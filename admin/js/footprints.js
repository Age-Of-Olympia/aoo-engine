/*
 * Editing a scenery family's shape and passability with the mouse.
 *
 * State starts from the hidden `figure` field, goes back into it after every
 * gesture, and is what the form POSTs — no asynchronous request, no second
 * copy of the figure to keep in step.
 */
(function () {
    'use strict';

    var CELL = 50;

    /* Redrawn whole on every gesture: a figure is a few dozen cells at most,
     * and a single render path cannot drift from the state. */
    function Board(root, form) {
        this.root = root;
        this.form = form;
        this.field = form.querySelector('.fp-figure');
        this.state = JSON.parse(this.field.value);
        this.dragging = null;

        /* Offsets stay in game coordinates (y upwards); the conversion to
         * screen rows happens at draw time only. */
        this.render();
    }

    /* Figure bounds, from which the board size follows. */
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

        /* One empty row all around, so there is somewhere to drop a piece. */
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

        /* Top to bottom: y decreases going down. */
        for (var y = maxY; y >= minY; y--) {
            for (var x = minX; x <= maxX; x++) {
                this.root.appendChild(this.cell(x, y, byCell[x + ',' + y]));
            }
        }

        this.sync();
    };

    /* A cell: empty, or carrying a piece. */
    Board.prototype.cell = function (x, y, piece) {
        var cell = document.createElement(piece === undefined ? 'div' : 'button');

        cell.className = 'fp-cell';
        cell.dataset.x = x;
        cell.dataset.y = y;

        if (piece === undefined) {
            cell.title = 'Clic : ajout de la case à la figure.';
            cell.addEventListener('click', this.onAdd.bind(this, x, y));
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
            + (blocks ? 'passage bloqué' : 'passage libre')
            + '. Clic : changer. Glisser : déplacer. Clic droit : retirer.';
        cell.setAttribute('aria-pressed', blocks ? 'true' : 'false');

        var url = this.state.pieces[piece];

        if (url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            img.loading = 'lazy';
            cell.appendChild(img);
        } else if (this.state.sheet) {
            /* One picture for the whole figure: the board stretches it over
             * the box, so each cell shows its own slice of it. */
            var bounds = this.bounds();
            var col = x - bounds.minX;
            var row = bounds.maxY - y;

            cell.style.backgroundImage = 'url("' + this.state.sheet + '")';
            cell.style.backgroundRepeat = 'no-repeat';
            cell.style.backgroundSize = ((bounds.maxX - bounds.minX + 1) * CELL) + 'px '
                + ((bounds.maxY - bounds.minY + 1) * CELL) + 'px';
            cell.style.backgroundPosition = (-col * CELL) + 'px ' + (-row * CELL) + 'px';
        }

        cell.addEventListener('click', this.onToggle.bind(this, Number(piece)));
        cell.addEventListener('contextmenu', this.onRemove.bind(this, Number(piece)));
        cell.addEventListener('dragstart', this.onDragStart.bind(this, Number(piece)));
        cell.addEventListener('dragend', this.onDragEnd.bind(this));

        return cell;
    };

    /* Clicking a cell toggles whether it blocks the way. */
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

    /* An empty cell clicked joins the figure, as the next piece. */
    Board.prototype.onAdd = function (x, y, event) {
        event.preventDefault();

        var next = Math.max.apply(null, [-1].concat(Object.keys(this.state.offsets).map(Number))) + 1;

        this.state.offsets[next] = [x, y];
        this.render();
    };

    /* A figure keeps at least one cell: the last one cannot go. */
    Board.prototype.onRemove = function (piece, event) {
        event.preventDefault();

        if (Object.keys(this.state.offsets).length < 2) {
            return;
        }

        delete this.state.offsets[piece];

        var at = this.state.blocked.indexOf(piece);

        if (at !== -1) {
            this.state.blocked.splice(at, 1);
        }

        this.render();
    };

    Board.prototype.onDragStart = function (piece, event) {
        this.dragging = piece;
        event.currentTarget.classList.add('fp-cell--dragging');

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            /* Firefox emits no `drop` without a payload. */
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

    /* Dropping a piece on an empty cell reshapes the figure. */
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

    /* Re-anchor on the first piece — the catalogue convention — and write
     * the hidden field. A drag breaks that relation, so it is restored here. */
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

            summary.textContent = pieces.length + ' case' + (pieces.length > 1 ? 's' : '')
                + ' · ' + (blocking === 0
                    ? 'on passe partout'
                    : blocking + ' case' + (blocking > 1 ? 's' : '') + ' qui barre'
                        + (blocking > 1 ? 'nt' : '') + ' le chemin');
        }
    };

    /* Filter and search: a list of a hundred and thirty families reads badly. */
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

        var checkAll = document.querySelector('[data-check-all]');

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                checkAll.form.querySelectorAll('input[name="ids[]"]').forEach(function (box) {
                    box.checked = checkAll.checked;
                });
            });
        }
    });
})();
