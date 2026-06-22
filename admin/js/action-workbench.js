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
/* Icon picker: pick an action icon from the available RPG-Awesome glyphs
   (window.WB_ICONS). The grid is built lazily on first open. */
(function () {
    var ICONS = window.WB_ICONS || [];
    function buildGrid(grid) {
        if (grid.childElementCount) { return; }
        var frag = document.createDocumentFragment();
        ICONS.forEach(function (name) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'wb-icon-opt';
            b.setAttribute('data-icon', name);
            b.title = name;
            b.innerHTML = '<i class="ra ' + name + '"></i>';
            frag.appendChild(b);
        });
        grid.appendChild(frag);
    }
    function closeAll(except) {
        document.querySelectorAll('.wb-icon-field.is-open').forEach(function (f) {
            if (f !== except) { f.classList.remove('is-open'); f.querySelector('.wb-icon-pop').hidden = true; }
        });
    }
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest && e.target.closest('.wb-icon-trigger');
        if (trigger) {
            var field = trigger.closest('.wb-icon-field');
            var pop = field.querySelector('.wb-icon-pop');
            var willOpen = pop.hidden;
            closeAll(field);
            if (willOpen) {
                buildGrid(field.querySelector('.wb-icon-grid'));
                pop.hidden = false;
                field.classList.add('is-open');
                field.querySelector('.wb-icon-search').focus();
            } else {
                pop.hidden = true;
                field.classList.remove('is-open');
            }
            return;
        }
        var opt = e.target.closest && e.target.closest('.wb-icon-opt');
        if (opt) {
            var picked = opt.closest('.wb-icon-field');
            var name = opt.getAttribute('data-icon');
            picked.querySelector('.wb-icon-input').value = name;
            picked.querySelector('.wb-icon-preview i').className = 'ra ' + name;
            picked.querySelector('.wb-icon-label').textContent = name;
            picked.querySelector('.wb-icon-pop').hidden = true;
            picked.classList.remove('is-open');
            return;
        }
        if (!e.target.closest || !e.target.closest('.wb-icon-field')) { closeAll(null); }
    });
    document.addEventListener('input', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('wb-icon-search')) {
            var q = e.target.value.toLowerCase();
            e.target.closest('.wb-icon-pop').querySelectorAll('.wb-icon-opt').forEach(function (o) {
                o.style.display = o.getAttribute('data-icon').indexOf(q) === -1 ? 'none' : '';
            });
        }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(null); } });
})();
/* Result modal: auto-open after a run, close to a floating re-open button, and
   reroll (re-submit the sim form) without leaving the modal. */
(function () {
    var modal = document.getElementById('sim-result-modal');
    if (!modal) { return; }
    var reopen = document.getElementById('sim-reopen');
    function close() { modal.classList.remove('is-open'); if (reopen) { reopen.hidden = false; } }
    function open() { modal.classList.add('is-open'); if (reopen) { reopen.hidden = true; } }
    modal.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) { close(); } });
    if (reopen) { reopen.addEventListener('click', open); }
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
