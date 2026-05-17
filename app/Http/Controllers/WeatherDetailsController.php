<?php

namespace App\Http\Controllers;

use App\Services\AiAdvisory\AiAdvisoryService;
use App\Services\ThreeDayWeatherOutlookService;
use App\Services\WeatherAdvisoryService;
use App\Services\WeatherPredictionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WeatherDetailsController extends Controller
{
    /**
     * Show the Weather Details page with full forecast, metrics, and charts.
     */
    public function show(
        WeatherAdvisoryService $weatherService,
        AiAdvisoryService $aiAdvisory,
        ThreeDayWeatherOutlookService $threeDayOutlook,
        WeatherPredictionService $weatherPredictionService,
    ): View {
        $user = Auth::user();
        $data = $weatherService->getAdvisoryData($user);

        $weather = $data['weather'] ?? null;
        $forecast = $data['forecast'] ?? [];
        $locationDisplay = $data['location_display'] ?? '—';
        $advisory = $data['advisory'] ?? [];

        if ($weather !== null) {
            $weather['wind_direction_label'] = WeatherAdvisoryService::windDirectionLabel(
                isset($weather['wind_direction']) ? (float) $weather['wind_direction'] : null
            );
        }

        foreach ($forecast as $i => $day) {
            $forecast[$i]['wind_direction_label'] = WeatherAdvisoryService::windDirectionLabel(
                isset($day['wind_deg']) ? (float) $day['wind_deg'] : null
            );
            $forecast[$i]['simple_label'] = self::simpleWeatherLabel($day['condition']['id'] ?? 800);
            $forecast[$i]['simple_icon'] = self::simpleWeatherIcon($day['condition']['id'] ?? 800);
        }

        if ($weather !== null) {
            $weather['simple_label'] = self::simpleWeatherLabel($weather['condition']['id'] ?? 800);
            $weather['simple_icon'] = self::simpleWeatherIcon($weather['condition']['id'] ?? 800);
        }

        $farmLocationDisplay = $user->farm_location_display;

        $smartAdvisory = $data['smart_advisory'] ?? [];
        $forecastRainProb = $data['forecast_rain_probability'] ?? null;
        $rainProbDisplay = $data['rain_probability_display'] ?? $forecastRainProb ?? (empty($forecast) ? null : (int) max(array_column($forecast, 'pop')));

        $weatherOutlook = $threeDayOutlook->build($weather, $forecast);

        $todayRainfallMm = is_array($weather) ? ($weather['today_expected_rainfall'] ?? null) : null;
        $weekRainfallMm = is_numeric($todayRainfallMm) ? ((float) $todayRainfallMm * 7) : null;
        $monthRainfallMm = is_numeric($todayRainfallMm) ? ((float) $todayRainfallMm * 30) : null;

        $weatherInput = $aiAdvisory->buildWeatherInput(
            $user,
            $weather,
            $forecast,
            $data['hourly_forecast'] ?? [],
            $locationDisplay,
            $rainProbDisplay,
            $todayRainfallMm,
            $weekRainfallMm,
            $monthRainfallMm
        );
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));
        $weatherRun = $aiAdvisory->run(AiAdvisoryService::PAGE_WEATHER, $user, $weatherInput);
        $smartRecommendation = [
            'recommendation' => $aiAdvisory->formatWeatherRecommendation($weatherRun, $modelName),
            'failed' => (($weatherRun['_meta']['ai_status'] ?? 'failed') !== 'success'),
        ];

        $mlPrediction = null;
        try {
            $mlPrediction = $weatherPredictionService->predict();
        } catch (\Throwable $exception) {
            Log::warning('Weather page could not preload ML prediction', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }

        return view('user.weather.weather-details', [
            'weather' => $weather,
            'forecast' => $forecast,
            'location_display' => $locationDisplay,
            'farm_location_display' => $farmLocationDisplay,
            'advisory' => $advisory,
            'smart_advisory' => $smartAdvisory,
            'forecast_rain_probability' => $forecastRainProb,
            'rain_probability_display' => $rainProbDisplay,
            'weatherData' => $data['weather_data'] ?? [],
            'last_updated' => $data['last_updated'] ?? null,
            'recommendation' => $smartRecommendation['recommendation'],
            'recommendation_failed' => $smartRecommendation['failed'],
            'weather_outlook' => $weatherOutlook,
            'ml_prediction' => $mlPrediction,
        ]);
    }

    /**
     * Map OpenWeatherMap condition id to simple farmer-friendly label.
     */
    public static function simpleWeatherLabel(int $conditionId): string
    {
        if ($conditionId >= 200 && $conditionId < 300) {
            return 'Storm';
        }
        if ($conditionId >= 300 && $conditionId < 600) {
            return 'Rain';
        }
        if ($conditionId >= 600 && $conditionId < 700) {
            return 'Snow';
        }
        if ($conditionId === 800) {
            return 'Clear';
        }
        if ($conditionId === 801) {
            return 'Partly cloudy';
        }
        if ($conditionId === 802 || $conditionId === 803) {
            return 'Cloudy';
        }
        if ($conditionId === 804) {
            return 'Overcast';
        }

        return 'Cloudy';
    }

    /**
     * Map OpenWeatherMap condition id to Lucide icon name.
     */
    public static function simpleWeatherIcon(int $conditionId): string
    {
        if ($conditionId >= 200 && $conditionId < 300) {
            return 'cloud-lightning';
        }
        if ($conditionId >= 300 && $conditionId < 600) {
            return 'cloud-rain';
        }
        if ($conditionId >= 600 && $conditionId < 700) {
            return 'cloud-snow';
        }
        if ($conditionId === 800) {
            return 'sun';
        }
        if ($conditionId >= 801 && $conditionId <= 804) {
            return 'cloud';
        }

        return 'cloud';
    }

    /**
     * Map OpenWeatherMap condition id to clay-style SVG variant (see x-clay-weather-icon).
     */
    public static function simpleWeatherClayType(int $conditionId): string
    {
        if ($conditionId >= 200 && $conditionId < 300) {
            return 'storm';
        }
        if ($conditionId >= 300 && $conditionId < 600) {
            return 'rain';
        }
        if ($conditionId >= 600 && $conditionId < 700) {
            return 'snow';
        }
        if ($conditionId === 800) {
            return 'sun';
        }
        if ($conditionId === 801) {
            return 'partly_cloudy';
        }
        if ($conditionId === 802) {
            return 'cloud';
        }
        if ($conditionId === 803) {
            return 'cloud';
        }
        if ($conditionId === 804) {
            return 'overcast';
        }

        return 'cloud';
    }

    /**
     * Map OpenWeatherMap condition id to a single emoji (matches dashboard farm-summary style).
     */
    public static function simpleWeatherEmoji(int $conditionId): string
    {
        if ($conditionId >= 200 && $conditionId < 300) {
            return '⛈️';
        }
        if ($conditionId >= 300 && $conditionId < 600) {
            return '🌧️';
        }
        if ($conditionId >= 600 && $conditionId < 700) {
            return '❄️';
        }
        if ($conditionId === 800) {
            return '☀️';
        }
        if ($conditionId === 801) {
            return '🌤️';
        }
        if ($conditionId === 802) {
            return '⛅';
        }
        if ($conditionId >= 803 && $conditionId <= 804) {
            return '☁️';
        }

        return '☁️';
    }

    /**
     * Build a short farm impact message based on weather and crop.
     */
    public static function buildFarmImpactMessage(?array $weather, array $forecast, ?int $rainProb, ?string $cropType, ?string $farmingStage): string
    {
        $cropLower = $cropType ? strtolower($cropType) : '';
        $condId = $weather['condition']['id'] ?? 800;
        $humidity = $weather['humidity'] ?? 0;
        $windSpeed = (float) ($weather['wind_speed'] ?? 0);

        if ($condId >= 300 && $condId < 600) {
            if (str_contains($cropLower, 'rice')) {
                return 'Rain may affect paddy water level. Check drainage and avoid fertilizer application before heavy rain.';
            }
            if (str_contains($cropLower, 'corn')) {
                return 'Rain may cause runoff. Young corn roots need good soil drainage. Monitor for standing water.';
            }
            if (str_contains($cropLower, 'vegetable')) {
                return 'Rain may damage sensitive vegetables. Protect raised beds and harvest ready produce early if needed.';
            }

            return 'Rain may cause water buildup in low areas. Prepare drainage and monitor field conditions.';
        }

        if ($rainProb !== null && $rainProb >= 60) {
            if ($farmingStage === 'planting' && str_contains($cropLower, 'rice')) {
                return 'Newly planted rice may be vulnerable to washout. Ensure bunds are secure and drainage is clear.';
            }
            if ($farmingStage === 'early_growth' && str_contains($cropLower, 'corn')) {
                return 'Young corn plants may be affected by prolonged wet soil. Watch for root stress.';
            }
            if (in_array($farmingStage, ['harvest', 'harvesting'], true)) {
                return 'Harvest-ready crops may need early harvest if heavy rain continues. Avoid spoilage and water damage.';
            }
        }

        if ($humidity >= 80) {
            return 'High humidity may increase moisture-related crop stress. Monitor for disease and ensure good air flow.';
        }

        if ($windSpeed >= 20) {
            return 'Strong winds may affect young corn or vegetable crops. Secure covers and check plant stability.';
        }

        if ($condId === 800 || $condId === 801) {
            return 'Conditions are stable. Light rain may help maintain soil moisture. Good for normal farm activities.';
        }

        return 'Monitor weather and field conditions. Prepare drainage for possible rain.';
    }

    /**
     * Short farmer-friendly weather summary based on forecast.
     */
    public static function weatherSummaryMessage(array $forecast): string
    {
        if (empty($forecast)) {
            return 'Check back later for weather updates.';
        }
        $maxPop = max(array_column($forecast, 'pop'));
        $rainyDays = count(array_filter($forecast, fn ($d) => ($d['pop'] ?? 0) >= 50));
        if ($maxPop >= 70 || $rainyDays >= 2) {
            return 'Rain expected in the next few days. Prepare farm drainage.';
        }
        if ($maxPop >= 40 || $rainyDays >= 1) {
            return 'Some rain possible. Keep an eye on the forecast.';
        }

        return 'Weather looks stable. Low chance of rain.';
    }

    /**
     * Approximate dew point in Celsius using the Magnus formula.
     */
    public static function estimateDewPoint(?float $tempC, ?float $humidityPercent): ?float
    {
        if ($tempC === null || $humidityPercent === null || $humidityPercent <= 0) {
            return null;
        }

        $humidityPercent = max(1.0, min(100.0, $humidityPercent));
        $a = 17.27;
        $b = 237.7;
        $alpha = (($a * $tempC) / ($b + $tempC)) + log($humidityPercent / 100.0);
        $dewPoint = ($b * $alpha) / ($a - $alpha);

        return round($dewPoint, 1);
    }

    /**
     * Estimate cloud cover percentage based on current condition and forecast tendency.
     */
    public static function estimateCloudCover(?array $weather, array $forecast): ?int
    {
        $conditionId = (int) ($weather['condition']['id'] ?? 800);
        $base = match (true) {
            $conditionId === 800 => 10,
            $conditionId === 801 => 30,
            $conditionId === 802 => 55,
            $conditionId === 803 => 75,
            $conditionId === 804 => 90,
            $conditionId >= 200 && $conditionId < 600 => 85,
            default => 60,
        };

        if (empty($forecast)) {
            return $base;
        }

        $avgPop = (int) round(array_sum(array_map(fn ($day) => (int) ($day['pop'] ?? 0), $forecast)) / max(count($forecast), 1));
        $adjusted = (int) round($base + ($avgPop - 30) * 0.25);

        return max(5, min(100, $adjusted));
    }

    /**
     * Build practical weather-to-farming recommendations.
     */
    public static function buildAgricultureInsights(?array $weather, array $forecast, ?int $rainProbDisplay, ?string $cropType, ?string $farmingStage): array
    {
        $crop = $cropType ?: 'Crops';
        $rainChance = $rainProbDisplay ?? ($weather['today_rain_probability'] ?? null);
        $rainMm = $weather['today_expected_rainfall'] ?? null;
        $humidity = $weather['humidity'] ?? null;

        $soilMoisture = 'Normal moisture retention expected. Keep routine field checks.';
        if (is_numeric($rainChance) && (int) $rainChance >= 55) {
            $soilMoisture = 'Rain is likely. Reduce manual watering and improve drainage channels.';
        } elseif (is_numeric($rainMm) && (float) $rainMm >= 5) {
            $soilMoisture = 'Light to moderate rain expected. Monitor low-lying plots for pooling.';
        }

        $irrigation = 'Proceed with standard irrigation schedule.';
        if (is_numeric($rainChance) && (int) $rainChance >= 60) {
            $irrigation = 'Delay irrigation for now and reassess after the next forecast update.';
        } elseif (is_numeric($humidity) && (int) $humidity >= 80) {
            $irrigation = 'Use lighter irrigation cycles to avoid excess moisture stress.';
        }

        $cropSafety = 'Safe for normal farm tasks today.';
        if ($farmingStage === 'planting' && is_numeric($rainChance) && (int) $rainChance <= 60) {
            $cropSafety = "Good window for {$crop} planting. Soil moisture and rainfall outlook are manageable.";
        } elseif (is_numeric($rainChance) && (int) $rainChance >= 70) {
            $cropSafety = 'Moderate risk for planting operations. Protect seedlings and postpone fertilizer spreading.';
        }

        $nextTwoDaysRain = array_sum(array_map(fn ($day) => (int) ($day['pop'] ?? 0), array_slice($forecast, 0, 2)));
        $alert = $nextTwoDaysRain >= 120
            ? 'Rain trend is rising in the next 48 hours. Secure channels and check runoff paths.'
            : 'No severe short-term rain signal. Continue with planned field activities.';

        return [
            'soil_moisture' => $soilMoisture,
            'irrigation' => $irrigation,
            'crop_safety' => $cropSafety,
            'alert' => $alert,
        ];
    }
}
