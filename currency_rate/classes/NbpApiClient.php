<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class NbpApiClient
{
    private const BASE_URL = 'https://api.nbp.pl/api';
    private const TIMEOUT  = 15;

    /**
     * Pobiera kursy z ostatnich $days dni roboczych (Tabela A NBP).
     * Zwraca spłaszczoną listę wierszy: code, name, rate, date.
     *
     * @throws RuntimeException
     */
    public function fetchRates(int $days = 30): array
    {
        $url  = sprintf('%s/exchangerates/tables/A/last/%d/?format=json', self::BASE_URL, $days);
        $raw  = $this->get($url);
        $rows = [];

        foreach ($raw as $table) {
            $date = $table['effectiveDate'] ?? null;

            if (!$date || empty($table['rates'])) {
                continue;
            }

            foreach ($table['rates'] as $entry) {
                $rows[] = [
                    'code' => (string) $entry['code'],
                    'name' => (string) $entry['currency'],
                    'rate' => (float)  $entry['mid'],
                    'date' => $date,
                ];
            }
        }

        return $rows;
    }

    private function get(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_USERAGENT      => 'PrestaShop/CurrencyRateModule/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('NBP API cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException(
                sprintf('NBP API returned HTTP %d for URL: %s', $httpCode, $url)
            );
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('NBP API JSON parse error: ' . json_last_error_msg());
        }

        return $data;
    }
}
