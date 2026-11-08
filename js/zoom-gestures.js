/* Pinch and Ctrl+wheel on el drive a zoom: the HUD board's (js/hud.js),
 * the maps' (load_map.php) and the Tiled editor's (tiled.php). A wheel
 * notch is one step, the same as the + / - buttons. */
window.zoomGestures = function (el, getZoom, setZoom, step) {
    var pinch = null;
    var rafPending = false;

    function dist(touches) {
        var dx = touches[0].clientX - touches[1].clientX;
        var dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy) || 1;
    }

    el.addEventListener('touchstart', function (e) {
        if (e.touches.length === 2) {
            pinch = { d: dist(e.touches), z: getZoom() };
        }
    }, { passive: true });

    el.addEventListener('touchmove', function (e) {
        if (!pinch || e.touches.length !== 2) {
            return;
        }
        e.preventDefault();
        var target = pinch.z * dist(e.touches) / pinch.d;
        if (!rafPending) {
            rafPending = true;
            requestAnimationFrame(function () {
                rafPending = false;
                setZoom(target);
            });
        }
    }, { passive: false });

    el.addEventListener('touchend', function (e) {
        if (e.touches.length < 2) {
            pinch = null;
        }
    }, { passive: true });

    el.addEventListener('touchcancel', function () {
        pinch = null;
    }, { passive: true });

    /* Ctrl + wheel: one step in place of the browser's PAGE zoom; not
     * passive, otherwise preventDefault is ignored. */
    el.addEventListener('wheel', function (e) {
        if (!e.ctrlKey) {
            return;
        }
        e.preventDefault();
        setZoom(e.deltaY < 0 ? getZoom() * step : getZoom() / step);
    }, { passive: false });
};
