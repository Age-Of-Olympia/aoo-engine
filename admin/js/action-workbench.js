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
/* Fold / unfold the actions list to an icon rail (persisted) for more space. */
(function () {
    var wb = document.querySelector('.wb');
    var btn = document.getElementById('wb-fold');
    if (!wb || !btn) { return; }
    if (localStorage.getItem('wb-folded') === '1') { wb.classList.add('wb--folded'); }
    btn.addEventListener('click', function () {
        var folded = wb.classList.toggle('wb--folded');
        localStorage.setItem('wb-folded', folded ? '1' : '0');
    });
})();
/* Icon picker : extrait vers admin/js/icon-picker.js (partagé avec les
   effets) — chargé par la page à côté de ce fichier. */
(function () {
    var modal = document.getElementById('sim-result-modal');
    if (!modal) { return; }
    function close() { modal.classList.remove('is-open'); }
    modal.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) { close(); } });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) { close(); }
    });
    var reroll = document.getElementById('sim-reroll');
    if (reroll) {
        reroll.addEventListener('click', function () {
            var form = document.querySelector('.sim-form');
            if (form) { form.submit(); }
        });
    }
})();
/* Passive conditions picker: reveal the panel matching the chosen mode
   (weapon / category / raw / none) within each .wb-cond block. */
document.querySelectorAll('.wb-cond .wb-cond-mode').forEach(function (select) {
    var cond = select.closest('.wb-cond');
    select.addEventListener('change', function () {
        cond.querySelectorAll('.wb-cond-panel').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-cond-mode') !== select.value;
        });
    });
});
/* Live filter for the (long) weapon list: hide options that don't match, then
   hide any group left with no visible option. Checked-first ordering is CSS. */
document.querySelectorAll('.wb-cond-search').forEach(function (input) {
    var panel = input.closest('.wb-cond-panel');
    input.addEventListener('input', function () {
        var q = input.value.toLowerCase();
        panel.querySelectorAll('.wb-cond-opt').forEach(function (opt) {
            opt.style.display = opt.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
        });
        panel.querySelectorAll('.wb-cond-group').forEach(function (group) {
            var visible = Array.prototype.some.call(group.querySelectorAll('.wb-cond-opt'), function (o) {
                return o.style.display !== 'none';
            });
            group.style.display = visible ? '' : 'none';
        });
    });
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
