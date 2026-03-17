<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class RateService
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly NbpApiClient   $apiClient,
        private readonly FileCache      $cache,
        private readonly RateRepository $repository
    ) {}

    public function getLatestRates(): array
    {
        $cached = $this->cache->get('latest_rates');
        if ($cached !== null) {
            return $cached;
        }

        $rates = $this->repository->getLatestRates();

        if (empty($rates)) {
            $this->refreshRates();
            $rates = $this->repository->getLatestRates();
        }

        $this->cache->set('latest_rates', $rates, self::CACHE_TTL);

        return $rates;
    }

    public function getHistoricalRates(string $currencyCode): array
    {
        $cacheKey = 'history_' . strtoupper($currencyCode);
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $rates = $this->repository->getHistoricalRates(strtoupper($currencyCode));
        $this->cache->set($cacheKey, $rates, self::CACHE_TTL);

        return $rates;
    }

    public function getAvailableCurrencies(): array
    {
        $cached = $this->cache->get('available_currencies');
        if ($cached !== null) {
            return $cached;
        }

        $currencies = $this->repository->getAvailableCurrencies();
        $this->cache->set('available_currencies', $currencies, self::CACHE_TTL);

        return $currencies;
    }

    public function refreshRates(): void
    {
        $rows = $this->apiClient->fetchRates(30);
        $this->repository->bulkUpsert($rows);
        $this->cache->clear();
    }
}
