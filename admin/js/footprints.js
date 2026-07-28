/*
 * Décors en plusieurs morceaux : régler la forme et le passage à la souris.
 *
 * La page servait un tableau de chiffres — une grille de numéros de morceaux,
 * un champ JSON pour les rôles. On ne voyait pas le décor, et « déclarer » ne
 * voulait rien dire. Ici on voit la figure à l'échelle de la carte, on clique
 * une case pour la faire barrer le chemin, on fait glisser un morceau pour
 * corriger la figure.
 *
 * L'état part du champ caché `figure`, y revient après chaque geste, et c'est
 * lui que le POST emporte : le formulaire reste ordinaire, sans requête
 * asynchrone ni seconde copie de la figure à garder d'accord.
 */
(function () {
    'use strict';

    var CELL = 50;

    /**
     * Le damier d'une carte, redessiné après chaque geste.
     *
     * Redessiner entièrement plutôt que retoucher : une figure fait au plus
     * quelques dizaines de cases, et un seul chemin de rendu ne peut pas
     * diverger de l'état.
     */
    function Board(root, form) {
        this.root = root;
        this.form = form;
        this.field = form.querySelector('.fp-figure');
        this.state = JSON.parse(this.field.value);
        this.dragging = null;

        /* Les décalages arrivent indexés par morceau, en coordonnées de jeu
         * (y vers le haut). La grille, elle, se lit de haut en bas : on garde
         * les décalages tels quels et on convertit au dessin, pour que ce qui
         * est enregistré reste ce que le reste du moteur attend. */
        this.render();
    }

    /** Les bornes de la figure, d'où se déduit la taille du damier. */
    Board.prototype.bounds = function () {
        var xs = [];
        var ys = [];

        Object.keys(this.state.offsets).forEach(function (piece) {
            xs.push(this.state.offsets[piece][0]);
            ys.push(this.state.offsets[piece][1]);
        }, this);

        if (!xs.length) {
            return { minX: 0, maxX: 0, minY: 0, maxY: 0 };
        }

        return {
            minX: Math.min.apply(null, xs),
            maxX: Math.max.apply(null, xs),
            minY: Math.min.apply(null, ys),
            maxY: Math.max.apply(null, ys)
        };
    };

    Board.prototype.render = function () {
        var bounds = this.bounds();

        /* Une rangée de cases vides tout autour : sans elle, on ne pourrait
         * jamais agrandir une figure, faute d'endroit où déposer un morceau. */
        var minX = bounds.minX - 1;
        var maxX = bounds.maxX + 1;
        var minY = bounds.minY - 1;
        var maxY = bounds.maxY + 1;

        var w = maxX - minX + 1;
        var h = maxY - minY + 1;

        var byCell = {};

        Object.keys(this.state.offsets).forEach(function (piece) {
            var offset = this.state.offsets[piece];
            byCell[offset[0] + ',' + offset[1]] = piece;
        }, this);

        this.root.textContent = '';
        this.root.style.gridTemplateColumns = 'repeat(' + w + ', ' + CELL + 'px)';

        /* De haut en bas : y décroît quand on descend. */
        for (var y = maxY; y >= minY; y--) {
            for (var x = minX; x <= maxX; x++) {
                this.root.appendChild(this.cell(x, y, byCell[x + ',' + y]));
            }
        }

        this.sync();
    };

    /** Une case : vide, ou porteuse d'un morceau. */
    Board.prototype.cell = function (x, y, piece) {
        var cell = document.createElement(piece === undefined ? 'div' : 'button');

        cell.className = 'fp-cell';
        cell.dataset.x = x;
        cell.dataset.y = y;

        if (piece === undefined) {
            cell.addEventListener('dragover', this.onDragOver.bind(this));
            cell.addEventListener('dragleave', this.onDragLeave.bind(this));
            cell.addEventListener('drop', this.onDrop.bind(this));

            return cell;
        }

        var blocks = this.state.blocked.indexOf(Number(piece)) !== -1;

        cell.type = 'button';
        cell.className += ' fp-cell--filled' + (blocks ? ' fp-cell--blocks' : '');
        cell.dataset.piece = piece;
        cell.draggable = true;
        cell.title = 'Morceau ' + piece + ' — '
            + (blocks ? 'barre le chemin' : 'on peut passer')
            + '. Cliquer pour changer, faire glisser pour déplacer.';
        cell.setAttribute('aria-pressed', blocks ? 'true' : 'false');

        var url = this.state.pieces[piece];

        if (url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            img.loading = 'lazy';
            cell.appendChild(img);
        }

        cell.addEventListener('click', this.onToggle.bind(this, Number(piece)));
        cell.addEventListener('dragstart', this.onDragStart.bind(this, Number(piece)));
        cell.addEventListener('dragend', this.onDragEnd.bind(this));

        return cell;
    };

    /** Cliquer une case : elle barre le chemin, ou plus. */
    Board.prototype.onToggle = function (piece, event) {
        event.preventDefault();

        var at = this.state.blocked.indexOf(piece);

        if (at === -1) {
            this.state.blocked.push(piece);
        } else {
            this.state.blocked.splice(at, 1);
        }

        this.render();
    };

    Board.prototype.onDragStart = function (piece, event) {
        this.dragging = piece;
        event.currentTarget.classList.add('fp-cell--dragging');

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            /* Firefox n'émet pas de `drop` sans charge utile. */
            event.dataTransfer.setData('text/plain', String(piece));
        }
    };

    Board.prototype.onDragEnd = function (event) {
        this.dragging = null;
        event.currentTarget.classList.remove('fp-cell--dragging');
    };

    Board.prototype.onDragOver = function (event) {
        if (this.dragging === null) {
            return;
        }

        event.preventDefault();
        event.currentTarget.classList.add('fp-cell--drop');
    };

    Board.prototype.onDragLeave = function (event) {
        event.currentTarget.classList.remove('fp-cell--drop');
    };

    /** Déposer un morceau sur une case vide : la figure change de forme. */
    Board.prototype.onDrop = function (event) {
        event.preventDefault();
        event.currentTarget.classList.remove('fp-cell--drop');

        if (this.dragging === null) {
            return;
        }

        this.state.offsets[this.dragging] = [
            Number(event.currentTarget.dataset.x),
            Number(event.currentTarget.dataset.y)
        ];

        this.dragging = null;
        this.render();
    };

    /**
     * Recale la figure sur son premier morceau et remplit le champ caché.
     *
     * Les décalages sont relatifs au premier morceau — c'est la convention du
     * catalogue, et la pose s'en sert pour faire tomber le morceau choisi sur
     * la case cliquée. Après un déplacement, ils ne le sont plus : on ramène.
     */
    Board.prototype.sync = function () {
        var pieces = Object.keys(this.state.offsets).map(Number).sort(function (a, b) {
            return a - b;
        });

        if (!pieces.length) {
            return;
        }

        var anchor = this.state.offsets[pieces[0]];
        var offsets = {};
        var xs = [];
        var ys = [];

        pieces.forEach(function (piece) {
            var dx = this.state.offsets[piece][0] - anchor[0];
            var dy = this.state.offsets[piece][1] - anchor[1];

            offsets[piece] = [dx, dy];
            xs.push(dx);
            ys.push(dy);
        }, this);

        this.field.value = JSON.stringify({
            family: this.state.family,
            w: Math.max.apply(null, xs) - Math.min.apply(null, xs) + 1,
            h: Math.max.apply(null, ys) - Math.min.apply(null, ys) + 1,
            offsets: offsets,
            blocked: this.state.blocked.slice().sort(function (a, b) { return a - b; })
        });

        var summary = this.form.querySelector('.fp-summary');

        if (summary) {
            var blocking = this.state.blocked.length;

            summary.textContent = pieces.length + ' morceau' + (pieces.length > 1 ? 'x' : '')
                + ' · ' + (blocking === 0
                    ? 'on passe partout'
                    : blocking + ' case' + (blocking > 1 ? 's' : '') + ' qui barre'
                        + (blocking > 1 ? 'nt' : '') + ' le chemin');
        }
    };

    /** Filtre et recherche : une liste de cent trente décors se traverse mal. */
    function wireFilters(cards) {
        var search = document.getElementById('fp-search');
        var buttons = Array.prototype.slice.call(document.querySelectorAll('.fp-filters [data-filter]'));
        var filter = 'all';

        function apply() {
            var needle = (search && search.value || '').trim().toLowerCase();

            cards.forEach(function (card) {
                var matchesState = filter === 'all' || card.dataset.state === filter;
                var matchesName = !needle || card.dataset.family.toLowerCase().indexOf(needle) !== -1;

                card.hidden = !(matchesState && matchesName);
            });
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                filter = button.dataset.filter;
                buttons.forEach(function (other) { other.classList.remove('active'); });
                button.classList.add('active');
                apply();
            });
        });

        if (search) {
            search.addEventListener('input', apply);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var cards = Array.prototype.slice.call(document.querySelectorAll('.fp-card'));

        cards.forEach(function (card) {
            var form = card.querySelector('.fp-form');
            var board = card.querySelector('.fp-board');

            if (board && form && form.querySelector('.fp-figure')) {
                new Board(board, form);
            }
        });

        wireFilters(cards);
    });
})();
