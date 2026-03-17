CREATE TABLE IF NOT EXISTS `PREFIX_currency_rate_history` (
    `id_rate`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `currency_code`  VARCHAR(3)    NOT NULL,
    `currency_name`  VARCHAR(64)   NOT NULL,
    `rate`           DECIMAL(10,6) NOT NULL COMMENT 'PLN per 1 unit of foreign currency (NBP mid rate)',
    `effective_date` DATE          NOT NULL,
    `fetched_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_rate`),
    UNIQUE KEY `uq_code_date`       (`currency_code`, `effective_date`),
    KEY       `idx_effective_date`  (`effective_date`),
    KEY       `idx_currency_code`   (`currency_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
