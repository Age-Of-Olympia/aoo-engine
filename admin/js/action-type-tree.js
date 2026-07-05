/* Collapse/expand for the action-type tree rail. Delegated so it works for any
   .tt-tree on the page; toggles a class on the clicked node's <li>. */
(function () {
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.tt-toggle');
        if (!toggle || toggle.classList.contains('tt-toggle--empty')) {
            return;
        }
        var node = toggle.closest('.tt-node');
        if (!node) {
            return;
        }
        var collapsed = node.classList.toggle('tt-collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
})();
