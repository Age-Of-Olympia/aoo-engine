/**
 * Drop a red × on map tiles the player cannot walk on (walls,
 * foregrounds, other players). Used by:
 *   - the tutorial (scoped to padded highlight zones for the
 *     "where can I walk" spotlight),
 *   - the player display option `showBlockedTiles` for regular play.
 *
 * Public API:
 *   window.drawBlockedTileMarkers(zones, className)
 *     zones:    Array<{left,top,right,bottom}> | null
 *               null = mark every blocked tile on screen
 *     className: CSS class to put on each marker so callers can
 *                clear their own without touching others.
 *   window.clearBlockedTileMarkers(className)
 */
(function() {

    function collectBlockedCoords(playerCoords) {
        const set = new Set();
        $('image[data-table="walls"], image[data-table="foregrounds"]').each(function() {
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

    window.drawBlockedTileMarkers = function(zones, className) {
        window.clearBlockedTileMarkers(className);

        const playerCoords = $('#current-player-avatar').attr('data-coords');
        const blocked = collectBlockedCoords(playerCoords);
        if (blocked.size === 0) {
            return;
        }

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
            const $marker = $('<div class="' + className + '">×</div>');
            $marker.css({
                top: cr.top + 'px',
                left: cr.left + 'px',
                width: cr.width + 'px',
                height: cr.height + 'px'
            });
            $('body').append($marker);
        });
    };

    window.clearBlockedTileMarkers = function(className) {
        $('.' + className).remove();
    };
})();
