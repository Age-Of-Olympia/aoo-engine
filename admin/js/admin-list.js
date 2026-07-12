/*
 * Recherche + pagination côté client pour les tableaux du panneau admin.
 *
 * Opt-in : <table data-admin-list data-page-size="50"> — le script injecte
 * une barre d'outils (champ de recherche + compteur) au-dessus du tableau et
 * une pagination en dessous. Le filtre porte sur le texte complet de chaque
 * ligne. Aucun rechargement : les lignes sont masquées/affichées.
 *
 * Chargé globalement par admin/layout.php : inactif sans tableau marqué.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('table[data-admin-list]').forEach(function (table) {
        var tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }
        var rows = Array.prototype.slice.call(tbody.rows);
        var pageSize = parseInt(table.getAttribute('data-page-size'), 10) || 50;
        var page = 0;
        var query = '';

        /* Barre d'outils : recherche + compteur */
        var toolbar = document.createElement('div');
        toolbar.className = 'admin-list-toolbar';
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-sm';
        search.placeholder = 'Rechercher… (nom, problème, badge)';
        var counter = document.createElement('small');
        counter.className = 'text-muted';
        toolbar.appendChild(search);
        toolbar.appendChild(counter);
        table.parentNode.insertBefore(toolbar, table);

        /* Pagination */
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
            return query === '' || row.textContent.toLowerCase().indexOf(query) !== -1;
        }

        function render() {
            var visible = rows.filter(matches);
            var pages = Math.max(1, Math.ceil(visible.length / pageSize));
            page = Math.min(page, pages - 1);

            rows.forEach(function (row) { row.style.display = 'none'; });
            visible.slice(page * pageSize, (page + 1) * pageSize)
                .forEach(function (row) { row.style.display = ''; });

            counter.textContent = query === ''
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
