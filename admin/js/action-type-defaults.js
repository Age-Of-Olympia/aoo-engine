/* Tab switching for the type-defaults editor. Remembers the last active tab in
   sessionStorage so it survives the save -> redirect round-trip. */
(function () {
    'use strict';
    var KEY = 'aoo.typeDefaults.tab';
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.wb-tabbar .nav-link'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.wb-tabpanel'));
    if (!tabs.length) {
        return;
    }

    function activate(index) {
        tabs.forEach(function (tab) {
            tab.classList.toggle('active', tab.getAttribute('data-tab') === String(index));
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-tab') !== String(index);
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var index = tab.getAttribute('data-tab');
            activate(index);
            try { sessionStorage.setItem(KEY, index); } catch (e) { /* ignore */ }
        });
    });

    var saved = null;
    try { saved = sessionStorage.getItem(KEY); } catch (e) { /* ignore */ }
    if (saved !== null && document.querySelector('.wb-tabbar .nav-link[data-tab="' + saved + '"]')) {
        activate(saved);
    }
})();
