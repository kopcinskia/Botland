{extends file='page.tpl'}

{block name='page_title'}
    {l s='Historical Exchange Rates (NBP)' mod='currency_rate'}
{/block}

{block name='page_content'}
<div class="currency-rate-history">

    <p class="text-muted mb-4">
        {l s='Mid-market rates published by the National Bank of Poland (Tabela A). Last 30 working days.' mod='currency_rate'}
    </p>

    {if $errorMessage}
        <div class="alert alert-danger" role="alert">
            {$errorMessage|escape:'html'}
        </div>

    {elseif empty($currencies)}
        <div class="alert alert-warning" role="alert">
            {l s='No exchange rate data is available yet. Please wait for the daily cron to run, or refresh manually from the Back-Office.' mod='currency_rate'}
        </div>

    {else}

        <form method="get" action="{$baseUrl|escape:'html'}" class="cr-selector-form mb-4">
            <div class="form-row align-items-end">
                <div class="col-auto">
                    <label for="cr-currency-select" class="col-form-label font-weight-bold">
                        {l s='Currency:' mod='currency_rate'}
                    </label>
                </div>
                <div class="col-auto">
                    <select name="currency" id="cr-currency-select" class="form-control">
                        {foreach $currencies as $c}
                            <option value="{$c.currency_code|escape:'html'}"
                                {if $c.currency_code == $selectedCode}selected{/if}>
                                {$c.currency_code|escape:'html'} &mdash; {$c.currency_name|escape:'html'}
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        {l s='Show' mod='currency_rate'}
                    </button>
                </div>
            </div>
        </form>

        {if !empty($history)}

            <div class="card cr-chart-card mb-4">
                <div class="card-body">
                    <h5 class="card-title">
                        {l s='Rate trend:' mod='currency_rate'}
                        <strong>{$selectedCode|escape:'html'}</strong> / PLN
                    </h5>
                    <canvas id="cr-rate-chart" height="90" aria-label="{l s='Exchange rate chart' mod='currency_rate'}"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header cr-card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        {l s='Daily rates:' mod='currency_rate'}
                        <strong>{$selectedCode|escape:'html'}</strong>
                    </h5>
                    <span class="badge badge-secondary">
                        {$history|count} {l s='days' mod='currency_rate'}
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover cr-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">
                                    <a href="{$baseUrl|escape:'html'}?currency={$selectedCode|escape:'html'}&sort=date&dir={if $sort == 'date' && $dir == 'ASC'}DESC{else}ASC{/if}">
                                        {l s='Date' mod='currency_rate'}
                                        {if $sort == 'date'} {if $dir == 'ASC'}&uarr;{else}&darr;{/if}{/if}
                                    </a>
                                </th>
                                <th scope="col">{l s='Currency' mod='currency_rate'}</th>
                                <th scope="col" class="text-right">
                                    <a href="{$baseUrl|escape:'html'}?currency={$selectedCode|escape:'html'}&sort=rate&dir={if $sort == 'rate' && $dir == 'ASC'}DESC{else}ASC{/if}">
                                        {l s='Rate (PLN)' mod='currency_rate'}
                                        {if $sort == 'rate'} {if $dir == 'ASC'}&uarr;{else}&darr;{/if}{/if}
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $history as $row}
                                <tr>
                                    <td>{$row.effective_date|escape:'html'}</td>
                                    <td class="text-muted">{$row.currency_name|escape:'html'}</td>
                                    <td class="text-right">
                                        <strong>{$row.rate|string_format:'%.4f'}</strong>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>

            {if $totalPages > 1}
            <nav class="mt-3" aria-label="{l s='Pagination' mod='currency_rate'}">
                <ul class="pagination justify-content-center">
                    <li class="page-item {if $currentPage <= 1}disabled{/if}">
                        <a class="page-link" href="{$baseUrl|escape:'html'}?currency={$selectedCode|escape:'html'}&sort={$sort|escape:'html'}&dir={$dir|escape:'html'}&page={$currentPage - 1}">&laquo;</a>
                    </li>
                    {for $p = 1 to $totalPages}
                        <li class="page-item {if $p == $currentPage}active{/if}">
                            <a class="page-link" href="{$baseUrl|escape:'html'}?currency={$selectedCode|escape:'html'}&sort={$sort|escape:'html'}&dir={$dir|escape:'html'}&page={$p}">{$p}</a>
                        </li>
                    {/for}
                    <li class="page-item {if $currentPage >= $totalPages}disabled{/if}">
                        <a class="page-link" href="{$baseUrl|escape:'html'}?currency={$selectedCode|escape:'html'}&sort={$sort|escape:'html'}&dir={$dir|escape:'html'}&page={$currentPage + 1}">&raquo;</a>
                    </li>
                </ul>
            </nav>
            {/if}

            <script>
            window.addEventListener('load', function () {
                var data = {$historyJson nofilter};
                var ctx  = document.getElementById('cr-rate-chart');
                if (!ctx || typeof Chart === 'undefined' || !data.length) { return; }

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(function (d) { return d.date; }),
                        datasets: [{
                            label: '{$selectedCode|escape:'javascript'} / PLN',
                            data:  data.map(function (d) { return d.rate; }),
                            borderColor:     '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.07)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ctx.parsed.y.toFixed(4) + ' PLN';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { ticks: { maxTicksLimit: 10 } },
                            y: { beginAtZero: false }
                        }
                    }
                });
            });
            </script>

        {else}
            <div class="alert alert-info" role="alert">
                {l s='No data found for the selected currency. Try another currency or wait for the next rate update.' mod='currency_rate'}
            </div>
        {/if}

    {/if}

</div>
{/block}
