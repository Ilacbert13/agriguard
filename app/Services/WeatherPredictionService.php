<?php

namespace App\Services;

use App\Models\HistoricalWeather;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;

class WeatherPredictionService
{
    /**
     * Per-request memo so a single dashboard / map / weather page render only
     * computes / fetches the prediction once even if called from multiple
     * services. Combined with the long-lived cache below, repeat user requests
     * almost always hit warm data (and never re-train the model).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $requestMemo = [];

    /**
     * @return array{
     *   status: string,
     *   rainfall: float,
     *   wind_speed: float,
     *   forecast: array<int, array{day:int,date:?string,rainfall:float,wind_speed:float}>,
     *   source: string,
     *   features: array<string, float|int>,
     *   computed_at: string,
     *   model_performance: array<string, float|string>
     * }
     */
    public function predict(): array
    {
        $input = $this->buildInputFeatures();
        $jsonInput = json_encode($input, JSON_THROW_ON_ERROR);

        if (isset($this->requestMemo[$jsonInput])) {
            return $this->requestMemo[$jsonInput];
        }

        $cacheMinutes = (int) config('agriweather.prediction.cache_minutes', 15);
        $cacheKey = $this->buildCacheKey($jsonInput);

        $prediction = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, $cacheMinutes)),
            fn (): array => $this->invokePythonPredictor($jsonInput)
        );

        return $this->requestMemo[$jsonInput] = array_merge($prediction, [
            'source' => 'model',
            'features' => $input,
            'computed_at' => now()->toIso8601String(),
        ]);
    }

    private function buildCacheKey(string $jsonInput): string
    {
        $scriptPath = (string) config('agriweather.prediction.script_path', base_path('python/predict.py'));
        $modelPath = (string) config('agriweather.prediction.model_path', base_path('python/model/xgboost_weather_model.pkl'));
        $metaPath = preg_replace('/\.pkl$/', '.meta.json', $modelPath) ?: '';

        $signatureParts = [
            is_file($scriptPath) ? (string) filemtime($scriptPath) : 'no-script',
            is_file($modelPath) ? (string) filemtime($modelPath) : 'no-model',
            $metaPath !== '' && is_file($metaPath) ? (string) filemtime($metaPath) : 'no-meta',
            md5($jsonInput),
        ];

        return 'weather_prediction:v4:'.md5(implode('|', $signatureParts));
    }

    /**
     * @return array<string, float|int>
     */
    private function buildInputFeatures(): array
    {
        $today = now();
        $recentRows = HistoricalWeather::query()
            ->validCalendarRows()
            ->whereDate('date', '<=', now()->toDateString())
            ->whereNotNull('rainfall')
            ->whereNotNull('wind_speed')
            ->orderByDesc('date')
            ->limit(3)
            ->get(['date', 'rainfall', 'wind_speed', 'wind_direction']);

        if ($recentRows->isEmpty()) {
            throw new RuntimeException(
                'Prediction input is incomplete: no recent historical weather rows found. '
                .'Add at least one historical weather row with rainfall and wind_speed.'
            );
        }

        return [
            'year' => (int) $today->format('Y'),
            'month' => (int) $today->format('m'),
            'day' => (int) $today->format('d'),
            'wind_direction' => $this->resolveWindDirectionFeature($recentRows->first()?->wind_direction),
            'rainfall_lag1' => (float) $recentRows[0]->rainfall,
            'rainfall_lag2' => isset($recentRows[1]) ? (float) $recentRows[1]->rainfall : (float) $recentRows[0]->rainfall,
            'wind_lag1' => (float) $recentRows[0]->wind_speed,
            'wind_lag2' => isset($recentRows[1]) ? (float) $recentRows[1]->wind_speed : (float) $recentRows[0]->wind_speed,
            'rainfall_avg3' => round((float) $recentRows->avg('rainfall'), 4),
            'wind_avg3' => round((float) $recentRows->avg('wind_speed'), 4),
        ];
    }

    /**
     * @return array{
     *   status: string,
     *   rainfall: float,
     *   wind_speed: float,
     *   forecast: array<int, array{day:int,date:?string,rainfall:float,wind_speed:float}>
     * }
     */
    private function invokePythonPredictor(string $jsonInput): array
    {
        $scriptPath = (string) config('agriweather.prediction.script_path', base_path('python/predict.py'));
        if (! is_file($scriptPath)) {
            throw new RuntimeException('Missing predict script at '.$scriptPath);
        }

        $modelPath = (string) config('agriweather.prediction.model_path', base_path('python/model/xgboost_weather_model.pkl'));
        if (! is_file($modelPath)) {
            throw new RuntimeException('Missing model at '.$modelPath);
        }

        $fallbackPython = PHP_OS_FAMILY === 'Windows'
            ? base_path('.venv/Scripts/python.exe')
            : base_path('.venv/bin/python');
        $pythonBin = (string) config('agriweather.prediction.python_bin', $fallbackPython);
        if (! is_file($pythonBin)) {
            throw new RuntimeException('Python interpreter not found at '.$pythonBin);
        }

        $encodedInput = base64_encode($jsonInput);
        $timeout = (int) config('agriweather.prediction.timeout_seconds', 45);

        $process = new Process([$pythonBin, $scriptPath, $encodedInput], base_path(), [
            'AGRIWEATHER_MODEL_PATH' => $modelPath,
            'JOBLIB_MULTIPROCESSING' => '0',
            'OMP_NUM_THREADS' => '1',
            'OPENBLAS_NUM_THREADS' => '1',
            'PYTHONNOUSERSITE' => '1',
        ]);
        $process->setTimeout(max(5, $timeout));
        $process->run();

        $rawOutput = trim($process->getOutput().$process->getErrorOutput());
        if (! $process->isSuccessful()) {
            throw new RuntimeException('ML predictor process failed: '.($rawOutput !== '' ? $rawOutput : 'unknown process error'));
        }
        if ($rawOutput === '') {
            throw new RuntimeException('Python predictor returned empty output.');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($rawOutput, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Predictor output is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Prediction output is not a JSON object.');
        }

        if (($decoded['status'] ?? null) === 'error') {
            $message = is_string($decoded['message'] ?? null) ? $decoded['message'] : 'Unknown Python model error';
            throw new RuntimeException('Model loading or inference issue: '.$message);
        }

        if (($decoded['status'] ?? null) !== 'success') {
            throw new RuntimeException('Python response did not include status=success.');
        }

        if (! isset($decoded['forecast']) || ! is_array($decoded['forecast']) || count($decoded['forecast']) < 1) {
            throw new RuntimeException('Prediction payload missing forecast array.');
        }

        $forecast = [];
        foreach ($decoded['forecast'] as $item) {
            if (! is_array($item)
                || ! isset($item['day'], $item['rainfall'], $item['wind_speed'])
                || ! is_numeric($item['day'])
                || ! is_numeric($item['rainfall'])
                || ! is_numeric($item['wind_speed'])
            ) {
                throw new RuntimeException('Prediction payload contains malformed forecast rows.');
            }

            $forecast[] = [
                'day' => (int) $item['day'],
                'date' => isset($item['date']) && is_string($item['date']) ? $item['date'] : null,
                'rainfall' => (float) $item['rainfall'],
                'wind_speed' => (float) $item['wind_speed'],
            ];
        }

        $today = $forecast[0];
        $modelPerformance = [];
        if (isset($decoded['model_performance']) && is_array($decoded['model_performance'])) {
            $modelPerformance = $decoded['model_performance'];
        }

        return [
            'status' => 'success',
            'rainfall' => $today['rainfall'],
            'wind_speed' => $today['wind_speed'],
            'forecast' => $forecast,
            'model_performance' => $modelPerformance,
        ];
    }

    private function resolveWindDirectionFeature(mixed $historicalDirection): float
    {
        if (is_numeric($historicalDirection)) {
            return $this->normalizeWindDirection((float) $historicalDirection);
        }

        if (is_string($historicalDirection)) {
            return $this->normalizeWindDirection($this->cardinalDirectionToDegrees($historicalDirection));
        }

        return 0.5;
    }

    private function normalizeWindDirection(float $degrees): float
    {
        $normalized = fmod($degrees, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        return round($normalized / 360.0, 4);
    }

    private function cardinalDirectionToDegrees(string $direction): float
    {
        $key = strtoupper(trim($direction));
        $map = [
            'N' => 0.0,
            'NNE' => 22.5,
            'NE' => 45.0,
            'ENE' => 67.5,
            'E' => 90.0,
            'ESE' => 112.5,
            'SE' => 135.0,
            'SSE' => 157.5,
            'S' => 180.0,
            'SSW' => 202.5,
            'SW' => 225.0,
            'WSW' => 247.5,
            'W' => 270.0,
            'WNW' => 292.5,
            'NW' => 315.0,
            'NNW' => 337.5,
        ];

        return $map[$key] ?? 180.0;
    }
}
