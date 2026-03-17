<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class RateRepository
{
    private const TABLE = 'currency_rate_history';

    public function bulkUpsert(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $db    = Db::getInstance();
        $table = _DB_PREFIX_ . self::TABLE;

        foreach ($rows as $row) {
            $db->execute(
                'INSERT INTO `' . $table . '`
                    (`currency_code`, `currency_name`, `rate`, `effective_date`, `fetched_at`)
                 VALUES
                    (\'' . pSQL($row['code']) . '\',
                     \'' . pSQL($row['name']) . '\',
                     '  . (float) $row['rate'] . ',
                     \'' . pSQL($row['date']) . '\',
                     NOW())
                 ON DUPLICATE KEY UPDATE
                     `rate`          = VALUES(`rate`),
                     `currency_name` = VALUES(`currency_name`),
                     `fetched_at`    = NOW()'
            );
        }
    }

    public function getLatestRates(): array
    {
        $table  = _DB_PREFIX_ . self::TABLE;
        $result = Db::getInstance()->executeS(
            'SELECT r.`currency_code`, r.`currency_name`, r.`rate`, r.`effective_date`
             FROM `' . $table . '` r
             INNER JOIN (
                 SELECT `currency_code`, MAX(`effective_date`) AS `max_date`
                 FROM `' . $table . '`
                 GROUP BY `currency_code`
             ) latest
               ON r.`currency_code` = latest.`currency_code`
              AND r.`effective_date` = latest.`max_date`
             ORDER BY r.`currency_code` ASC'
        );

        return $result ?: [];
    }

    public function getHistoricalRates(string $code, int $days = 30): array
    {
        $table  = _DB_PREFIX_ . self::TABLE;
        $result = Db::getInstance()->executeS(
            'SELECT `currency_code`, `currency_name`, `rate`, `effective_date`
             FROM `' . $table . '`
             WHERE `currency_code` = \'' . pSQL(strtoupper($code)) . '\'
               AND `effective_date` >= DATE_SUB(CURDATE(), INTERVAL ' . (int) $days . ' DAY)
             ORDER BY `effective_date` ASC'
        );

        return $result ?: [];
    }

    public function getAvailableCurrencies(): array
    {
        $table  = _DB_PREFIX_ . self::TABLE;
        $result = Db::getInstance()->executeS(
            'SELECT DISTINCT `currency_code`, `currency_name`
             FROM `' . $table . '`
             ORDER BY `currency_code` ASC'
        );

        return $result ?: [];
    }

    public function getLastFetchedAt(): ?string
    {
        $value = Db::getInstance()->getValue(
            'SELECT MAX(`fetched_at`) FROM `' . _DB_PREFIX_ . self::TABLE . '`'
        );

        return $value ?: null;
    }
}
