/**
 * Drop a red × on map tiles the player cannot walk on. Mirrors the
 * server's movement rules from go.php:
 *   - map_walls on the destination cell,
 *   - other players on the destination cell,
 *   - 'forbidden' triggers on the destination cell (the trigger row
 *     itself isn't in the DOM in normal play, so View.php stamps
 *     data-blocked="forbidden" on the .case for these cells).
 * Foregrounds are decorative only — they don't block movement, even
 * if they look like walls (a forbidden trigger underneath them is
 * what historically did the blocking).
 *
 * Used by:
 *   - the tutorial (scoped to padded highlight zones for the
 *     "where can I walk" spotlight),
 *   - the player display option `showBlockedTiles` for regular play,
 *   - the tiled editor's "Cases bloquantes" toggle.
 *
 * Public API:
 *   window.drawBlockedTileMarkers(zones, className, container)
 *     zones:     Array<{left,top,right,bottom}> | null
 *                null = mark every blocked tile on screen
 *     className: CSS class to put on each marker so callers can
 *                clear their own without touching others.
 *     container: jQuery collection (optional). When provided,
 *                markers are appended inside it with
 *                position:absolute (container-relative) so they
 *                scroll with the container natively and get
 *                clipped at its overflow edge — required when the
 *                map is bigger than the viewport (tiled editor).
 *                The container MUST be a positioned ancestor
 *                (position:relative/absolute/fixed) for the
 *                container-relative offsets to anchor correctly.
 *                Default: position:fixed in <body> (tutorial /
 *                regular play, where the map fits the viewport).
 *   window.clearBlockedTileMarkers(className)
 */
(function() {

    function collectBlockedCoords(playerCoords) {
        const set = new Set();
        $('image[data-table="walls"]').each(function() {
            const c = this.getAttribute('data-coords');
            if (c) set.add(c);
        });
        $('.case[data-blocked="forbidden"]').each(function() {
            const c = this.getAttribute('data-coords');
            if (c) set.add(c);
        });
        $('image[data-table="players"]').each(function() {
            const c = this.getAttribute('data-coords');
            if (c && c !== playerCoords) set.add(c);
        });
        return set;
    }

    function tileCenterInZones(cr, zones) {
        if (!zones || zones.length === 0) {
            return true;
        }
        const cx = cr.left + cr.width / 2;
        const cy = cr.top + cr.height / 2;
        for (const z of zones) {
            if (cx >= z.left && cx <= z.right && cy >= z.top && cy <= z.bottom) {
                return true;
            }
        }
        return false;
    }

    window.drawBlockedTileMarkers = function(zones, className, container) {
        window.clearBlockedTileMarkers(className);

        const playerCoords = $('#current-player-avatar').attr('data-coords');
        const blocked = collectBlockedCoords(playerCoords);
        if (blocked.size === 0) {
            return;
        }

        const useAbsolute = container && container.length > 0;
        const containerEl = useAbsolute ? container[0] : null;
        const containerRect = useAbsolute ? containerEl.getBoundingClientRect() : null;
        const scrollLeft = useAbsolute ? containerEl.scrollLeft : 0;
        const scrollTop = useAbsolute ? containerEl.scrollTop : 0;

        $('.case').each(function() {
            const $case = $(this);
            const coords = $case.attr('data-coords');
            if (!coords || coords === playerCoords) {
                return;
            }
            if (!blocked.has(coords)) {
                return;
            }
            const cr = this.getBoundingClientRect();
            if (cr.width === 0 || cr.height === 0) {
                return;
            }
            if (!tileCenterInZones(cr, zones)) {
                return;
            }
            const $marker = $('<div class="' + className + '">⛔</div>');
            if (useAbsolute) {
                /* Convert viewport rect → container-relative coords.
                   getBoundingClientRect() is viewport-relative, so we
                   subtract the container's viewport origin and add
                   back its current scroll offset. */
                $marker.css({
                    position: 'absolute',
                    top: (cr.top - containerRect.top + scrollTop) + 'px',
                    left: (cr.left - containerRect.left + scrollLeft) + 'px',
                    width: cr.width + 'px',
                    height: cr.height + 'px'
                });
                container.append($marker);
            } else {
                $marker.css({
                    top: cr.top + 'px',
                    left: cr.left + 'px',
                    width: cr.width + 'px',
                    height: cr.height + 'px'
                });
                $('body').append($marker);
            }
        });
    };

    window.clearBlockedTileMarkers = function(className) {
        $('.' + className).remove();
    };
})();
