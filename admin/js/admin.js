/*
 * Admin shell behaviour, loaded on every admin page (deferred: the DOM is
 * parsed when this runs).
 *
 *  - sidebar sections and groups fold, the choice remembered per browser;
 *  - below 900px the sidebar is a drawer opened from the top bar;
 *  - every .table scrolls in its own box instead of widening the page;
 *  - data-confirm="message" on a form, a submit button or a link asks first;
 *  - data-ask="question" on a form or a submit button asks yes or no, puts
 *    1 or 0 in the field named by data-ask-field and sends the form either
 *    way (closing the dialog sends nothing); data-ask-if-changed="x,y" asks
 *    only when one of those fields changed;
 *  - data-autosubmit on a field submits its form when it changes;
 *  - data-check-all on a header checkbox ticks the visible rows of its table;
 *  - the × of a flash message closes it;
 *  - « Noms techniques » in the sidebar shows the codes behind labels.
 */
(function () {
    var body = document.body;

    var nav = document.querySelector('.vertical-nav');
    if (nav) {
        var KEY = 'adminNavOpen';
        var open = {};
        try { open = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { /* private mode */ }
        nav.querySelectorAll('.nav-section, .nav-group').forEach(function (box) {
            var kind = box.classList.contains('nav-section') ? 'section' : 'group';
            var title = box.querySelector('.nav-' + kind + '-title');
            var name = kind + ' ' + title.textContent.trim();
            if (open[name]) {
                box.classList.add('nav-' + kind + '-open');
            }
            title.setAttribute('aria-expanded', box.classList.contains('nav-' + kind + '-open'));
            title.addEventListener('click', function () {
                open[name] = box.classList.toggle('nav-' + kind + '-open');
                title.setAttribute('aria-expanded', open[name]);
                try { localStorage.setItem(KEY, JSON.stringify(open)); } catch (e) { /* private mode */ }
            });
        });
    }

    // « Noms techniques »: codes beside their labels, remembered per browser.
    var techSwitch = document.querySelector('[data-tech-names]');
    if (techSwitch) {
        techSwitch.checked = body.classList.contains('show-tech');
        techSwitch.addEventListener('change', function () {
            body.classList.toggle('show-tech', techSwitch.checked);
            try { localStorage.setItem('adminTechNames', techSwitch.checked ? '1' : '0'); } catch (e) { /* private mode */ }
        });
    }

    var toggle = document.querySelector('.admin-nav-toggle');
    function setDrawer(isOpen) {
        body.classList.toggle('admin-nav-open', isOpen);
        if (toggle) {
            toggle.setAttribute('aria-expanded', isOpen);
        }
    }
    if (toggle) {
        toggle.addEventListener('click', function () { setDrawer(!body.classList.contains('admin-nav-open')); });
    }
    var scrim = document.querySelector('.admin-scrim');
    if (scrim) {
        scrim.addEventListener('click', function () { setDrawer(false); });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && body.classList.contains('admin-nav-open')) {
            setDrawer(false);
        }
    });

    document.querySelectorAll('.admin-main table.table').forEach(function (table) {
        if (table.parentElement.closest('.table-responsive')) {
            return;
        }
        var box = document.createElement('div');
        box.className = 'table-responsive';
        table.parentNode.insertBefore(box, table);
        box.appendChild(table);
    });

    document.addEventListener('change', function (event) {
        var field = event.target;
        if (field.hasAttribute('data-autosubmit') && field.form) {
            field.form.requestSubmit();
        }
        // Rows hidden by the search or the paging stay as they are.
        if (field.hasAttribute('data-check-all')) {
            field.closest('table').querySelectorAll('tbody tr').forEach(function (row) {
                var box = row.querySelector('input[type="checkbox"]');
                if (box && row.offsetParent !== null) {
                    box.checked = field.checked;
                }
            });
        }
    });

    /* A confirmed form is resubmitted with this flag, so the second submit
     * event goes through. */
    var confirmed = false;
    function ask(message) {
        return window.aooConfirm ? window.aooConfirm(message) : Promise.resolve(window.confirm(message));
    }
    document.addEventListener('submit', function (event) {
        if (confirmed) {
            confirmed = false;
            return;
        }
        var form = event.target;
        var submitter = event.submitter || null;
        var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (!message) {
            return;
        }
        event.preventDefault();
        ask(message).then(function (ok) {
            if (ok) {
                confirmed = true;
                form.requestSubmit(submitter);
            }
        });
    }, true);

    function changed(field) {
        if (field.tagName === 'SELECT') {
            var initial = Array.prototype.find.call(field.options, function (option) { return option.defaultSelected; });
            return field.value !== (initial ? initial.value : field.options[0] && field.options[0].value);
        }
        return field.value !== field.defaultValue;
    }
    var answered = false;
    document.addEventListener('submit', function (event) {
        if (answered) {
            answered = false;
            return;
        }
        var form = event.target;
        var submitter = event.submitter || null;
        var source = submitter && submitter.hasAttribute('data-ask') ? submitter : form;
        var question = source.getAttribute('data-ask');
        if (!question) {
            return;
        }
        var watched = (source.getAttribute('data-ask-if-changed') || '').split(',').filter(Boolean);
        if (watched.length && !watched.some(function (name) { return form.elements[name] && changed(form.elements[name]); })) {
            return;
        }
        event.preventDefault();
        var choice = window.aooChoose
            ? window.aooChoose(question, [{ value: '1', label: 'Oui' }, { value: '0', label: 'Non' }], '1')
            : Promise.resolve(window.confirm(question) ? '1' : '0');
        choice.then(function (value) {
            if (value === null) {
                return;
            }
            var name = source.getAttribute('data-ask-field');
            var field = form.elements[name];
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                form.appendChild(field);
            }
            field.value = value;
            answered = true;
            form.requestSubmit(submitter);
        });
    }, true);

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-confirm]');
        if (link) {
            event.preventDefault();
            ask(link.getAttribute('data-confirm')).then(function (ok) {
                if (ok) {
                    window.location.href = link.href;
                }
            });
            return;
        }
        var close = event.target.closest('.alert .close, .alert .btn-close');
        if (close) {
            close.closest('.alert').remove();
        }
    });
})();
