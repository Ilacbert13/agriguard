<?php

declare(strict_types=1);

/**
 * Single Laravel process: wait for DB, then migrate → seed → CSV import.
 * Avoids a second `php artisan migrate` subprocess — on Railway, that process often hits
 * SQLSTATE[2002] timeouts to *.railway.internal even after a probe connection succeeded.
 */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';

ini_set('default_socket_timeout', getenv('AGRIGUARD_DB_SOCKET_TIMEOUT') ?: '10');

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$max = (int) (getenv('AGRIGUARD_DB_WAIT_ATTEMPTS') ?: 120);
$sleep = (int) (getenv('AGRIGUARD_DB_WAIT_SLEEP') ?: 2);

$connection = (string) $app['config']->get('database.default', 'mysql');

$key = 'database.connections.'.$connection.'.options';
$opts = (array) $app['config']->get($key, []);
$connectTimeout = (int) (getenv('AGRIGUARD_DB_CONNECT_TIMEOUT_SEC') ?: 10);
if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
    $opts[\PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = $connectTimeout;
}
$app['config']->set($key, $opts);

$connected = false;
for ($i = 0; $i < $max; $i++) {
    try {
        $db = $app->make('db');
        $db->purge($connection);
        $db->connection($connection)->reconnect();
        $db->connection($connection)->getPdo();
        $connected = true;
        fwrite(STDERR, 'agriguard-db-setup: connected ('.$connection.', attempt '.($i + 1).")\n");
        break;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, "\n")) {
            $msg = (string) preg_replace('/\s+/', ' ', $msg);
        }
        fwrite(STDERR, 'agriguard-db-setup: waiting ('.($i + 1)."/{$max}): {$msg}\n");
        sleep($sleep);
    }
}

if (! $connected) {
    fwrite(STDERR, "agriguard-db-setup: database unreachable after {$max} attempts\n");
    exit(1);
}

$postSleep = (int) (getenv('AGRIGUARD_POST_TCP_SLEEP') ?: 15);
if ($postSleep > 0) {
    fwrite(STDERR, "agriguard-db-setup: sleeping {$postSleep}s before migrate\n");
    sleep($postSleep);
}

$csvPath = storage_path('app/public/historical_weather.csv');

$steps = [
    ['migrate', ['--force' => true]],
    ['db:seed', ['--force' => true]],
    ['historical-weather:import', ['path' => $csvPath]],
];

foreach ($steps as [$command, $arguments]) {
    $code = Artisan::call($command, $arguments);
    if ($code !== 0) {
        fwrite(STDERR, "agriguard-db-setup: `{$command}` exited {$code}\n");
        fwrite(STDERR, Artisan::output());

        exit($code);
    }
    fwrite(STDERR, "agriguard-db-setup: completed `{$command}`\n");
}

exit(0);
