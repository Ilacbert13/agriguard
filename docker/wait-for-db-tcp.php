<?php

declare(strict_types=1);

/**
 * Exit 0 once MySQL accepts TCP on the host/port from Laravel DB env.
 * Used by docker/agriguard-start.sh before migrate — avoids "connection refused" while MySQL boots.
 * when MySQL is still booting.
 */
$endpoint = static function (): array {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    if (is_string($host) && $host !== '') {
        return [$host, (int) $port];
    }

    foreach (['DATABASE_URL', 'MYSQL_URL'] as $key) {
        $url = getenv($key);
        if (! is_string($url) || $url === '') {
            continue;
        }
        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['host'])) {
            return [
                $parts['host'],
                (int) ($parts['port'] ?? 3306),
            ];
        }
    }

    fwrite(STDERR, "wait-for-db-tcp: set DB_HOST or DATABASE_URL / MYSQL_URL\n");

    exit(1);
};

[$host, $port] = $endpoint();

$maxAttempts = (int) (getenv('AGRIGUARD_DB_WAIT_ATTEMPTS') ?: 120);
$sleepSeconds = (int) (getenv('AGRIGUARD_DB_WAIT_SLEEP') ?: 2);

for ($i = 0; $i < $maxAttempts; $i++) {
    $fp = @stream_socket_client(
        "tcp://{$host}:{$port}",
        $errno,
        $errstr,
        2,
        STREAM_CLIENT_CONNECT
    );
    if ($fp !== false) {
        fclose($fp);
        fwrite(STDERR, "wait-for-db-tcp: {$host}:{$port} is accepting connections (attempt ".($i + 1).")\n");
        exit(0);
    }

    fwrite(STDERR, "wait-for-db-tcp: waiting for {$host}:{$port} (".($i + 1)."/{$maxAttempts}) {$errstr}\n");
    sleep($sleepSeconds);
}

fwrite(STDERR, "wait-for-db-tcp: timed out after {$maxAttempts} attempts\n");

exit(1);
