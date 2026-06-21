/* Action workbench client behaviour: list filter, raw key->value editor,
   Configurer/Simuler tab switching. Extracted from admin/action-workbench.php. */
/* Live client-side filter of the actions list. */
(function () {
    var search = document.getElementById('wb-search');
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase();
            document.querySelectorAll('#wb-list .wb-item').forEach(function (el) {
                el.style.display = el.getAttribute('data-search').indexOf(q) === -1 ? 'none' : '';
            });
        });
    }
})();
/* Raw key→value editor: add a row, or remove one. Rows use a unique running
   index so PHP keeps each (k, v) pair together regardless of deletions. */
var wbRawSeq = 0;
document.querySelectorAll('.wb-raw-add').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var editor = btn.closest('.wb-raw-editor');
        var rows = editor.querySelector('.wb-raw-rows');
        var prefix = editor.getAttribute('data-raw-prefix');
        var i = 'n' + (++wbRawSeq);
        var row = document.createElement('div');
        row.className = 'wb-raw-row';
        row.innerHTML = '<input class="form-control wb-raw-k" name="' + prefix + '[' + i + '][k]" placeholder="clé" autocomplete="off">'
            + '<input class="form-control wb-raw-v" name="' + prefix + '[' + i + '][v]" placeholder="valeur" autocomplete="off">'
            + '<button type="button" class="wb-raw-del" title="Retirer">×</button>';
        rows.appendChild(row);
    });
});
document.addEventListener('click', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('wb-raw-del')) {
        var row = e.target.closest('.wb-raw-row');
        if (row) { row.remove(); }
    }
});
/* Configurer / Simuler tab switching. */
document.querySelectorAll('.wb-tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var tab = btn.getAttribute('data-tab');
        document.querySelectorAll('.wb-tab-btn').forEach(function (b) { b.classList.toggle('active', b === btn); });
        document.querySelectorAll('.wb-tab').forEach(function (p) { p.hidden = p.getAttribute('data-tab') !== tab; });
        /* Keep the tab in the URL so a refresh stays on it. */
        var url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    });
});
