<?php

declare(strict_types=1);

// moduł leży w modules/currency_rate/cron/, root PS jest 3 poziomy wyżej
$psRoot = dirname(__DIR__, 3);

if (!file_exists($psRoot . '/config/config.inc.php')) {
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'PS root not found: ' . $psRoot]));
}

require_once $psRoot . '/config/config.inc.php';

if (PHP_SAPI !== 'cli') {
    $expectedToken = (string) Configuration::get('CURRENCY_RATE_CRON_TOKEN');
    $providedToken = (string) ($_GET['token'] ?? '');

    if (!$expectedToken || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        exit(json_encode(['status' => 'error', 'message' => 'Forbidden']));
    }
}

$moduleDir = dirname(__DIR__);
require_once $moduleDir . '/classes/NbpApiClient.php';
require_once $moduleDir . '/classes/FileCache.php';
require_once $moduleDir . '/classes/RateRepository.php';
require_once $moduleDir . '/classes/RateService.php';

$service = new RateService(
    new NbpApiClient(),
    new FileCache($moduleDir . '/cache/'),
    new RateRepository()
);

try {
    $service->refreshRates();

    $updatedAt = (new RateRepository())->getLastFetchedAt();

    PrestaShopLogger::addLog('CurrencyRate cron: rates refreshed at ' . ($updatedAt ?? 'unknown'), 1);

    if (PHP_SAPI === 'cli') {
        echo '[' . date('Y-m-d H:i:s') . '] OK fetched_at=' . ($updatedAt ?? 'n/a') . PHP_EOL;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'updated_at' => $updatedAt]);
    }
} catch (Exception $e) {
    PrestaShopLogger::addLog('CurrencyRate cron error: ' . $e->getMessage(), 3);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $e->getMessage() . PHP_EOL);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit(1);
}
