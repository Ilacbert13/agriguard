<?php

namespace App\Services;

use App\Models\User;
use App\Support\RainChance;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FarmAssistantService
{
    private const LANG_EN = 'en';

    private const LANG_TL = 'tl';

    private const LANG_TAGLISH = 'taglish';

    private const LANG_OTHER = 'other';

    public function __construct(
        private readonly TogetherAiService $togetherAiService,
        private readonly FarmWeatherService $farmWeatherService,
        private readonly FarmRiskSnapshotService $riskSnapshotService,
        private readonly RainfallHeatmapService $rainfallHeatmapService,
        private readonly CropTimelineService $cropTimelineService,
        private readonly WeatherPredictionService $weatherPredictionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildAssistantContext(User $user): array
    {
        $stageKey = $this->cropTimelineService->normalizeStageKey((string) ($user->farming_stage ?? ''));
        $stageLabel = CropTimelineService::STAGE_LABELS[$stageKey] ?? 'Not set';
        $cropType = trim((string) ($user->crop_type ?? ''));
        $locationDisplay = trim((string) ($user->farm_location_display ?? ''));

        $hasGps = is_numeric($user->farm_lat ?? null) && is_numeric($user->farm_lng ?? null);
        $weather = $this->farmWeatherService->getNormalizedWeatherForUser($user);
        $apiRainFallback = isset($weather['today_rain_probability']) && is_numeric($weather['today_rain_probability'])
            ? (int) $weather['today_rain_probability']
            : null;
        $displayRainChance = $this->resolveWeatherPageRainChance($apiRainFallback);
        $riskFull = $this->riskSnapshotService->buildFromNormalizedWeather($user, $weather);
        $riskSnapshot = [
            'rain_chance_display' => RainChance::formatDisplay($displayRainChance),
            'three_day_effect' => (string) ($riskFull['three_day_outlook'] ?? $riskFull['three_day_effect'] ?? ''),
        ];
        $rainfallLabel = $this->rainfallHeatmapService->intensityLabel($weather);
        $rainfallMm = isset($weather['today_expected_rainfall']) && is_numeric($weather['today_expected_rainfall'])
            ? (float) $weather['today_expected_rainfall']
            : null;
        $rainfallPop = $displayRainChance;

        $sessionLang = (string) session('assistant_language_preference', self::LANG_EN);
        $sessionLang = in_array($sessionLang, [self::LANG_EN, self::LANG_TL, self::LANG_TAGLISH], true)
            ? $sessionLang
            : self::LANG_EN;

        return [
            'crop_type' => $cropType !== '' ? $cropType : 'Not set',
            'growth_stage' => $stageLabel,
            'growth_stage_key' => $stageKey,
            'barangay' => $user->farm_barangay_name ?: '—',
            'municipality' => $user->farm_municipality ?: '—',
            'location_display' => $locationDisplay !== '' ? $locationDisplay : '—',
            'has_gps' => $hasGps,
            'weather' => $weather,
            'current_weather_text' => $this->weatherLine($weather),
            'rainfall_level' => $rainfallLabel,
            'rainfall_mm' => $rainfallMm,
            'rainfall_probability' => $rainfallPop,
            'temperature_c' => is_numeric($weather['current_temperature'] ?? null) ? (float) $weather['current_temperature'] : null,
            'humidity' => is_numeric($weather['humidity'] ?? null) ? (int) $weather['humidity'] : null,
            'risk_snapshot' => $riskSnapshot,
            'display_rain_chance_percent' => $displayRainChance,
            'api_rain_fallback_percent' => $apiRainFallback,
            'current_date_human' => now()->format('l, F j, Y'),
            'assistant_language_preference' => $sessionLang,
        ];
    }

    /**
     * Matches Weather page Rain Chance: ML model rainfall buckets when available,
     * API today probability only as initial / error fallback.
     */
    private function resolveWeatherPageRainChance(?int $apiFallbackPercent): ?int
    {
        $modelTodayRainfallMm = null;

        try {
            $prediction = $this->weatherPredictionService->predict();
            $forecast = is_array($prediction['forecast'] ?? null) ? $prediction['forecast'] : [];
            $today = $forecast[0] ?? null;
            if (is_array($today) && is_numeric($today['rainfall'] ?? null)) {
                $modelTodayRainfallMm = (float) $today['rainfall'];
            }
        } catch (\Throwable) {
            $modelTodayRainfallMm = null;
        }

        return RainChance::weatherPageDisplay($modelTodayRainfallMm, $apiFallbackPercent);
    }

    /**
     * @param  array<int, array{role:string,content:string}>  $history
     * @return array<string,mixed>
     */
    public function answer(User $user, string $message, array $history, array $context): array
    {
        $normalized = trim($message);
        if ($normalized === '') {
            throw new RuntimeException('Please type your question first.');
        }

        $lang = $this->languageContextForMessage($normalized);
        $effectiveLanguage = (string) ($lang['effective_language'] ?? self::LANG_EN);

        $payload = [
            'language_context' => $lang,
            'farm_context' => [
                'crop_type' => $context['crop_type'] ?? 'Not set',
                'crop_stage' => $context['growth_stage'] ?? 'Not set',
                'location' => $context['location_display'] ?? '—',
                'barangay' => $context['barangay'] ?? '—',
                'date' => $context['current_date_human'] ?? now()->format('l, F j, Y'),
            ],
            'weather_context' => [
                'condition' => data_get($context, 'weather.condition', 'Unknown'),
                'rain_chance_percent' => $context['rainfall_probability'] ?? null,
                'rainfall_mm' => $context['rainfall_mm'] ?? null,
                'temperature_c' => $context['temperature_c'] ?? null,
                'humidity' => $context['humidity'] ?? null,
            ],
            'official_risk_snapshot' => [
                'three_day_effect' => data_get($context, 'risk_snapshot.three_day_effect', 'No forecast impact available'),
                'rain_chance' => data_get($context, 'risk_snapshot.rain_chance_display', '—'),
            ],
            'chat' => [
                'history' => $history,
                'latest_user_message' => $normalized,
            ],
        ];

        try {
            Log::info('Assistant risk snapshot payload', [
                'user_id' => $user->id,
                'official_risk_snapshot' => $payload['official_risk_snapshot'],
                'weather_context' => $payload['weather_context'],
            ]);
            $result = $this->togetherAiService->generateRecommendation(
                $payload,
                $this->assistantPrompt(),
                $this->assistantUserPreamble(),
            );
            $decoded = $this->decode((string) ($result['raw_content'] ?? ''));
            $text = is_array($decoded) ? trim((string) ($decoded['message'] ?? '')) : '';
            if ($text === '') {
                throw new RuntimeException('Malformed AI response');
            }

            return [
                'message' => $text,
                'meta' => [
                    'source' => 'ai_chat',
                    'language' => $effectiveLanguage,
                    'fallback_mode' => false,
                    'ai_model' => (string) ($result['model_used'] ?? ''),
                ],
            ];
        } catch (\Throwable) {
            return [
                'message' => $this->basicFallbackReply($context, $effectiveLanguage),
                'meta' => [
                    'source' => 'basic_fallback',
                    'language' => $effectiveLanguage,
                    'fallback_mode' => true,
                    'fallback_note' => 'Using basic farm guidance',
                ],
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function starterMessage(array $context): array
    {
        $lang = (string) ($context['assistant_language_preference'] ?? self::LANG_EN);
        $message = match ($lang) {
            self::LANG_TL => 'Kumusta! Ako ang AgriGuard AI Assistant — tumutulong sa tanim, panahon, paghahanda sa sakuna, at ligtas na desisyon sa farm. Magtanong tungkol sa ulan, bagyo, baha, tagtuyot, o iba pang gawain.',
            self::LANG_TAGLISH => 'Hi! I\'m your AgriGuard AI Assistant — farming plus disaster-aware tips (weather, floods, drought, storms). Ask about your crop and farm; I\'ll use your context.',
            default => 'Hi! I\'m your AgriGuard AI Assistant — farming and disaster-aware help (storms, floods, drought, heat). Ask about crops, weather, or preparedness; I use your farm context.',
        };

        return [
            'message' => $message,
            'meta' => [
                'source' => 'context_starter',
                'language' => $lang,
                'fallback_mode' => false,
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function languageContextForMessage(string $latestUserMessage): array
    {
        $m = mb_strtolower(trim($latestUserMessage), 'UTF-8');

        $sessionPref = session('assistant_language_preference', null);
        $sessionPref = is_string($sessionPref) ? $sessionPref : null;
        $sessionPref = in_array($sessionPref, [self::LANG_EN, self::LANG_TL, self::LANG_TAGLISH], true) ? $sessionPref : null;

        $explicit = null;
        if (preg_match('/\btaglish\b/i', $m)) {
            $explicit = self::LANG_TAGLISH;
        } elseif (preg_match('/\btagalog\b/i', $m)) {
            $explicit = self::LANG_TL;
        } elseif (preg_match('/\benglish\b/i', $m)) {
            $explicit = self::LANG_EN;
        }

        $tagalogPatterns = ['/paano/i', '/dapat/i', '/gawin/i', '/tanim/i', '/ulan/i', '/ngayon/i'];
        $englishPatterns = ['/can/i', '/should/i', '/what/i', '/how/i', '/today/i', '/plant/i', '/spray/i', '/water/i', '/rain/i'];

        $tagCount = 0;
        foreach ($tagalogPatterns as $p) {
            if (preg_match($p, $m) === 1) {
                $tagCount++;
            }
        }
        $enCount = 0;
        foreach ($englishPatterns as $p) {
            if (preg_match($p, $m) === 1) {
                $enCount++;
            }
        }

        $detected = self::LANG_OTHER;
        if ($explicit !== null) {
            $detected = $explicit;
        } elseif ($tagCount > 0 && $enCount > 0) {
            $detected = self::LANG_TAGLISH;
        } elseif ($tagCount > 0) {
            $detected = self::LANG_TL;
        } elseif ($enCount > 0) {
            $detected = self::LANG_EN;
        }

        if ($detected === self::LANG_OTHER) {
            $effective = $sessionPref ?? self::LANG_EN;
        } else {
            $effective = $detected;
            session(['assistant_language_preference' => $detected]);
        }

        return [
            'detected_language' => $effective,
            'effective_language' => $effective,
            'session_language' => $effective,
        ];
    }

    private function assistantPrompt(): string
    {
        return <<<'PROMPT'
You are AgriGuard Assistant, an AI farming and disaster-aware advisor in a chat interface.

MAIN GOAL
Sound like a natural human assistant: friendly, clear, and conversational—not a formatted report, dashboard export, or documentation page.

RESPONSE STYLE (STRICT)
- Write in normal prose: one or two short paragraphs that flow like speech.
- Mirror the user language (English, Tagalog, Taglish, or other). Use simple farming language.
- Start directly with the answer, then explain briefly only if it helps.
- Weave practical tips into sentences; avoid repeating the same advice in different shapes or sections.
- Do NOT use: headings or labels like "Summary", "Guide", "Steps", "Main advice"; numbered lists unless absolutely necessary; repeated blocks; heavy bullet lists; or UI-like or report-style formatting.
- Keep bullets rare—if you use them at all, at most one short line or two, never a long checklist unless the user explicitly asks for a list.

DISASTER AND WEATHER TOPICS (typhoons, floods, drought, extreme heat, storms)
Explain the situation simply in plain sentences. Give practical, safety-minded crop and farm guidance inside the same flowing paragraph(s). Do not stack many separate steps; stay conversational and focused on protection and safer choices. Never encourage unsafe behavior (e.g., working in floodwater, ignoring evacuation).

CORE ROLE
Cover agriculture plus disasters when relevant: typhoons, floods, droughts, earthquakes, landslides, heatwaves, storms; weather impacts; crop safety; preparedness; calm and safety-first always.

FARM CONTEXT
Use crop type, growth stage, weather/rain from payload, and location text when present so advice feels personal.

GROUNDING AND LIMITS (STRICT)
- Use payload.farm_context, payload.weather_context, and payload.chat for continuity. Treat payload.official_risk_snapshot as the source for embedded rain chance and short outlook—explain it faithfully; do not replace or recalculate it.
- Do not invent weather numbers or farm facts outside the payload.
- Do not claim to send real-time government alerts or replace official agencies (e.g., PAGASA, NDRRMC). When risk is serious or unclear, briefly remind the user to check official local advisories—without sounding like a canned footer every time.
- Do not give crop-loss percentages, yield estimates, or money figures; give practical field guidance instead.

Return strict JSON only:
{
  "message": "reply text the farmer will read"
}
PROMPT;
    }

    /**
     * User-turn preamble for Together AI (overrides default crop-only advisory text).
     */
    private function assistantUserPreamble(): string
    {
        return <<<'TXT'
Use only this input JSON for grounded weather numbers and outlook text—do not invent readings absent from the payload.
If the latest_user_message is a language-support or capability question (e.g., Tagalog/Taglish/English), acknowledge politely in the requested language and invite a farm or safety question.
Otherwise reply in natural conversational paragraphs per the system style rules (no report-style sections or heavy lists).
Return valid JSON only as specified in the system prompt.
TXT;
    }

    private function basicFallbackReply(array $context, string $lang): string
    {
        $rain = is_numeric($context['rainfall_probability'] ?? null) ? (int) $context['rainfall_probability'] : null;
        $weather = (string) data_get($context, 'weather.condition', 'Unknown');
        $stage = (string) ($context['growth_stage'] ?? 'current stage');

        if ($lang === self::LANG_TL) {
            return 'May pansamantalang issue sa AI kaya basic farm guidance muna. Sa ngayon, '.$weather.' ang kondisyon. Mas safe kung unahin mo ang field check at drainage, tapos i-adjust ang pagdidilig o pag-spray base sa ulan'.($rain !== null ? " ({$rain}% chance)." : '.');
        }
        if ($lang === self::LANG_TAGLISH) {
            return 'May temporary AI issue, so basic farm guidance muna. Right now, weather is '.$weather.'. Mas safe if unahin mo ang field check and drainage, then adjust watering or spraying based on rain'.($rain !== null ? " ({$rain}% chance)." : '.');
        }

        return 'There is a temporary AI issue, so I am using basic farm guidance. Right now, weather is '.$weather.'. The safer move is to start with field and drainage checks, then adjust watering or spraying based on rain'.($rain !== null ? " ({$rain}% chance)." : '.').' Keep actions appropriate for your '.$stage.'.';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decode(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = preg_replace('/^```json\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $m) === 1) {
            $decoded = json_decode((string) $m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function weatherLine(?array $weather): string
    {
        if (! is_array($weather)) {
            return 'Weather unavailable';
        }

        $temp = is_numeric($weather['current_temperature'] ?? null)
            ? round((float) $weather['current_temperature']).'°C'
            : '—';
        $condition = trim((string) ($weather['condition'] ?? 'Unknown'));

        return "{$temp} · {$condition}";
    }
}
