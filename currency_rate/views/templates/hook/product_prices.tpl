{if isset($convertedPrices) && $convertedPrices|count > 0}
<div class="currency-rate-product-prices">

    <h6 class="cr-pp-title">
        {l s='Price in other currencies' mod='currency_rate'}
    </h6>

    <div class="table-responsive">
        <table class="table table-sm table-bordered cr-pp-table">
            <thead>
                <tr>
                    <th scope="col">{l s='Currency' mod='currency_rate'}</th>
                    <th scope="col">{l s='Code' mod='currency_rate'}</th>
                    <th scope="col" class="text-right">{l s='Approx. price' mod='currency_rate'}</th>
                    <th scope="col" class="text-right cr-pp-rate-col">{l s='NBP rate' mod='currency_rate'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $convertedPrices as $item}
                <tr>
                    <td class="text-muted">{$item.name|escape:'html'}</td>
                    <td><strong>{$item.code|escape:'html'}</strong></td>
                    <td class="text-right">
                        {$item.amount|string_format:'%.2f'}&nbsp;{$item.code|escape:'html'}
                    </td>
                    <td class="text-right cr-pp-rate-col text-muted">
                        {$item.rate|string_format:'%.4f'}
                    </td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>

    <p class="cr-pp-footnote">
        {if $ratesDate}
            {l s='Rates as of %s' sprintf=[$ratesDate] mod='currency_rate'}
            &bull;
        {/if}
        {l s='Source: NBP (National Bank of Poland), Table A' mod='currency_rate'}
    </p>

</div>
{/if}
