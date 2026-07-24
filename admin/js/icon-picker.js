/* Icon picker (IconFieldView) : grille filtrable des icônes RPG-Awesome
 * listées dans window.WB_ICONS, émis par la page. Partagé entre le
 * workbench des actions et les effets — styles dans css/icon-picker.css. */
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
        var swatch = e.target.closest && e.target.closest('.wb-color-swatch');
        if (swatch) {
            var fld = swatch.closest('.wb-icon-field');
            var inp = swatch.querySelector('input');
            var prev = fld && fld.querySelector('.wb-icon-preview i');
            if (prev) { prev.style.color = inp.getAttribute('data-hex') || ''; }
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
