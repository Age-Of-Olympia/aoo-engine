/**
 * Drop a red × on map tiles the player cannot walk on.
 *
 * Ce fichier n'a plus de règle à lui. Il en avait une, qui MIMAIT celle
 * du serveur en relisant les calques dessinés — une image de ressource,
 * une image de joueur — et qui pouvait donc s'en écarter : elle comptait
 * les structures traversables (une table) que `js/view.js` écartait, et
 * ne savait rien des plans où les personnages sont cachés.
 *
 * Le serveur pose désormais `data-blocked` sur chaque case refusée au
 * pas, avec le verdict même de `go.php`
 * (App\Service\Map\TileOccupancyService). Ici on ne fait que le lire.
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

        const $blocked = $('.case[data-blocked]');
        if ($blocked.length === 0) {
            return;
        }

        const useAbsolute = container && container.length > 0;
        const containerEl = useAbsolute ? container[0] : null;
        const containerRect = useAbsolute ? containerEl.getBoundingClientRect() : null;
        const scrollLeft = useAbsolute ? containerEl.scrollLeft : 0;
        const scrollTop = useAbsolute ? containerEl.scrollTop : 0;

        $blocked.each(function() {
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
