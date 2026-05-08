<?php

declare(strict_types=1);

/**
 * Bench HTTP para endpoints publicos de autenticacao/entrada.
 *
 * Uso:
 * php -d xdebug.mode=off tools/performance/run-auth-http-benchmark.php
 */

function percentile(array $values, float $p): float
{
    if ($values === []) {
        return 0.0;
    }

    sort($values);
    $index = (int) ceil(($p / 100.0) * count($values)) - 1;
    $index = max(0, min($index, count($values) - 1));
    return (float) $values[$index];
}

function summarize(array $latenciesMs, float $elapsedSec, int $errors): array
{
    if ($latenciesMs === []) {
        return [
            'count' => 0,
            'errors' => $errors,
            'min_ms' => 0.0,
            'p50_ms' => 0.0,
            'p95_ms' => 0.0,
            'p99_ms' => 0.0,
            'mean_ms' => 0.0,
            'max_ms' => 0.0,
            'elapsed_s' => round($elapsedSec, 3),
            'rps' => 0.0,
        ];
    }

    $count = count($latenciesMs);
    $sum = array_sum($latenciesMs);

    return [
        'count' => $count,
        'errors' => $errors,
        'min_ms' => round((float) min($latenciesMs), 2),
        'p50_ms' => round(percentile($latenciesMs, 50), 2),
        'p95_ms' => round(percentile($latenciesMs, 95), 2),
        'p99_ms' => round(percentile($latenciesMs, 99), 2),
        'mean_ms' => round($sum / $count, 2),
        'max_ms' => round((float) max($latenciesMs), 2),
        'elapsed_s' => round($elapsedSec, 3),
        'rps' => $elapsedSec > 0 ? round($count / $elapsedSec, 2) : 0.0,
    ];
}

function newHandle(string $url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Connection: close'],
    ]);

    return $ch;
}

function warmup(string $url, int $iterations = 8): void
{
    for ($i = 0; $i < $iterations; $i++) {
        $ch = newHandle($url);
        curl_exec($ch);
        curl_close($ch);
    }
}

function sequentialBenchmark(string $url, int $requests): array
{
    $latencies = [];
    $errors = 0;
    $start = microtime(true);

    for ($i = 0; $i < $requests; $i++) {
        $ch = newHandle($url);
        $reqStart = microtime(true);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $latencies[] = (microtime(true) - $reqStart) * 1000;
        if ($errno !== 0 || $status >= 400 || $status === 0) {
            $errors++;
        }
        curl_close($ch);
    }

    $elapsed = microtime(true) - $start;
    return summarize($latencies, $elapsed, $errors);
}

function concurrentBenchmark(string $url, int $totalRequests, int $concurrency): array
{
    $latencies = [];
    $errors = 0;
    $start = microtime(true);

    $mh = curl_multi_init();
    $active = 0;
    $issued = 0;
    $starts = [];

    $enqueue = function () use ($url, $mh, &$issued, $totalRequests, &$starts): bool {
        if ($issued >= $totalRequests) {
            return false;
        }

        $ch = newHandle($url);
        $id = spl_object_id($ch);
        $starts[$id] = microtime(true);
        curl_multi_add_handle($mh, $ch);
        $issued++;

        return true;
    };

    for ($i = 0; $i < $concurrency; $i++) {
        if (!$enqueue()) {
            break;
        }
    }

    do {
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);

        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $id = spl_object_id($ch);
            $reqStart = $starts[$id] ?? microtime(true);
            unset($starts[$id]);

            $latencies[] = (microtime(true) - $reqStart) * 1000;

            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($errno !== 0 || $status >= 400 || $status === 0 || $info['result'] !== CURLE_OK) {
                $errors++;
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $enqueue();
        }

        if ($active) {
            curl_multi_select($mh, 1.0);
        }
    } while ($active || $issued < $totalRequests);

    curl_multi_close($mh);

    $elapsed = microtime(true) - $start;
    return summarize($latencies, $elapsed, $errors);
}

$base = 'http://localhost/nosfirsolis';
$endpoints = [
    'Landing' => $base . '/',
    'ClientLogin' => $base . '/client/auth/login',
    'AdminLogin' => $base . '/admin/auth/login',
    'ForgotPassword' => $base . '/client/auth/forgotpassword',
    'ForgotEmail' => $base . '/client/auth/forgotemail',
];

$sequentialRequests = 120;
$concurrentRequests = 240;
$concurrency = 12;

$results = [];
foreach ($endpoints as $name => $url) {
    warmup($url);

    $results[] = [
        'endpoint' => $name,
        'url' => $url,
        'sequential' => sequentialBenchmark($url, $sequentialRequests),
        'concurrent' => concurrentBenchmark($url, $concurrentRequests, $concurrency),
    ];
}

$payload = [
    'generated_at' => date('Y-m-d H:i:s'),
    'runtime' => [
        'php' => PHP_VERSION,
        'sapi' => PHP_SAPI,
    ],
    'method' => [
        'sequential_requests' => $sequentialRequests,
        'concurrent_total_requests' => $concurrentRequests,
        'concurrent_workers' => $concurrency,
    ],
    'results' => $results,
];

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
