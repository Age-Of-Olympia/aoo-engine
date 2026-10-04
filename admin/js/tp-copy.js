/* Copies a cell's coordinates in the console's « tp <nom> » form
 * (button.tp-copy[data-tp]). Standalone: the admin console has no jQuery,
 * and navigator.clipboard is missing over plain http, hence the fallback
 * on a temporary selection. */
document.addEventListener("click", function (event) {
    var button = event.target.closest(".tp-copy");
    if (!button) { return; }

    var value = button.getAttribute("data-tp");
    var done = function () {
        var code = button.querySelector("code");
        var before = code.textContent;
        code.textContent = "copié !";
        setTimeout(function () { code.textContent = before; }, 1200);
    };

    var fallback = function () {
        var field = document.createElement("textarea");
        field.value = value;
        field.style.position = "fixed";
        field.style.opacity = "0";
        document.body.appendChild(field);
        field.select();
        try { document.execCommand("copy"); done(); } finally { field.remove(); }
    };

    /* The modern clipboard can be refused (permission, insecure page):
     * without the fallback the click would silently do nothing. */
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value).then(done, fallback);
        return;
    }

    fallback();
});
