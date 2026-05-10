<?php

$defaultPythonBin = PHP_OS_FAMILY === 'Windows'
    ? base_path('.venv/Scripts/python.exe')
    : base_path('.venv/bin/python');

return [
    'prediction' => [
        'python_bin' => env('AGRIWEATHER_PYTHON_BIN', $defaultPythonBin),
        'script_path' => env('AGRIWEATHER_PREDICT_SCRIPT', base_path('python/predict.py')),
        'model_path' => env('AGRIWEATHER_MODEL_PATH', base_path('python/model/xgboost_weather_model.pkl')),
        'timeout_seconds' => (int) env('AGRIWEATHER_PREDICT_TIMEOUT', 45),
        'cache_minutes' => (int) env('AGRIWEATHER_PREDICT_CACHE_MINUTES', 15),
    ],

    'model_performance' => [
        'dataset' => '2014-2024 historical weather',
        'rainfall_r2' => 0.847,
        'wind_r2' => 0.791,
        'overall_accuracy' => 81.9,
    ],
];
