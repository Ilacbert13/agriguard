<?php

declare(strict_types=1);

/**
 * Block until Laravel can open a PDO connection to the default DB (same as php artisan migrate).
 * Raw TCP (wait-for-db-tcp) is insufficient: you can get "connection timed out" on PDO while TCP "succeeds".
 */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../vendor/autoload.php';

// Avoid multi-minute hangs per failed attempt (common cause of long deploys).
ini_set('default_socket_timeout', getenv('AGRIGUARD_DB_SOCKET_TIMEOUT') ?: '10');

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$max = (int) (getenv('AGRIGUARD_DB_WAIT_ATTEMPTS') ?: 120);
$sleep = (int) (getenv('AGRIGUARD_DB_WAIT_SLEEP') ?: 2);

$connection = (string) $app['config']->get('database.default', 'mysql');

// Short MySQL connect timeout when PDO exposes it (otherwise default_socket_timeout applies).
$key = 'database.connections.'.$connection.'.options';
$opts = (array) $app['config']->get($key, []);
$connectTimeout = (int) (getenv('AGRIGUARD_DB_CONNECT_TIMEOUT_SEC') ?: 10);
if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
    $opts[\PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = $connectTimeout;
}
$app['config']->set($key, $opts);

for ($i = 0; $i < $max; $i++) {
    try {
        $db = $app->make('db');
        $db->purge($connection);
        $db->connection($connection)->reconnect();
        $db->connection($connection)->getPdo();
        fwrite(STDERR, "wait-for-laravel-db: connected ({$connection}, attempt ".($i + 1).")\n");
        exit(0);
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, "\n")) {
            $msg = (string) preg_replace('/\s+/', ' ', $msg);
        }
        fwrite(STDERR, "wait-for-laravel-db: waiting (".($i + 1)."/{$max}): {$msg}\n");
        sleep($sleep);
    }
}

fwrite(STDERR, "wait-for-laravel-db: giving up after {$max} attempts (check Railway: MySQL running, same project, DB_* / DATABASE_URL from service reference)\n");

exit(1);
