<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'currency_rate/classes/NbpApiClient.php';
require_once _PS_MODULE_DIR_ . 'currency_rate/classes/FileCache.php';
require_once _PS_MODULE_DIR_ . 'currency_rate/classes/RateRepository.php';
require_once _PS_MODULE_DIR_ . 'currency_rate/classes/RateService.php';

class Currency_rateHistoryModuleFrontController extends ModuleFrontController
{
    public $page_name = 'module-currency_rate-history';

    public function initContent(): void
    {
        parent::initContent();

        $service    = $this->buildService();
        $currencies = $service->getAvailableCurrencies();

        $rawCode      = (string) Tools::getValue('currency', $currencies[0]['currency_code'] ?? 'USD');
        $selectedCode = strtoupper(preg_replace('/[^A-Za-z]/', '', $rawCode));

        try {
            $history      = $service->getHistoricalRates($selectedCode);
            $errorMessage = null;
        } catch (Exception $e) {
            $history      = [];
            $errorMessage = $this->trans(
                'Unable to load exchange rates. Please try again later.',
                [],
                'Modules.Currencyrate.Front'
            );
            PrestaShopLogger::addLog('CurrencyRate HistoryController: ' . $e->getMessage(), 3);
        }

        $sort = in_array(Tools::getValue('sort'), ['date', 'rate'], true)
            ? Tools::getValue('sort')
            : 'date';
        $dir = Tools::getValue('dir') === 'DESC' ? 'DESC' : 'ASC';

        if (!empty($history)) {
            usort($history, function (array $a, array $b) use ($sort, $dir): int {
                $cmp = $sort === 'rate'
                    ? ((float) $a['rate'] <=> (float) $b['rate'])
                    : strcmp((string) $a['effective_date'], (string) $b['effective_date']);
                return $dir === 'DESC' ? -$cmp : $cmp;
            });
        }

        // wykres zawsze w kolejności chronologicznej niezależnie od sortowania tabeli
        $chronological = $history;
        if ($sort !== 'date' || $dir !== 'ASC') {
            usort($chronological, fn($a, $b) => strcmp($a['effective_date'], $b['effective_date']));
        }

        $historyJson = json_encode(
            array_map(
                fn(array $r) => ['date' => $r['effective_date'], 'rate' => (float) $r['rate']],
                $chronological
            )
        );

        $this->context->smarty->assign([
            'currencies'   => $currencies,
            'selectedCode' => $selectedCode,
            'history'      => $history,
            'historyJson'  => $historyJson,
            'errorMessage' => $errorMessage,
            'baseUrl'      => $this->context->link->getModuleLink('currency_rate', 'history'),
            'sort'         => $sort,
            'dir'          => $dir,
        ]);

        $this->setTemplate('module:currency_rate/views/templates/front/history.tpl');
    }

    private function buildService(): RateService
    {
        return new RateService(
            new NbpApiClient(),
            new FileCache(_PS_MODULE_DIR_ . 'currency_rate/cache/'),
            new RateRepository()
        );
    }
}
