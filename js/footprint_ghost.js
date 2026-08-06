/* Fantôme d'emprise PARTAGÉ — le même dessin pour le choix de case de
   construction (build_picker.js) et pour le pinceau bâtiments de l'éditeur
   (tiled.js) : les cases de la figure prennent un liseré vert quand tout
   est libre, rouge sinon, et le sprite s'étire sur l'union des cases.

   La validité reste l'affaire de l'appelant (portée du bâtisseur, règles
   serveur…) : ici on ne fait que voir la figure sous le curseur. */
window.FootprintGhost = (function(){

    var styleInjected = false;

    function injectStyle(){
        if(styleInjected){ return; }
        styleInjected = true;
        var style = document.createElement('style');
        style.id = 'footprint-ghost-style';
        style.textContent =
            '.build-ghost-ok{outline:3px solid rgba(40,167,69,.85);outline-offset:-3px;}' +
            '.build-ghost-bad{outline:3px solid rgba(220,53,69,.85);outline-offset:-3px;}' +
            '.footprint-ghost-img{position:fixed;pointer-events:none;opacity:.55;z-index:99990;display:none;}';
        document.head.appendChild(style);
    }

    function make(opts){
        injectStyle();
        var footprint = (opts.footprint && opts.footprint.length) ? opts.footprint : [[0, 0]];
        var img = null;
        if(opts.imgSrc){
            img = document.createElement('img');
            img.className = 'footprint-ghost-img';
            img.alt = '';
            img.src = opts.imgSrc;
            document.body.appendChild(img);
        }

        /* La case cliquée est l'ORIGINE, les autres décalages la suivent. */
        function cellsAt(x, y){
            return footprint.map(function(off){
                return document.querySelector('.case[data-coords="' + (x + off[0]) + ',' + (y + off[1]) + '"]');
            });
        }

        function clear(){
            document.querySelectorAll('.build-ghost-ok, .build-ghost-bad').forEach(function(el){
                el.classList.remove('build-ghost-ok');
                el.classList.remove('build-ghost-bad');
            });
            if(img){ img.style.display = 'none'; }
        }

        /* Peint la figure à l'origine (x, y) et renvoie « toutes les cases
           existent et sont libres ». Le sprite est mesuré sur les rects réels
           des cases : l'orientation du damier n'entre jamais en jeu. */
        function preview(x, y){
            clear();
            var cells = cellsAt(x, y);
            var allFree = cells.every(function(el){ return el && !el.hasAttribute('data-blocked'); });
            cells.forEach(function(el){
                if(el){ el.classList.add(allFree ? 'build-ghost-ok' : 'build-ghost-bad'); }
            });
            if(img && cells.some(function(el){ return !!el; })){
                var left = Infinity, top = Infinity, right = -Infinity, bottom = -Infinity;
                cells.forEach(function(el){
                    if(!el){ return; }
                    var r = el.getBoundingClientRect();
                    left = Math.min(left, r.left);
                    top = Math.min(top, r.top);
                    right = Math.max(right, r.right);
                    bottom = Math.max(bottom, r.bottom);
                });
                img.style.left = left + 'px';
                img.style.top = top + 'px';
                img.style.width = (right - left) + 'px';
                img.style.height = (bottom - top) + 'px';
                img.style.display = 'block';
            }
            return allFree;
        }

        function destroy(){
            clear();
            if(img && img.parentNode){ img.parentNode.removeChild(img); }
        }

        return { cellsAt: cellsAt, preview: preview, clear: clear, destroy: destroy };
    }

    return { make: make };
})();
