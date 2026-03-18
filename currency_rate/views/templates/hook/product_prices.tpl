{if isset($convertedPrices) && $convertedPrices|count > 0}
<div class="currency-rate-product-prices">

    <h6 class="cr-pp-title">
        {l s='Price in other currencies' mod='currency_rate'}
    </h6>

    <div class="cr-pp-search mb-2">
        <input type="text" id="cr-pp-filter" class="form-control form-control-sm"
               placeholder="{l s='Filter currency...' mod='currency_rate'}">
    </div>

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
            <tbody id="cr-pp-tbody">
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

    <nav id="cr-pp-pagination" aria-label="{l s='Pagination' mod='currency_rate'}"></nav>

    <p class="cr-pp-footnote">
        {if $ratesDate}
            {l s='Rates as of %s' sprintf=[$ratesDate] mod='currency_rate'}
            &bull;
        {/if}
        {l s='Source: NBP (National Bank of Poland), Table A' mod='currency_rate'}
    </p>

    <script>
    (function () {
        var PER_PAGE   = 10;
        var input      = document.getElementById('cr-pp-filter');
        var tbody      = document.getElementById('cr-pp-tbody');
        var pagination = document.getElementById('cr-pp-pagination');
        if (!input || !tbody || !pagination) { return; }

        var allRows    = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var currentPage = 1;

        function getVisible() {
            var q = input.value.toLowerCase().trim();
            return allRows.filter(function (row) {
                return !q || row.textContent.toLowerCase().indexOf(q) !== -1;
            });
        }

        function render() {
            var visible    = getVisible();
            var totalPages = Math.max(1, Math.ceil(visible.length / PER_PAGE));
            if (currentPage > totalPages) { currentPage = 1; }

            var start = (currentPage - 1) * PER_PAGE;
            var end   = start + PER_PAGE;

            allRows.forEach(function (row) { row.style.display = 'none'; });
            visible.slice(start, end).forEach(function (row) { row.style.display = ''; });

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            if (totalPages <= 1) { pagination.innerHTML = ''; return; }

            var items = '<ul class="pagination pagination-sm justify-content-center mt-2 mb-0">';

            items += '<li class="page-item ' + (currentPage <= 1 ? 'disabled' : '') + '">'
                   + '<a class="page-link" data-p="' + (currentPage - 1) + '">&laquo;</a></li>';

            for (var p = 1; p <= totalPages; p++) {
                items += '<li class="page-item ' + (p === currentPage ? 'active' : '') + '">'
                       + '<a class="page-link" data-p="' + p + '">' + p + '</a></li>';
            }

            items += '<li class="page-item ' + (currentPage >= totalPages ? 'disabled' : '') + '">'
                   + '<a class="page-link" data-p="' + (currentPage + 1) + '">&raquo;</a></li>';

            items += '</ul>';
            pagination.innerHTML = items;

            pagination.querySelectorAll('[data-p]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    var p = parseInt(this.getAttribute('data-p'));
                    if (p >= 1 && p <= totalPages) {
                        currentPage = p;
                        render();
                    }
                });
            });
        }

        input.addEventListener('input', function () {
            currentPage = 1;
            render();
        });

        render();
    })();
    </script>

</div>
{/if}
