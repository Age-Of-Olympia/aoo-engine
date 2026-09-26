<?php

namespace App\View;

use Classes\View;

/**
 * Animated board images (water, lava, fire, a composed ground or mark…)
 * drawn as one layer per image instead of one animated image per cell.
 * One instance per board table, rendered where that table's rows end.
 *
 * An animated image inside the board SVG repaints every cell showing it on
 * every frame: hundreds of cells, each with its masks and the ground under
 * it. Here each group of cells sharing a tile, a turn and a phase is one
 * HTML layer whose background repeats the tile; an SVG mask cut from the
 * cells, with the board's edge fades and elbow halves, gives it the shape
 * the cells had.
 *
 * A composed tile that drifts or sways (its data-composer says how) is
 * frozen and slid by a CSS transform: painted once, moved by the
 * compositor, never repainted. Any other animation — GIF, animated WebP or
 * PNG, a flickering SVG — plays as the layer's background: one layer
 * repaints instead of hundreds of cells. Static tiles stay drawn per cell.
 */
final class AnimatedLayersView
{
    /**
     * Default positions per second of a sliding texture. Each step
     * recomposites the layer; 12 keeps a weak phone or a machine without
     * GPU close to idle, smooth motion costs about four times as much
     * there. The player overrides it per browser (map options, js/hud.js
     * applyAnimationQuality), from each move's duration and data-segments.
     */
    public const STEPS_PER_SECOND = 12;

    /** Pieces a sway is cut into: its easing, as straight segments. */
    private const SWAY_SEGMENTS = 8;

    /**
     * Default timing of a looping motion made of $segments keyframe
     * intervals: STEPS_PER_SECOND positions a second in all.
     */
    public static function steps(float $seconds, int $segments = 1): string
    {
        return 'steps('. max(1, (int) round($seconds * self::STEPS_PER_SECOND / $segments)) .')';
    }

    /** Native motion of each composer drift, in texture units. */
    private const DRIFT = ['drift_h' => [50, 0], 'drift_v' => [0, 50], 'drift_d' => [50, 50]];

    public function __construct(public readonly string $table = 'elements')
    {
    }

    /** @var array<string, int> image name => rank of its first row */
    private array $ranks = [];

    /** @var array<string, array{img: string, opacity: float, turn: int, shift: array{float, float}, cells: list<array{x: int, y: int, edge: int, clip: string}>}> */
    private array $groups = [];

    /**
     * Settings a composed SVG carries in data-composer, or null. Memoised per file.
     *
     * @return array<string, mixed>|null
     */
    public static function composerParams(string $img): ?array
    {
        static $params = [];

        if (!array_key_exists($img, $params)) {
            $params[$img] = null;
            if (str_ends_with($img, '.svg') && preg_match('/data-composer="([^"]*)"/', (string) @file_get_contents($img, false, null, 0, 4096), $m)) {
                $params[$img] = json_decode(html_entity_decode($m[1]), true) ?: null;
            }
        }

        return $params[$img];
    }

    /**
     * Whether an image is a sliding composed tile: only those become layers.
     * An image animating by itself (GIF, WebP, APNG, SMIL) stays one <image>
     * per cell — as a layer background, Chrome Android drops whatever the
     * board paints above it on some frames.
     */
    public static function slides(string $img): bool
    {
        return self::moves($img) !== [];
    }

    /**
     * One cell (or one elbow half) of an element's image. $format is the
     * rank of the image's format in the element's stack; $shift is the
     * phase offset along the texture's own axes, as the per-cell drawing
     * applied it.
     *
     * @param array{float, float} $shift
     */
    public function add(string $img, int $format, float $opacity, int $turn, array $shift, int $x, int $y, int $edge, string $clip): void
    {
        /* Layers stack as the cells did: images in the order of their
         * rows, then an element's formats, then an elbow's clipped half
         * over its whole one. */
        $rank = $this->ranks[pathinfo($img, PATHINFO_FILENAME)] ??= count($this->ranks);
        $key = sprintf('%04d|%d|%d|%s|%d|%s', $rank, $format, $clip !== '', $img, $turn, implode(',', $shift));
        $this->groups[$key] ??= ['img' => $img, 'opacity' => $opacity, 'turn' => $turn, 'shift' => $shift, 'cells' => []];
        $this->groups[$key]['cells'][] = ['x' => $x, 'y' => $y, 'edge' => $edge, 'clip' => $clip];
    }

    /**
     * The layers, their masks and their motion, wrapped in one group per
     * table even when empty: the HUD swaps that node whole after an action.
     */
    public function render(): string
    {
        $t = View::TILE_PX;
        $edges = [];
        $clips = [];
        $body = '';
        $css = '';

        ksort($this->groups);
        foreach (array_values($this->groups) as $i => $group) {
            $id = 'layer-'. $this->table .'-'. $i;
            $xs = array_column($group['cells'], 'x');
            $ys = array_column($group['cells'], 'y');
            [$left, $top] = [min($xs), min($ys)];
            [$w, $h] = [max($xs) + $t - $left, max($ys) + $t - $top];

            $shape = self::shapeUri($group['img']);
            $mask = '';
            foreach ($group['cells'] as $cell) {
                $edges[$cell['edge']] = $cell['edge'];
                $clips[$cell['clip']] = $cell['clip'];
                $fill = $shape
                    ? '<image href="'. $shape .'" width="'. $t .'" height="'. $t .'"'. ($group['turn'] ? ' transform="rotate('. $group['turn'] .' '. ($t / 2) .' '. ($t / 2) .')"' : '') .'/>'
                    : '<rect width="'. $t .'" height="'. $t .'" fill="#fff"/>';
                $mask .= '<svg x="'. ($cell['x'] - $left) .'" y="'. ($cell['y'] - $top) .'" width="'. $t .'" height="'. $t .'">'
                    . ($cell['edge'] ? '<g mask="url(#elem-edge-'. $cell['edge'] .')">' : '<g>')
                    . ($cell['clip'] ? '<g mask="url(#'. $cell['clip'] .')">' : '<g>')
                    . $fill .'</g></g></svg>';
            }

            /* The texture block overhangs the layer by a tile on every side,
             * so no step of a slide uncovers an edge. A turned block is a
             * square turned about a cell centre, wide enough to cover the
             * layer at any angle. Its corner stays on the board's grid, so
             * the turned texture lands on the grid too. */
            if ($group['turn']) {
                [$cx, $cy] = [$t / 2 + $t * intdiv($w, 2 * $t), $t / 2 + $t * intdiv($h, 2 * $t)];
                $r = $t * (int) ceil((hypot($w, $h) + $t) / $t) + $t / 2;
                $frame = 'left:'. ($cx - $r) .'px;top:'. ($cy - $r) .'px;width:'. (2 * $r) .'px;height:'. (2 * $r) .'px;transform:rotate('. $group['turn'] .'deg);';
            } else {
                $frame = 'left:0;top:0;width:'. $w .'px;height:'. $h .'px;';
            }
            $texture = 'left:-'. $t .'px;top:-'. $t .'px;right:-'. $t .'px;bottom:-'. $t .'px;'
                . 'background:url(\''. self::textureUri($group['img']) .'\') '. $group['shift'][0] .'px '. $group['shift'][1] .'px/'. $t .'px '. $t .'px;';

            $inner = '<div class="anim-layer-texture" style="'. $texture .'"></div>';
            foreach (array_reverse(self::moves($group['img']), true) as $n => $move) {
                $inner = '<div class="anim-layer-move" data-segments="'. $move['segments'] .'" style="animation-name:'. $id .'-'. $n .';animation-duration:'. $move['seconds'] .'s;'
                    . 'animation-timing-function:'. self::steps($move['seconds'], $move['segments']) .'">'. $inner .'</div>';
                $css .= '@keyframes '. $id .'-'. $n .'{'. $move['keyframes'] .'}';
            }

            $body .= '<mask id="'. $id .'" maskUnits="userSpaceOnUse" x="0" y="0" width="'. $w .'" height="'. $h .'">'. $mask .'</mask>'
                . '<svg x="'. $left .'" y="'. $top .'" width="'. $w .'" height="'. $h .'" overflow="hidden">'
                . '<foreignObject width="'. $w .'" height="'. $h .'" mask="url(#'. $id .')" pointer-events="none">'
                . '<div xmlns="http://www.w3.org/1999/xhtml" style="position:relative;width:'. $w .'px;height:'. $h .'px;overflow:hidden;opacity:'. $group['opacity'] .'">'
                . '<div class="anim-layer-frame" style="'. $frame .'">'. $inner .'</div></div>'
                . '</foreignObject></svg>';
        }

        return '<g id="anim-layers-'. $this->table .'" class="anim-layers">'
            . ($body === '' ? '' : View::elementEdgeDefs(array_values($edges)) .'<defs>'. View::elementHalfDefs(array_values($clips)) .'</defs>'
                . '<style>.anim-layer-frame,.anim-layer-move,.anim-layer-texture{position:absolute;max-width:none;max-height:none}'
                . '.anim-layer-move{inset:0;will-change:transform;animation-iteration-count:infinite}'
                . '@media (prefers-reduced-motion:reduce){.anim-layer-move{animation:none}}'. $css .'</style>'. $body)
            .'</g>';
    }

    /**
     * How a composed tile slides, in its own axes (the frame carries the
     * turn): one move for a drift, two for a sway (one per axis, out of
     * phase, nested so they add up). A drift is one straight keyframe
     * interval, a sway SWAY_SEGMENTS of them following its easing; the
     * timing function steps each interval. None for any other image: it
     * animates itself.
     *
     * @return list<array{seconds: float, keyframes: string, segments: int}>
     */
    private static function moves(string $img): array
    {
        $p = self::composerParams($img);
        $anim = $p['anim'] ?? '';
        $seconds = (float) ($p['speed'] ?? 0);
        if ($seconds <= 0 || (!isset(self::DRIFT[$anim]) && $anim !== 'sway')) {
            return [];
        }

        $swing = fn(float $f): float => (1 - cos(2 * M_PI * $f)) / 2;
        $sample = function (float $seconds, callable $at, int $n): array {
            $frames = '';
            for ($i = 0; $i <= $n; $i++) {
                [$x, $y] = $at($i / $n);
                $frames .= round(100 * $i / $n, 3) .'%{transform:translate('. round($x, 2) .'px,'. round($y, 2) .'px)}';
            }

            return ['seconds' => $seconds, 'keyframes' => $frames, 'segments' => $n];
        };

        if ($anim === 'sway') {
            return [
                $sample($seconds, fn(float $f): array => [5 * $swing($f), 0], self::SWAY_SEGMENTS),
                $sample(round($seconds * 1.3, 1), fn(float $f): array => [0, -3 * $swing($f)], self::SWAY_SEGMENTS),
            ];
        }
        [$dx, $dy] = self::DRIFT[$anim];

        return [$sample($seconds, fn(float $f): array => [$dx * $f, $dy * $f], 1)];
    }

    /**
     * What the layer repeats. A sliding composed tile is frozen: its
     * animations out, its own shape mask out (the layer's mask carries it,
     * fixed to the cell). Memoised.
     */
    private static function textureUri(string $img): string
    {
        static $uris = [];

        return $uris[$img] ??= (function () use ($img): string {
            $svg = preg_replace('#<animate(Transform)?\b[^>]*/>#', '', (string) file_get_contents($img));

            return 'data:image/svg+xml,'. rawurlencode(str_replace(' mask="url(#m)"', '', $svg));
        })();
    }

    /** A sliding composed tile's own shape (puddle, splash…) as a white image, or null. */
    private static function shapeUri(string $img): ?string
    {
        static $uris = [];

        if (!array_key_exists($img, $uris)) {
            $uris[$img] = preg_match('#<mask id="m">.*?</mask>#s', (string) file_get_contents($img), $m)
                ? 'data:image/svg+xml,'. rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="50" height="50"><defs>'
                    . $m[0] .'</defs><rect width="50" height="50" fill="#fff" mask="url(#m)"/></svg>')
                : null;
        }

        return $uris[$img];
    }
}
