/*
 * Search, facets and paging for any admin table, client side.
 *
 *   <table data-admin-list data-page-size="50" data-facets="type:Type,status:Statut">
 *     <tr data-type="PNJ" data-status="Actif">…
 *
 * The search matches the row text; each facet becomes a <select> whose
 * options are the distinct values found on the rows (the value IS the label).
 */
document.addEventListener('DOMContentLoaded', function () {
    // A popover panel filled on first opening: the page ships a URL, not the rows.
    document.querySelectorAll('details [data-lazy-src]').forEach(function (panel) {
        panel.closest('details').addEventListener('toggle', function () {
            var url = panel.getAttribute('data-lazy-src');
            if (!this.open || !url) {
                return;
            }
            panel.removeAttribute('data-lazy-src');
            fetch(url).then(function (r) { return r.text(); }).then(function (html) { panel.innerHTML = html; });
        });
    });

    document.querySelectorAll('table[data-admin-list]').forEach(function (table) {
        var tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }
        var rows = Array.prototype.slice.call(tbody.rows);
        var pageSize = parseInt(table.getAttribute('data-page-size'), 10) || 50;
        var page = 0;
        var query = '';

        var toolbar = document.createElement('div');
        toolbar.className = 'admin-list-toolbar';
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-sm';
        search.placeholder = table.getAttribute('data-search-placeholder') || 'Rechercher…';
        toolbar.appendChild(search);

        var facets = (table.getAttribute('data-facets') || '').split(',').filter(Boolean).map(function (spec) {
            var parts = spec.split(':');
            var attr = 'data-' + parts[0];
            var values = {};
            rows.forEach(function (row) {
                var value = row.getAttribute(attr);
                if (value) {
                    values[value] = (values[value] || 0) + 1;
                }
            });
            var select = document.createElement('select');
            select.className = 'form-control form-control-sm';
            select.appendChild(new Option(parts[1] || parts[0], ''));
            Object.keys(values).sort().forEach(function (value) {
                select.appendChild(new Option(value + ' (' + values[value] + ')', value));
            });
            select.addEventListener('change', function () { page = 0; render(); });
            toolbar.appendChild(select);
            return { attr: attr, select: select };
        });

        var counter = document.createElement('small');
        counter.className = 'text-muted';
        toolbar.appendChild(counter);
        table.parentNode.insertBefore(toolbar, table);

        var pager = document.createElement('div');
        pager.className = 'admin-list-pager';
        var prev = pagerButton('← Précédent');
        var info = document.createElement('span');
        info.className = 'text-muted';
        var next = pagerButton('Suivant →');
        pager.appendChild(prev);
        pager.appendChild(info);
        pager.appendChild(next);
        table.parentNode.insertBefore(pager, table.nextSibling);

        function pagerButton(label) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-secondary';
            button.textContent = label;
            return button;
        }

        function matches(row) {
            if (query !== '' && row.textContent.toLowerCase().indexOf(query) === -1) {
                return false;
            }
            return facets.every(function (facet) {
                return facet.select.value === '' || row.getAttribute(facet.attr) === facet.select.value;
            });
        }

        function render() {
            var visible = rows.filter(matches);
            var pages = Math.max(1, Math.ceil(visible.length / pageSize));
            page = Math.min(page, pages - 1);

            rows.forEach(function (row) { row.style.display = 'none'; });
            visible.slice(page * pageSize, (page + 1) * pageSize)
                .forEach(function (row) { row.style.display = ''; });

            counter.textContent = visible.length === rows.length
                ? visible.length + ' ligne(s)'
                : visible.length + ' / ' + rows.length + ' ligne(s)';
            info.textContent = 'page ' + (page + 1) + ' / ' + pages;
            prev.disabled = page === 0;
            next.disabled = page >= pages - 1;
            pager.style.display = pages > 1 ? '' : 'none';
        }

        var debounce = null;
        search.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                query = search.value.trim().toLowerCase();
                page = 0;
                render();
            }, 150);
        });
        prev.addEventListener('click', function () { page--; render(); });
        next.addEventListener('click', function () { page++; render(); });

        render();
    });
});
