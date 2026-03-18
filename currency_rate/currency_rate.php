<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/NbpApiClient.php';
require_once __DIR__ . '/classes/FileCache.php';
require_once __DIR__ . '/classes/RateRepository.php';
require_once __DIR__ . '/classes/RateService.php';

class Currency_Rate extends Module
{
    public function __construct()
    {
        $this->name = 'currency_rate';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Soft-Craft';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Currency Rate');
        $this->description = $this->l('Displays current and historical currency exchange rates from NBP API');
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->installSql()) {
            return false;
        }

        if (
            !$this->registerHook('displayProductAdditionalInfo')
            || !$this->registerHook('displayHeader')
        ) {
            return false;
        }

        if (!Configuration::get('CURRENCY_RATE_CRON_TOKEN')) {
            Configuration::updateValue('CURRENCY_RATE_CRON_TOKEN', bin2hex(random_bytes(16)));
        }

        try {
            $this->buildRateService()->refreshRates();
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'CurrencyRate install: initial rate fetch failed — ' . $e->getMessage(),
                2
            );
        }

        return true;
    }

    public function uninstall(): bool
    {
        $this->uninstallSql();
        $this->clearCacheFiles();
        Configuration::deleteByName('CURRENCY_RATE_CRON_TOKEN');
        Configuration::deleteByName('CURRENCY_RATE_DISPLAY_CODES');

        return parent::uninstall();
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        $product = $params['product'] ?? [];
        $idProduct = (int) ($product['id'] ?? $product['id_product'] ?? Tools::getValue('id_product'));

        if (!$idProduct) {
            return '';
        }

        try {
            $price = (float) Product::getPriceStatic($idProduct, false);
            $rates = $this->buildRateService()->getLatestRates();

            if (empty($rates)) {
                return '';
            }

            $converted = [];
            foreach ($rates as $r) {
                if ((float) $r['rate'] > 0) {
                    $converted[] = [
                        'code'   => $r['currency_code'],
                        'name'   => $r['currency_name'],
                        'amount' => round($price / (float) $r['rate'], 2),
                        'rate'   => $r['rate'],
                    ];
                }
            }

            $this->context->smarty->assign([
                'convertedPrices' => $converted,
                'basePricePln'    => $price,
                'ratesDate'       => $rates[0]['effective_date'] ?? '',
            ]);

            return $this->display(__FILE__, 'views/templates/hook/product_prices.tpl');
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'CurrencyRate hookDisplayProductAdditionalInfo: ' . $e->getMessage(),
                3
            );

            return '';
        }
    }

    public function hookDisplayHeader(): void
    {
        $controller = $this->context->controller;
        $controller->addCSS($this->_path . 'views/css/currency_rate.css');

        if (get_class($controller) === 'Currency_rateHistoryModuleFrontController') {
            $controller->registerJavascript(
                'currency-rate-chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
                ['server' => 'remote', 'position' => 'bottom', 'priority' => 200]
            );
        }
    }

    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitRefreshRates')) {
            try {
                $this->buildRateService()->refreshRates();
                Configuration::deleteByName('CURRENCY_RATE_LAST_ERROR');
                $output .= $this->displayConfirmation(
                    $this->l('Exchange rates refreshed successfully.')
                );
            } catch (Exception $e) {
                Configuration::updateValue(
                    'CURRENCY_RATE_LAST_ERROR',
                    date('Y-m-d H:i:s') . ': ' . $e->getMessage()
                );
                $output .= $this->displayError(
                    $this->l('Failed to refresh rates: ') . htmlspecialchars($e->getMessage())
                );
                PrestaShopLogger::addLog('CurrencyRate getContent: ' . $e->getMessage(), 3);
            }
        }

        $cronToken = Configuration::get('CURRENCY_RATE_CRON_TOKEN');
        $shopBaseUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__;
        $cronUrl = $shopBaseUrl . 'modules/currency_rate/cron/update_rates.php?token=' . urlencode($cronToken);

        $repo = new RateRepository();
        $lastFetched = $repo->getLastFetchedAt();

        return $output . $this->renderConfigPage($cronUrl, $lastFetched);
    }

    private function renderConfigPage(string $cronUrl, ?string $lastFetched): string
    {
        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-money"></i> ' . $this->l('Currency Rate Settings') . '</div>';
        $html .= '<div class="form-wrapper">';

        $lastError = Configuration::get('CURRENCY_RATE_LAST_ERROR');
        if ($lastError) {
            $html .= '<div class="alert alert-danger">';
            $html .= '<strong>' . $this->l('Last API error:') . '</strong> ' . htmlspecialchars($lastError);
            $html .= '</div>';
        }

        if ($lastFetched) {
            $html .= '<div class="alert alert-info">';
            $html .= $this->l('Last rate update:') . ' <strong>' . htmlspecialchars($lastFetched) . '</strong>';
            $html .= '</div>';
        } else {
            $html .= '<div class="alert alert-warning">' . $this->l('No exchange rate data found. Click "Refresh Rates Now" to fetch data from NBP API.') . '</div>';
        }

        $html .= '<div class="form-group">';
        $html .= '<label class="control-label col-lg-3">' . $this->l('Cron URL') . '</label>';
        $html .= '<div class="col-lg-9">';
        $html .= '<input type="text" class="form-control" readonly value="' . htmlspecialchars($cronUrl) . '">';
        $html .= '<p class="help-block">' . $this->l('Configure your server cron to call this URL daily, e.g.:') . '</p>';
        $html .= '<code>0 8 * * * curl -s "' . htmlspecialchars($cronUrl) . '" &gt; /dev/null</code>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<form method="post" style="margin-top:20px;">';
        $html .= '<button type="submit" name="submitRefreshRates" class="btn btn-primary">';
        $html .= '<i class="icon-refresh"></i> ' . $this->l('Refresh Rates Now');
        $html .= '</button>';
        $html .= '</form>';

        $html .= '</div></div>';

        return $html;
    }

    private function buildRateService(): RateService
    {
        return new RateService(
            new NbpApiClient(),
            new FileCache(__DIR__ . '/cache/'),
            new RateRepository()
        );
    }

    private function installSql(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (!Db::getInstance()->execute($statement)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallSql(): void
    {
        $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            Db::getInstance()->execute($statement);
        }
    }

    private function clearCacheFiles(): void
    {
        foreach (glob(__DIR__ . '/cache/*.cache') ?: [] as $file) {
            unlink($file);
        }
    }
}
