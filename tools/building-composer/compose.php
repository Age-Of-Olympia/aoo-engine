<?php
/**
 * Building composer - assembles game building sprites from forms x facades x roofs.
 *
 * The engine owns the geometry: dimetric projection (diamond footprint), visible
 * planes, lighting, ground skirt, downscale and tile cropping. Surfaces are flat
 * rectangular textures, either procedural placeholders or PNG swatches painted by
 * an artist over the generated templates (see `blueprint`). Textures are
 * affine-mapped onto the planes, so the artist never draws in perspective.
 *
 * Runs inside the devcontainer (PHP + GD):
 *   docker exec PHP-AOO4-Local php tools/building-composer/compose.php <command>
 *
 * Commands:
 *   list
 *   blueprint all|<form>          -> blueprints/ + parts/templates/
 *   build <form> [options]       -> out/<name>.png + out/<name>_NN.png tiles
 *   sheet                        -> samples/sheet.png (demo grid)
 *
 * Build options:
 *   --facade stone|plaster|wood|colombage     procedural facade (default stone)
 *   --roof tiles|thatch|slate|flat            procedural roof material (default tiles)
 *   --shape gable|hip|flat                    roof shape (default: the form's)
 *   --facade-img <png> --roof-img <png>       artist swatches instead of procedural
 *   --mirror                                  flip orientation
 *   --seed <int>                              procedural variation
 *   --name <basename>                         output name (default composed)
 */

const TILE = 50;        // final px per map cell
const S = 4;            // working scale factor
const ISO = 0.45;       // diamond height / width ratio
const EAVE = 0.16;      // roof overhang, in tiles
const MARGIN_X = 2 * S; // canvas padding left/right, on top of the eave allowance
const MARGIN_TOP = 6 * S;

// form => [footprint w, footprint d, canvas tiles wide, canvas tiles tall,
//          storeys, storey height final px, roof height final px, roof shape]
const FORMS = [
    'hutte_1x1'   => [1, 1, 1, 1, 1, 13, 11, 'gable'],
    'longere_2x1' => [2, 1, 2, 2, 1, 16, 13, 'gable'],
    'maison_2x2'  => [2, 2, 2, 2, 2, 19, 16, 'hip'],
    'tour_1x1'    => [1, 1, 1, 2, 3, 20, 14, 'hip'],
    'halle_2x2'   => [2, 2, 2, 2, 1, 24, 20, 'gable'],
];

const FACADES = ['stone', 'plaster', 'wood', 'colombage', 'columns', 'darkstone', 'logs', 'swamp'];
const ROOFS = ['tiles', 'thatch', 'slate', 'flat'];
const DOORS = ['simple', 'double', 'arche', 'aucune'];
const DOOR_POS = ['gauche' => 0.22, 'centre' => 0.5, 'droite' => 0.78];
const WINDOWS = ['simple', 'arche', 'hautes', 'aucune'];

// plane light factors, sun high on the viewer's left
const SHADE_LEFT = 0.95;
const SHADE_RIGHT = 0.68;
const SHADE_SLOPE_L = 1.0;
const SHADE_SLOPE_R = 0.66;
const SHADE_FLAT = 0.92;

/** Screen mapping for one form: p(x, y, z) with x, y in tiles, z in working px. */
class Projection
{
    public int $width;
    public int $height;
    public float $u;
    public float $ox;
    public float $oy;

    public function __construct(int $w, int $d, int $cols, int $rows)
    {
        $this->width = $cols * TILE * S;
        $this->height = $rows * TILE * S;
        // eave corners stick out on both axes at once, hence 4x, not 2x
        $this->u = ($this->width - 2 * MARGIN_X) / ($w + $d + 4 * EAVE);
        $bottom = $this->height - 4 * S;
        $this->oy = $bottom - ($w + $d) * $this->u * ISO;
        $this->ox = $this->width / 2 - ($w - $d) * $this->u / 2;
    }

    /** @return array{float, float} */
    public function p(float $x, float $y, float $z = 0.0): array
    {
        return [
            $this->ox + ($x - $y) * $this->u,
            $this->oy + ($x + $y) * $this->u * ISO - $z,
        ];
    }
}

// ---------------------------------------------------------------- GD helpers

function newImage(int $w, int $h, ?array $rgb = null): GdImage
{
    $im = imagecreatetruecolor(max(1, $w), max(1, $h));
    imagesavealpha($im, true);
    if ($rgb === null) {
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
    } else {
        imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    }

    return $im;
}

/** Linear blend toward black: multiplies every channel by $factor. */
function shade(GdImage $im, float $factor): void
{
    $alpha = (int) round(127 * $factor);
    if ($alpha < 127) {
        imagefilledrectangle($im, 0, 0, imagesx($im), imagesy($im),
            imagecolorallocatealpha($im, 0, 0, 0, $alpha));
    }
}

/** Random translucent dots, a cheap stand-in for texture noise. */
function speckle(GdImage $im, int $strength, int $seed): void
{
    mt_srand($seed);
    $w = imagesx($im);
    $h = imagesy($im);
    $count = intdiv($w * $h, 90);
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(0, $w - 1);
        $y = mt_rand(0, $h - 1);
        $s = mt_rand(1, S);
        $v = mt_rand(0, 1) ? 255 : 0;
        $a = 127 - mt_rand(8, $strength);
        $c = imagecolorallocatealpha($im, $v, $v, $v, max(0, $a));
        imagefilledrectangle($im, $x, $y, $x + $s, $y + $s, $c);
    }
}

// ---------------------------------------------------------------- textures

function masonry(int $w, int $h, int $seed, array $base, array $mortar, int $jitter): GdImage
{
    mt_srand($seed);
    $im = newImage($w, $h, $base);
    $rowH = 7 * S;
    $line = imagecolorallocate($im, ...$mortar);
    for ($j = 0, $top = 0; $top < $h; $top += $rowH, $j++) {
        $offset = ($j % 2) * 5 * S;
        for ($left = -$offset; $left < $w; $left += 10 * S) {
            $v = mt_rand(-$jitter, $jitter);
            imagefilledrectangle($im, $left, $top, $left + 10 * S - 2, $top + $rowH - 2,
                imagecolorallocate($im, $base[0] + $v, $base[1] + $v, $base[2] + $v));
            imagerectangle($im, $left, $top, $left + 10 * S - 2, $top + $rowH - 2, $line);
        }
    }
    speckle($im, 30, $seed);

    return $im;
}

function texStone(int $w, int $h, int $seed): GdImage
{
    return masonry($w, $h, $seed, [168, 158, 140], [120, 112, 98], 14);
}

function texDarkstone(int $w, int $h, int $seed): GdImage
{
    return masonry($w, $h, $seed, [86, 88, 100], [50, 52, 62], 10);
}

function texColumns(int $w, int $h, int $seed): GdImage
{
    // greek colonnade: bright fluted marble columns in front of a shadowed recess
    $im = newImage($w, $h, [72, 64, 58]);
    speckle($im, 16, $seed);
    $beamH = 7 * S;
    imagefilledrectangle($im, 0, 0, $w, $beamH, imagecolorallocate($im, 226, 221, 208));
    imagefilledrectangle($im, 0, $beamH, $w, $beamH + 2 * S, imagecolorallocate($im, 184, 178, 164));
    $shadowSide = imagecolorallocate($im, 168, 162, 148);
    $litSide = imagecolorallocate($im, 208, 203, 190);
    $body = imagecolorallocate($im, 242, 238, 228);
    $flute = imagecolorallocate($im, 196, 190, 176);
    $cap = imagecolorallocate($im, 232, 227, 214);
    $capShadow = imagecolorallocate($im, 178, 172, 158);
    $half = 11 * S;
    $top = $beamH + 2 * S;
    for ($cx = 25 * S; $cx < $w; $cx += 50 * S) {
        // shaft, lit from the left, with two flutes
        imagefilledrectangle($im, $cx - $half, $top, $cx + $half, $h, $body);
        imagefilledrectangle($im, $cx - $half, $top, $cx - $half + 3 * S, $h, $litSide);
        imagefilledrectangle($im, $cx + $half - 4 * S, $top, $cx + $half, $h, $shadowSide);
        imageline($im, $cx - 3 * S, $top, $cx - 3 * S, $h, $flute);
        imageline($im, $cx + 3 * S, $top, $cx + 3 * S, $h, $flute);
        // capital (abacus over echinus) and base plinth
        imagefilledrectangle($im, $cx - $half - 3 * S, $top, $cx + $half + 3 * S, $top + 3 * S, $cap);
        imagefilledrectangle($im, $cx - $half - S, $top + 3 * S, $cx + $half + S, $top + 5 * S, $capShadow);
        imagefilledrectangle($im, $cx - $half - 2 * S, $h - 3 * S, $cx + $half + 2 * S, $h, $capShadow);
        imagefilledrectangle($im, $cx - $half - 3 * S, $h - 2 * S, $cx + $half + 3 * S, $h, $cap);
    }

    return $im;
}

function atticTexture(int $w, int $h, int $seed): GdImage
{
    // rectangular pignon: marble panel with a cornice and a recessed field
    $im = newImage($w, $h, [230, 226, 216]);
    speckle($im, 12, $seed);
    imagefilledrectangle($im, 0, 0, $w, 4 * S, imagecolorallocate($im, 240, 236, 226));
    imageline($im, 0, 4 * S, $w, 4 * S, imagecolorallocate($im, 170, 164, 150));
    imagefilledrectangle($im, 10 * S, 8 * S, $w - 10 * S, $h - 5 * S,
        imagecolorallocate($im, 206, 200, 188));

    return $im;
}

/**
 * Sign lettering drawn on the WORKING canvas in screen space: bold white text
 * on a dark plaque, sheared smoothly along the panel slope at 4x resolution —
 * the final downscale anti-aliases it like vector signage. (Drawing into the
 * panel texture is hopeless: its ~5x horizontal squeeze eats the strokes.)
 */
function drawWorkingLabel(GdImage $canvas, string $label, float $cx, float $cy,
    float $panelW, float $panelH, float $slope, bool $mirror): void
{
    // the coin pictogram becomes a one-byte sentinel so byte-wise layout holds
    $label = str_replace('💰', "\x01", $label);
    // up to two lines, separated by | or a newline
    $lines = array_slice(array_values(array_filter(
        array_map('trim', preg_split('/[|\n]+/', $label)),
        fn (string $l): bool => $l !== ''
    )), 0, 2);
    if ($lines === []) {
        return;
    }
    $gw = imagefontwidth(5);
    $gh = imagefontheight(5);
    $rowH = $gh + 3;
    $stampW = max(array_map(fn (string $l): int => strlen($l) * ($gw + 2) + 4, $lines));
    $stampH = count($lines) * $rowH + 2;
    // bold multi-strike, one glyph per widened cell so letters never touch
    $stamp = imagecreate($stampW, $stampH);
    $bg = imagecolorallocate($stamp, 1, 2, 3);
    imagecolortransparent($stamp, $bg);
    $ink = imagecolorallocate($stamp, 250, 248, 242);
    $gold = imagecolorallocate($stamp, 224, 182, 64);
    $goldRim = imagecolorallocate($stamp, 160, 118, 28);
    $goldHi = imagecolorallocate($stamp, 248, 226, 140);
    foreach ($lines as $li => $line) {
        $len = strlen($line);
        // justified: shorter lines get wider tracking so every line fills the
        // plaque and the background hugs the text
        $extra = $len > 1 ? intdiv($stampW - 4 - $len * ($gw + 2), $len - 1) : 0;
        $x0 = 2 + intdiv($stampW - 4 - $len * ($gw + 2) - $extra * ($len - 1), 2);
        for ($i = 0; $i < $len; $i++) {
            if ($line[$i] === "\x01") {
                // gold coin pictogram filling the glyph cell
                $ccx = $x0 + $i * ($gw + 2 + $extra) + intdiv($gw + 2, 2);
                $ccy = 1 + $li * $rowH + intdiv($gh, 2) + 1;
                $dia = $gh - 3;
                imagefilledellipse($stamp, $ccx, $ccy, $dia, $dia, $gold);
                imageellipse($stamp, $ccx, $ccy, $dia, $dia, $goldRim);
                imagefilledellipse($stamp, $ccx - 2, $ccy - 2, 3, 3, $goldHi);
                imagefilledrectangle($stamp, $ccx - 1, $ccy - 2, $ccx, $ccy + 3, $goldRim);
                continue;
            }
            foreach ([[1, 1], [2, 1], [1, 2], [2, 2]] as [$ox, $oy]) {
                imagechar($stamp, 5, $x0 + $i * ($gw + 2 + $extra) + $ox,
                    1 + $li * $rowH + $oy, $line[$i], $ink);
            }
        }
    }
    // the plaque IS the attic panel (small marble border); the text fills it,
    // whatever its length — capped so short labels don't become grotesque
    $hw = $panelW / 2 - 6;
    $hh = $panelH / 2 - 6;
    $tw = min((int) (2 * $hw) - 10, $stampW * 5);
    $th = min((int) (2 * $hh) - 8, $stampH * 4);
    $scaled = newImage($tw, $th);
    imagealphablending($scaled, false);
    imagecopyresized($scaled, $stamp, 0, 0, 0, 0, $tw, $th, $stampW, $stampH);
    if ($mirror) {
        // the whole canvas flips later; pre-flip so the text reads correctly
        imageflip($scaled, IMG_FLIP_HORIZONTAL);
    }
    $plaque = [];
    foreach ([[-$hw, -$hh], [$hw, -$hh], [$hw, $hh], [-$hw, $hh]] as [$dx, $dy]) {
        $plaque[] = $cx + $dx;
        $plaque[] = $cy + $dy + $dx * $slope;
    }
    imagefilledpolygon($canvas, $plaque, imagecolorallocate($canvas, 52, 46, 40));
    $x0 = (int) round($cx - $tw / 2);
    $y0 = $cy - $th / 2;
    for ($col = 0; $col < $tw; $col++) {
        imagecopy($canvas, $scaled, $x0 + $col,
            (int) round($y0 + ($col - $tw / 2) * $slope), $col, 0, 1, $th);
    }
}

function pedimentTexture(int $w, int $h, int $seed): GdImage
{
    // triangular fronton: marble cornice around a recessed tympanum
    $im = newImage($w, $h, [230, 226, 216]);
    speckle($im, 12, $seed);
    imagefilledpolygon($im, [
        intdiv($w, 2), 6 * S,
        12 * S, $h - 6 * S,
        $w - 12 * S, $h - 6 * S,
    ], imagecolorallocate($im, 198, 192, 178));
    imagefilledrectangle($im, 0, $h - 4 * S, $w, $h, imagecolorallocate($im, 240, 236, 226));
    imageline($im, 0, $h - 4 * S, $w, $h - 4 * S, imagecolorallocate($im, 170, 164, 150));

    return $im;
}

function texLogs(int $w, int $h, int $seed): GdImage
{
    // stacked horizontal logs for tree houses
    mt_srand($seed);
    $im = newImage($w, $h, [110, 82, 52]);
    $rowH = 9 * S;
    for ($top = 0; $top < $h; $top += $rowH) {
        $v = mt_rand(-12, 12);
        imagefilledrectangle($im, 0, $top, $w, $top + $rowH - 1,
            imagecolorallocate($im, 110 + $v, 82 + $v, 52 + $v));
        imagefilledrectangle($im, 0, $top, $w, $top + S,
            imagecolorallocate($im, 142 + $v, 112 + $v, 76 + $v));
        imagefilledrectangle($im, 0, $top + $rowH - 2 * S, $w, $top + $rowH - 1,
            imagecolorallocate($im, 68 + $v, 48 + $v, 30 + $v));
    }
    speckle($im, 24, $seed);

    return $im;
}

function texSwamp(int $w, int $h, int $seed): GdImage
{
    // damp olive planks eaten by moss from below
    mt_srand($seed);
    $im = newImage($w, $h, [88, 86, 60]);
    $line = imagecolorallocate($im, 54, 54, 36);
    for ($left = 0; $left < $w; $left += 5 * S) {
        $v = mt_rand(-14, 14);
        imagefilledrectangle($im, $left, 0, $left + 5 * S - 2, $h,
            imagecolorallocate($im, 88 + $v, 86 + $v, 60 + $v));
        imageline($im, $left, 0, $left, $h, $line);
    }
    for ($i = 0, $n = intdiv($w, 4 * S); $i < $n; $i++) {
        $x = mt_rand(0, $w - 1);
        $y = $h - mt_rand(0, (int) ($h * 0.7));
        $r = mt_rand(2 * S, 5 * S);
        imagefilledellipse($im, $x, $y, $r, intdiv($r, 2),
            imagecolorallocatealpha($im, 70, 112, 58, mt_rand(60, 95)));
    }
    speckle($im, 26, $seed);

    return $im;
}

function texPlaster(int $w, int $h, int $seed): GdImage
{
    $im = newImage($w, $h, [214, 200, 176]);
    speckle($im, 20, $seed);

    return $im;
}

function texWood(int $w, int $h, int $seed): GdImage
{
    mt_srand($seed);
    $im = newImage($w, $h, [124, 96, 66]);
    $line = imagecolorallocate($im, 82, 62, 42);
    for ($left = 0; $left < $w; $left += 5 * S) {
        $v = mt_rand(-16, 16);
        imagefilledrectangle($im, $left, 0, $left + 5 * S - 2, $h,
            imagecolorallocate($im, 124 + $v, 96 + $v, 66 + $v));
        imageline($im, $left, 0, $left, $h, $line);
    }
    speckle($im, 26, $seed);

    return $im;
}

function texColombage(int $w, int $h, int $seed): GdImage
{
    $im = texPlaster($w, $h, $seed);
    $beam = imagecolorallocate($im, 94, 70, 48);
    $bw = 3 * S;
    imagefilledrectangle($im, 0, 0, $w, $bw, $beam);
    imagefilledrectangle($im, 0, $h - $bw, $w, $h, $beam);
    imagesetthickness($im, 2 * $bw / 3);
    for ($left = 0; $left <= $w; $left += 25 * S) {
        imagefilledrectangle($im, $left - intdiv($bw, 2), 0, $left + intdiv($bw, 2), $h, $beam);
        imageline($im, $left, $h, $left + 25 * S, 0, $beam);
    }
    imagesetthickness($im, 1);

    return $im;
}

function roofRows(int $w, int $h, int $seed, array $base, int $jitter, array $line, int $rowH): GdImage
{
    mt_srand($seed);
    $im = newImage($w, $h, $base);
    $lineC = imagecolorallocate($im, ...$line);
    for ($j = 0, $top = 0; $top < $h; $top += $rowH, $j++) {
        $offset = ($j % 2) * 4 * S;
        for ($left = -$offset; $left < $w; $left += 8 * S) {
            $v = mt_rand(-$jitter, $jitter);
            imagefilledrectangle($im, $left, $top, $left + 8 * S, $top + $rowH,
                imagecolorallocate($im, $base[0] + $v, $base[1] + $v, $base[2] + $v));
            imageline($im, $left, $top, $left, $top + $rowH, $lineC);
        }
        imageline($im, 0, $top + $rowH, $w, $top + $rowH, $lineC);
    }
    speckle($im, 22, $seed);

    return $im;
}

function texTiles(int $w, int $h, int $seed): GdImage
{
    return roofRows($w, $h, $seed, [164, 96, 70], 16, [110, 62, 46], 4 * S);
}

function texSlate(int $w, int $h, int $seed): GdImage
{
    return roofRows($w, $h, $seed, [104, 110, 122], 12, [70, 74, 84], 4 * S);
}

function texThatch(int $w, int $h, int $seed): GdImage
{
    mt_srand($seed);
    $im = newImage($w, $h, [148, 122, 74]);
    for ($i = 0, $n = intdiv($w * $h, 60); $i < $n; $i++) {
        $x = mt_rand(0, $w - 1);
        $y = mt_rand(0, $h - 1);
        $len = mt_rand(4 * S, 10 * S);
        $v = mt_rand(-34, 34);
        imageline($im, $x, $y, $x + mt_rand(-S, S), $y + $len,
            imagecolorallocatealpha($im, 148 + $v, 122 + $v, 74 + $v, 40));
    }
    $band = imagecolorallocatealpha($im, 60, 48, 20, 96);
    for ($top = 10 * S; $top < $h; $top += 10 * S) {
        imagefilledrectangle($im, 0, $top, $w, $top + S, $band);
    }

    return $im;
}

function texFlat(int $w, int $h, int $seed): GdImage
{
    $im = newImage($w, $h, [150, 138, 118]);
    speckle($im, 26, $seed);

    return $im;
}

function facadeTexture(string $name, ?string $file, int $w, int $h, int $seed): GdImage
{
    if ($file !== null) {
        return loadScaled($file, $w, $h);
    }

    return match ($name) {
        'plaster' => texPlaster($w, $h, $seed),
        'wood' => texWood($w, $h, $seed),
        'colombage' => texColombage($w, $h, $seed),
        'columns' => texColumns($w, $h, $seed),
        'darkstone' => texDarkstone($w, $h, $seed),
        'logs' => texLogs($w, $h, $seed),
        'swamp' => texSwamp($w, $h, $seed),
        default => texStone($w, $h, $seed),
    };
}

function roofTexture(string $name, ?string $file, int $w, int $h, int $seed): GdImage
{
    if ($file !== null) {
        return loadScaled($file, $w, $h);
    }

    return match ($name) {
        'thatch' => texThatch($w, $h, $seed),
        'slate' => texSlate($w, $h, $seed),
        'flat' => texFlat($w, $h, $seed),
        default => texTiles($w, $h, $seed),
    };
}

function loadScaled(string $file, int $w, int $h): GdImage
{
    $src = imagecreatefrompng($file);
    if ($src === false) {
        fwrite(STDERR, "cannot read $file\n");
        exit(1);
    }
    $im = newImage($w, $h);
    imagecopyresampled($im, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));

    return $im;
}

// ---------------------------------------------------------------- wall dressing

function drawDoor(GdImage $tex, string $style, int $cx, int $base, int $storeyH): array
{
    // widths are ~2x heights: the map squeezes textures horizontally
    $w = $style === 'simple' ? 42 * S : 66 * S;
    $h = (int) ($storeyH * ($style === 'simple' ? 0.72 : 0.85));
    $half = intdiv($w, 2);
    $frame = imagecolorallocate($tex, 96, 78, 56);
    $leaf = imagecolorallocate($tex, 58, 42, 28);
    $split = imagecolorallocate($tex, 40, 30, 20);
    if ($style === 'arche') {
        // elliptical arch capped by the door height, not the (much larger) width
        $frame = imagecolorallocate($tex, 190, 184, 168);
        $archH = max(6 * S, intdiv($h, 2));
        $cy = $base - $h + $archH;
        imagefilledarc($tex, $cx, $cy, 2 * ($half + 3 * S), 2 * $archH, 180, 360, $frame, IMG_ARC_PIE);
        imagefilledrectangle($tex, $cx - $half - 3 * S, $cy, $cx + $half + 3 * S, $base, $frame);
        imagefilledarc($tex, $cx, $cy, 2 * $half, 2 * ($archH - 3 * S), 180, 360, $leaf, IMG_ARC_PIE);
        imagefilledrectangle($tex, $cx - $half, $cy, $cx + $half, $base, $leaf);
    } else {
        if ($style === 'double') {
            $frame = imagecolorallocate($tex, 190, 184, 168);
            imagefilledrectangle($tex, $cx - $half - 3 * S, $base - $h - 5 * S,
                $cx + $half + 3 * S, $base - $h - 2 * S, imagecolorallocate($tex, 140, 128, 104));
        }
        imagefilledrectangle($tex, $cx - $half - 2 * S, $base - $h - 2 * S,
            $cx + $half + 2 * S, $base, $frame);
        imagefilledrectangle($tex, $cx - $half, $base - $h, $cx + $half, $base, $leaf);
    }
    imagesetthickness($tex, S);
    imageline($tex, $cx, $base - $h + ($style === 'arche' ? 3 * S : 0), $cx, $base, $split);
    imagesetthickness($tex, 1);

    return [$cx - $half - 4 * S, $cx + $half + 4 * S];
}

function drawWindow(GdImage $tex, string $style, int $cx, int $base, int $storeyH): void
{
    $frame = imagecolorallocate($tex, 198, 188, 168);
    $glass = imagecolorallocate($tex, 52, 58, 66);
    $bar = imagecolorallocate($tex, 30, 34, 40);
    [$w, $h] = match ($style) {
        'hautes' => [20 * S, max(10 * S, (int) ($storeyH * 0.55))],
        'arche' => [26 * S, 12 * S],
        default => [30 * S, 11 * S],
    };
    $half = intdiv($w, 2);
    $cy = $base - intdiv($storeyH, 2);
    $top = $cy - intdiv($h, 2);
    $bot = $cy + intdiv($h, 2);
    imagesetthickness($tex, S);
    if ($style === 'arche') {
        // arch included in the height budget and in the vertical centering
        $total = min(13 * S, (int) ($storeyH * 0.6));
        $archH = intdiv($total * 2, 5);
        $spring = $cy - intdiv($total, 2) + $archH;
        $bot = $spring + $total - $archH;
        imagefilledarc($tex, $cx, $spring, 2 * ($half + 3 * S), 2 * ($archH + 3 * S), 180, 360,
            $frame, IMG_ARC_PIE);
        imagefilledrectangle($tex, $cx - $half - 3 * S, $spring, $cx + $half + 3 * S,
            $bot + 2 * S, $frame);
        imagefilledarc($tex, $cx, $spring, 2 * $half, 2 * $archH, 180, 360, $glass, IMG_ARC_PIE);
        imagefilledrectangle($tex, $cx - $half, $spring, $cx + $half, $bot, $glass);
        imageline($tex, $cx, $spring - $archH + S, $cx, $bot, $bar);
    } else {
        imagefilledrectangle($tex, $cx - $half - 2 * S, $top - 2 * S,
            $cx + $half + 2 * S, $bot + 2 * S, $frame);
        imagefilledrectangle($tex, $cx - $half, $top, $cx + $half, $bot, $glass);
        imageline($tex, $cx, $top, $cx, $bot, $bar);
        if ($style === 'hautes') {
            imageline($tex, $cx - $half, $cy, $cx + $half, $cy, $bar);
        }
    }
    imagesetthickness($tex, 1);
}

/** Draw a door and windows in flat texture space; an artist swatch replaces this. */
function stampOpenings(GdImage $tex, int $lenTiles, int $storeys, int $storeyH, ?array $door,
    string $windows = 'simple'): void
{
    $width = imagesx($tex);
    $height = imagesy($tex);
    $blocked = null;
    if ($door !== null && $door['style'] !== 'aucune') {
        $w = $door['style'] === 'simple' ? 42 * S : 66 * S;
        $cx = (int) round($door['frac'] * $width);
        $cx = max(intdiv($w, 2) + 6 * S, min($width - intdiv($w, 2) - 6 * S, $cx));
        $blocked = drawDoor($tex, $door['style'], $cx, $height, $storeyH);
    }
    if ($windows === 'aucune') {
        return;
    }
    for ($storey = 0; $storey < $storeys; $storey++) {
        $base = $height - $storey * $storeyH;
        for ($t = 0; $t < $lenTiles; $t++) {
            $cx = intdiv($width, $lenTiles) * $t + intdiv($width, 2 * $lenTiles);
            if ($storey === 0 && $blocked !== null
                && $cx + 17 * S >= $blocked[0] && $cx - 17 * S <= $blocked[1]) {
                continue;
            }
            drawWindow($tex, $windows, $cx, $base, $storeyH);
        }
    }
}

/** Darken under the eaves and at the foot of the wall. */
function occlude(GdImage $tex, int $eavePx): void
{
    $w = imagesx($tex);
    $h = imagesy($tex);
    for ($y = 0; $y < $h; $y++) {
        $factor = 1.0;
        if ($y < $eavePx) {
            $factor = 0.72 + 0.28 * $y / $eavePx;
        }
        $foot = $h - $y;
        if ($foot < 10 * S) {
            $factor = min($factor, 0.78 + 0.22 * $foot / (10 * S));
        }
        if ($factor < 1.0) {
            imagefilledrectangle($tex, 0, $y, $w, $y,
                imagecolorallocatealpha($tex, 0, 0, 0, (int) round(127 * $factor)));
        }
    }
}

// ---------------------------------------------------------------- compositing

/**
 * Affine-map $tex onto the parallelogram $a -> $b (u axis) -> $d (v axis),
 * optionally clipped to a convex polygon in canvas coordinates.
 */
function pasteQuad(GdImage $canvas, GdImage $tex, array $a, array $b, array $d,
    float $shadeF, ?array $poly = null): void
{
    $tw = imagesx($tex);
    $th = imagesy($tex);
    $e1 = [$b[0] - $a[0], $b[1] - $a[1]];
    $e2 = [$d[0] - $a[0], $d[1] - $a[1]];
    $det = $e1[0] * $e2[1] - $e1[1] * $e2[0];
    if (abs($det) < 1e-6) {
        return;
    }
    if ($det < 0) {
        // GD rejects mirroring matrices: re-anchor on the v-end and flip the texture
        imageflip($tex, IMG_FLIP_VERTICAL);
        [$a, $b, $d] = [$d, [$d[0] + $e1[0], $d[1] + $e1[1]], $a];
        $e2 = [$d[0] - $a[0], $d[1] - $a[1]];
    }
    shade($tex, $shadeF);

    // forward matrix, translated so the transformed bbox starts at (0, 0)
    $m = [$e1[0] / $tw, $e1[1] / $tw, $e2[0] / $th, $e2[1] / $th, 0, 0];
    $corners = [[0, 0], [$tw, 0], [$tw, $th], [0, $th]];
    $minX = $minY = PHP_FLOAT_MAX;
    foreach ($corners as [$sx, $sy]) {
        $minX = min($minX, $m[0] * $sx + $m[2] * $sy + $a[0]);
        $minY = min($minY, $m[1] * $sx + $m[3] * $sy + $a[1]);
    }
    $m[4] = $a[0] - $minX;
    $m[5] = $a[1] - $minY;
    $layer = imageaffine($tex, $m);
    if ($layer === false) {
        return;
    }
    imagesavealpha($layer, true);

    if ($poly !== null) {
        clipToPoly($layer, $poly, (int) round($minX), (int) round($minY));
    }
    imagecopy($canvas, $layer, (int) round($minX), (int) round($minY), 0, 0,
        imagesx($layer), imagesy($layer));
}

/** Clear every layer pixel falling outside the polygon (canvas coordinates). */
function clipToPoly(GdImage $layer, array $poly, int $offX, int $offY): void
{
    $w = imagesx($layer);
    $h = imagesy($layer);
    $mask = imagecreate($w, $h);
    imagecolorallocate($mask, 0, 0, 0);
    $white = imagecolorallocate($mask, 255, 255, 255);
    $pts = [];
    foreach ($poly as [$px, $py]) {
        $pts[] = $px - $offX;
        $pts[] = $py - $offY;
    }
    imagefilledpolygon($mask, $pts, $white);
    imagealphablending($layer, false);
    $clear = imagecolorallocatealpha($layer, 0, 0, 0, 127);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (imagecolorat($mask, $x, $y) === 0) {
                imagesetpixel($layer, $x, $y, $clear);
            }
        }
    }
    imagealphablending($layer, true);
}

function edgeLine(GdImage $canvas, array $a, array $b, int $width = S): void
{
    imagesetthickness($canvas, $width);
    imageline($canvas, (int) $a[0], (int) $a[1], (int) $b[0], (int) $b[1],
        imagecolorallocatealpha($canvas, 44, 36, 28, 62));
    imagesetthickness($canvas, 1);
}

// ---------------------------------------------------------------- build

function build(string $form, string $facade = 'stone', string $roofMat = 'tiles',
    ?string $shape = null, ?string $facadeImg = null, ?string $roofImg = null,
    bool $mirror = false, int $seed = 1, string $door = 'simple', float $doorFrac = 0.5,
    string $windows = 'simple', string $label = ''): GdImage
{
    [$w, $d, $cols, $rows, $storeys, $storeyF, $roofF, $defaultShape] = FORMS[$form];
    $shape = $shape ?? $defaultShape;
    $pr = new Projection($w, $d, $cols, $rows);
    $h = $storeys * $storeyF * S;
    $rh = $roofF * S;

    // temple: the fronton must crown the far parapet corner on screen; the height
    // this projection demands is split half between raising the fronton and
    // sinking the deck, so neither a pointy pignon nor a deep well
    $ovTemple = 0.12;
    $parapet = 0.14;
    $sinkT = 0.0;
    if ($shape === 'temple') {
        $need = ($w / 2 + $d + $ovTemple) * $pr->u * ISO + 2 * S - $rh;
        $sinkT = max(2 * S, $need / 2);
        $rh += max(0, (int) round($need - $sinkT));
    } elseif ($shape === 'banque') {
        // banque assumes its flat roof is seen; only a thin lip below the coping
        $sinkT = 2 * S;
    } elseif ($shape === 'attique') {
        // rectangular pignon covers more sky than a triangle: it only needs to
        // beat the far parapet corner at its left end
        $need = $d * $pr->u * ISO + 2 * S - $rh;
        $sinkT = max(2 * S, $need / 2);
        $rh += max(0, (int) round($need - $sinkT));
    }

    // highest drawn points per shape as [x+y, z] pairs; shrink walls and roof
    // together if any would leave the canvas ("slightly smaller" beats "cut off")
    $peaks = match ($shape) {
        'flat' => [[0.0, $h]],
        'temple', 'banque' => [[0.0, $h], [$d + $w / 2, $h + $rh]],
        'attique' => [[0.0, $h], [(float) $d, $h + $rh]],
        'gable' => [[$d / 2 - EAVE, $h + $rh + 3 * S]], // slab thickness on top
        default => [[(float) min($w, $d), $h + $rh]],
    };
    $factor = 1.0;
    foreach ($peaks as [$xy, $z]) {
        $room = $pr->oy + $xy * $pr->u * ISO - MARGIN_TOP;
        if ($z > $room && $z > 0) {
            $factor = min($factor, $room / $z);
        }
    }
    if ($factor < 1.0) {
        $h = (int) ($h * $factor);
        $rh = (int) ($rh * $factor);
        $sinkT *= $factor;
    }
    $storeyH = intdiv($h, $storeys);
    $canvas = newImage($pr->width, $pr->height);

    $winStyle = $facade === 'columns' ? 'aucune' : $windows;
    $wall = function (int $lenTiles, array $a, array $b, float $shadeF, ?array $doorSpec,
        ?array $clip = null)
    use ($canvas, $facade, $facadeImg, $storeys, $storeyH, $h, $seed, $winStyle): void {
        $tex = facadeTexture($facade, $facadeImg, $lenTiles * TILE * S * 2, $h, $seed);
        stampOpenings($tex, $lenTiles, $storeys, $storeyH, $doorSpec, $winStyle);
        occlude($tex, 6 * S);
        pasteQuad($canvas, $tex, [$a[0], $a[1] - $h], [$b[0], $b[1] - $h], $a, $shadeF, $clip);
    };

    // temple deck and the back parapet faces go BEFORE the walls (occluded like
    // an interior); the front and right caps go after, on top of the walls
    $parTex = fn (int $sd): GdImage => texPlaster(TILE * S, 8 * S, $sd);
    $flatTopShapes = ['temple', 'banque', 'attique'];
    if (in_array($shape, $flatTopShapes, true)) {
        pasteQuad($canvas, roofTexture($roofMat, $roofImg, $w * TILE * S, $d * TILE * S, $seed + 5),
            $pr->p(0, 0, $h - $sinkT), $pr->p($w, 0, $h - $sinkT), $pr->p(0, $d, $h - $sinkT), 0.45);
        // inner faces of the two back parapet walls
        pasteQuad($canvas, $parTex($seed + 11), $pr->p($parapet, 0, $h),
            $pr->p($parapet, $d, $h), $pr->p($parapet, 0, $h - $sinkT), 0.7);
        pasteQuad($canvas, $parTex($seed + 12), $pr->p($parapet, $parapet, $h),
            $pr->p($w, $parapet, $h), $pr->p($parapet, $parapet, $h - $sinkT), 0.85);
        // their top caps
        pasteQuad($canvas, $parTex($seed + 13), $pr->p(0, 0, $h),
            $pr->p(0, $d, $h), $pr->p($parapet, 0, $h), 0.95);
        pasteQuad($canvas, $parTex($seed + 14), $pr->p(0, 0, $h),
            $pr->p($w, 0, $h), $pr->p(0, $parapet, $h), 0.95);
        edgeLine($canvas, $pr->p(0, 0, $h), $pr->p(0, $d, $h), intdiv(S, 2));
        edgeLine($canvas, $pr->p(0, 0, $h), $pr->p($w, 0, $h), intdiv(S, 2));
    }

    // left wall carries the door; right wall only windows
    $wall($w, $pr->p(0, $d), $pr->p($w, $d), SHADE_LEFT, ['style' => $door, 'frac' => $doorFrac]);
    $wall($d, $pr->p($w, $d), $pr->p($w, 0), SHADE_RIGHT, null);
    $corner = $pr->p($w, $d);
    edgeLine($canvas, [$corner[0], $corner[1] - $h], $corner, intdiv(S, 2));

    $slopeH = (int) hypot($d / 2 * $pr->u * 1.4, $rh);
    $pt = fn (float $x, float $y, float $z = 0.0): array => $pr->p($x, $y, $z);
    $ridge = $h + $rh;
    $ao = EAVE;

    // fronton slab between $x0 and $x1 on the front plane: cornice caps on both
    // rakes, marble face, protruding $ovTemple and dropping below the wall top
    $fronton = function (float $x0, float $x1, int $rhX)
    use ($canvas, $pt, $d, $h, $seed, $ovTemple): void {
        $yF = $d + $ovTemple;
        $bz = $h - 2 * S;
        $mid = ($x0 + $x1) / 2;
        $apexF = $pt($mid, $yF, $h + $rhX);
        $apexB = $pt($mid, $d, $h + $rhX);
        $b0F = $pt($x0, $yF, $bz);
        $b1F = $pt($x1, $yF, $bz);
        $capTex = fn (int $sd): GdImage => texPlaster(TILE * S, 8 * S, $sd);
        pasteQuad($canvas, $capTex($seed + 6), $apexB, $apexF, $pt($x0, $d, $bz), 1.0);
        pasteQuad($canvas, $capTex($seed + 7), $apexB, $apexF, $pt($x1, $d, $bz), 0.7);
        pasteQuad($canvas, pedimentTexture((int) (($x1 - $x0) * TILE * S * 2),
            max(1, $rhX + 2 * S), $seed),
            [$b0F[0], $b0F[1] - $rhX - 2 * S], [$b1F[0], $b1F[1] - $rhX - 2 * S], $b0F, SHADE_LEFT,
            [$apexF, $b0F, $b1F]);
        edgeLine($canvas, $apexF, $b0F, intdiv(S, 2));
        edgeLine($canvas, $apexF, $b1F, intdiv(S, 2));
        edgeLine($canvas, $b0F, $b1F, intdiv(S, 2));
    };

    if (in_array($shape, $flatTopShapes, true)) {
        // front and right parapet caps, over the freshly drawn walls
        pasteQuad($canvas, $parTex($seed + 15), $pt(0, $d - $parapet, $h),
            $pt($w, $d - $parapet, $h), $pt(0, $d, $h), 0.95);
        pasteQuad($canvas, $parTex($seed + 16), $pt($w - $parapet, 0, $h),
            $pt($w - $parapet, $d, $h), $pt($w, 0, $h), 0.9);
        edgeLine($canvas, $pt(0, $d, $h), $pt($w, $d, $h), intdiv(S, 2));
        edgeLine($canvas, $pt($w, $d, $h), $pt($w, 0, $h), intdiv(S, 2));
        if ($shape === 'temple') {
            $fronton(-$ovTemple, $w + $ovTemple, $rh);
        } elseif ($shape === 'banque') {
            // banque: a modest fronton over the entrance, flat roof in plain sight
            $fronton(0.25 * $w, 0.75 * $w, max(6 * S, (int) (0.6 * $rh)));
        } else {
            // attique: full-width rectangular pignon, slab like the fronton
            $yF = $d + $ovTemple;
            $x0 = -$ovTemple;
            $x1 = $w + $ovTemple;
            $bzA = $h - 2 * S;
            $topA = $h + $rh;
            pasteQuad($canvas, $parTex($seed + 17), $pt($x0, $d, $topA),
                $pt($x1, $d, $topA), $pt($x0, $yF, $topA), 0.95);
            pasteQuad($canvas, $parTex($seed + 18), $pt($x1, $yF, $topA),
                $pt($x1, $d, $topA), $pt($x1, $yF, $bzA), 0.7);
            pasteQuad($canvas, atticTexture((int) (($x1 - $x0) * TILE * S * 2),
                $rh + 2 * S, $seed),
                [$pt($x0, $yF, $bzA)[0], $pt($x0, $yF, $bzA)[1] - $rh - 2 * S],
                [$pt($x1, $yF, $bzA)[0], $pt($x1, $yF, $bzA)[1] - $rh - 2 * S],
                $pt($x0, $yF, $bzA), SHADE_LEFT);
            edgeLine($canvas, $pt($x0, $yF, $topA), $pt($x1, $yF, $topA), intdiv(S, 2));
            edgeLine($canvas, $pt($x1, $yF, $topA), $pt($x1, $yF, $bzA), intdiv(S, 2));
            edgeLine($canvas, $pt($x0, $yF, $bzA), $pt($x1, $yF, $bzA), intdiv(S, 2));
            if ($label !== '') {
                [$lcx, $lcy] = $pt(($x0 + $x1) / 2, $yF, ($bzA + $topA) / 2);
                drawWorkingLabel($canvas, strtoupper($label), $lcx, $lcy,
                    ($x1 - $x0) * $pr->u, $rh + 2 * S, ISO, $mirror);
            }
        }
    } elseif ($shape === 'flat') {
        pasteQuad($canvas, roofTexture($roofMat, $roofImg, $w * TILE * S, $d * TILE * S, $seed + 5),
            $pt(0, 0, $h), $pt($w, 0, $h), $pt(0, $d, $h), SHADE_FLAT);
        foreach ([[[0, $d], [$w, $d]], [[$w, $d], [$w, 0]], [[0, 0], [0, $d]], [[0, 0], [$w, 0]]] as [$s, $e]) {
            edgeLine($canvas, $pt($s[0], $s[1], $h), $pt($e[0], $e[1], $h));
        }
    } elseif ($shape === 'gable' && $w >= $d) {
        // ridge along x; far slope hidden, gable triangle closes the right end.
        // The roof is a slab with real thickness: its cut faces at the gable end
        // rise vertically on screen, so the overhang reads on both rakes
        $t = 3 * S;
        $tri = facadeTexture($facade, $facadeImg, $d * TILE * S * 2, $rh, $seed);
        pasteQuad($canvas, $tri, $pt($w, $d, $ridge), $pt($w, 0, $ridge),
            $pt($w, $d, $h), SHADE_RIGHT * 0.86,
            [$pt($w, $d / 2, $ridge), $pt($w, $d, $h), $pt($w, 0, $h)]);
        edgeLine($canvas, $pt($w, $d / 2, $ridge), $pt($w, 0, $h), intdiv(S, 2));
        edgeLine($canvas, $pt($w, $d, $h), $pt($w, 0, $h), intdiv(S, 2));
        $endTex = fn (int $sd): GdImage => roofTexture($roofMat, $roofImg, TILE * S, $slopeH, $sd);
        // back slab cut face, apex down to just past the back corner (running it
        // to the full eave overhang would leave a beam floating in the air)
        $yEnd = -$ao / 2;
        $zEnd = $ridge - ($d / 2 - $yEnd) / ($d / 2 + $ao) * ($ridge - ($h - 2 * S));
        pasteQuad($canvas, $endTex($seed + 7),
            $pt($w + $ao, $d / 2, $ridge + $t), $pt($w + $ao, $yEnd, $zEnd + $t),
            $pt($w + $ao, $d / 2, $ridge), 0.45);
        // slab top surface
        pasteQuad($canvas, roofTexture($roofMat, $roofImg, $w * TILE * S * 2, $slopeH, $seed + 5),
            $pt(-$ao, $d + $ao, $h - 2 * S + $t), $pt($w + $ao, $d + $ao, $h - 2 * S + $t),
            $pt(-$ao, $d / 2, $ridge + $t), SHADE_SLOPE_L);
        // front slab cut face, eave corner up to the apex
        pasteQuad($canvas, $endTex($seed + 8),
            $pt($w + $ao, $d + $ao, $h - 2 * S + $t), $pt($w + $ao, $d / 2, $ridge + $t),
            $pt($w + $ao, $d + $ao, $h - 2 * S), 0.62);
        // eave fascia along the front
        pasteQuad($canvas, roofTexture($roofMat, $roofImg, $w * TILE * S * 2, $t, $seed + 9),
            $pt(-$ao, $d + $ao, $h - 2 * S + $t), $pt($w + $ao, $d + $ao, $h - 2 * S + $t),
            $pt(-$ao, $d + $ao, $h - 2 * S), 0.55);
        edgeLine($canvas, $pt(-$ao, $d / 2, $ridge + $t), $pt($w + $ao, $d / 2, $ridge + $t));
        edgeLine($canvas, $pt(-$ao, $d + $ao, $h - 2 * S), $pt($w + $ao, $d + $ao, $h - 2 * S),
            intdiv(S, 2));
    } else {
        // hip: ridge along the longer axis, both visible slopes are trapezoids
        [$rx0, $rx1] = $w >= $d ? [$d / 2, $w - $d / 2] : [$w / 2, $w / 2];
        [$ry0, $ry1] = $w >= $d ? [$d / 2, $d / 2] : [$d - $w / 2, $w / 2];
        pasteQuad($canvas, roofTexture($roofMat, $roofImg, $w * TILE * S * 2, $slopeH, $seed + 5),
            $pt(-$ao, $d + $ao, $h - 2 * S), $pt($w + $ao, $d + $ao, $h - 2 * S),
            $pt($rx0, $d / 2, $ridge), SHADE_SLOPE_L,
            [$pt(-$ao, $d + $ao, $h - 2 * S), $pt($w + $ao, $d + $ao, $h - 2 * S),
                $pt($rx1, $ry0, $ridge), $pt($rx0, $ry0, $ridge)]);
        pasteQuad($canvas, roofTexture($roofMat, $roofImg, $d * TILE * S * 2, $slopeH, $seed + 6),
            $pt($w + $ao, $d + $ao, $h - 2 * S), $pt($w + $ao, -$ao, $h - 2 * S),
            $pt($rx1, $ry0, $ridge), SHADE_SLOPE_R,
            [$pt($w + $ao, $d + $ao, $h - 2 * S), $pt($w + $ao, -$ao, $h - 2 * S),
                $pt($rx1, $ry1, $ridge), $pt($rx1, $ry0, $ridge)]);
        edgeLine($canvas, $pt($rx0, $ry0, $ridge), $pt($rx1, $ry0, $ridge));
        edgeLine($canvas, $pt(-$ao, $d + $ao, $h - 2 * S), $pt($w + $ao, $d + $ao, $h - 2 * S));
        edgeLine($canvas, $pt($w + $ao, $d + $ao, $h - 2 * S), $pt($w + $ao, -$ao, $h - 2 * S));
    }

    if ($mirror) {
        imageflip($canvas, IMG_FLIP_HORIZONTAL);
    }
    $final = newImage($cols * TILE, $rows * TILE);
    imagecopyresampled($final, $canvas, 0, 0, 0, 0, $cols * TILE, $rows * TILE,
        $pr->width, $pr->height);

    return $final;
}

function cropTiles(GdImage $img, string $outDir, string $name): void
{
    $cols = intdiv(imagesx($img), TILE);
    $rows = intdiv(imagesy($img), TILE);
    for ($j = 0; $j < $rows; $j++) {
        for ($i = 0; $i < $cols; $i++) {
            $tile = newImage(TILE, TILE);
            imagealphablending($tile, false);
            imagecopy($tile, $img, 0, 0, $i * TILE, $j * TILE, TILE, TILE);
            $n = $j * $cols + $i;
            imagepng($tile, sprintf('%s/%s_%02d.png', $outDir, $name, $n));
        }
    }
}

// ---------------------------------------------------------------- blueprints

function blueprint(string $form): GdImage
{
    [$w, $d, $cols, $rows, $storeys, $storeyF, $roofF] = FORMS[$form];
    $pr = new Projection($w, $d, $cols, $rows);
    $h = $storeys * $storeyF * S;
    $rh = $roofF * S;
    $im = newImage($pr->width, $pr->height, [240, 238, 232]);
    $grid = imagecolorallocate($im, 210, 206, 198);
    for ($i = 1; $i < $cols; $i++) {
        imageline($im, $i * TILE * S, 0, $i * TILE * S, $pr->height, $grid);
    }
    for ($j = 1; $j < $rows; $j++) {
        imageline($im, 0, $j * TILE * S, $pr->width, $j * TILE * S, $grid);
    }
    $gray = imagecolorallocate($im, 150, 150, 150);
    for ($x = 0; $x <= $w; $x++) {
        [$x1, $y1] = $pr->p($x, 0);
        [$x2, $y2] = $pr->p($x, $d);
        imageline($im, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $gray);
    }
    for ($y = 0; $y <= $d; $y++) {
        [$x1, $y1] = $pr->p(0, $y);
        [$x2, $y2] = $pr->p($w, $y);
        imageline($im, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $gray);
    }

    $quad = function (array $pts, array $rgb, string $label) use ($im): void {
        $color = imagecolorallocate($im, ...$rgb);
        imagesetthickness($im, S);
        $flat = [];
        foreach ($pts as [$px, $py]) {
            $flat[] = (int) $px;
            $flat[] = (int) $py;
        }
        imagepolygon($im, $flat, $color);
        imagesetthickness($im, 1);
        $cx = array_sum(array_column($pts, 0)) / count($pts);
        $cy = array_sum(array_column($pts, 1)) / count($pts);
        imagestring($im, 5, (int) $cx - 4 * strlen($label), (int) $cy, $label, $color);
    };

    $up = fn (array $p, float $z): array => [$p[0], $p[1] - $z];
    $quad([$up($pr->p(0, $d), $h), $up($pr->p($w, $d), $h), $pr->p($w, $d), $pr->p(0, $d)],
        [40, 90, 200], "facade {$w}t x {$storeys} niv.");
    $quad([$up($pr->p($w, $d), $h), $up($pr->p($w, 0), $h), $pr->p($w, 0), $pr->p($w, $d)],
        [30, 140, 70], "pignon {$d}t");
    $quad([$up($pr->p(0, $d), $h), $up($pr->p($w, $d), $h),
        $up($pr->p($w, $d / 2), $h + $rh), $up($pr->p(0, $d / 2), $h + $rh)],
        [200, 60, 40], 'toit');
    imagestring($im, 5, 6 * S, 4 * S,
        sprintf('%s | %dx%d px finaux, echelle x%d', $form, $cols * TILE, $rows * TILE, S),
        imagecolorallocate($im, 60, 60, 60));

    return $im;
}

/** Flat rectangles the artist paints; the composer maps them onto the planes. */
function templates(string $form): array
{
    [$w, $d, , , $storeys, $storeyF, $roofF] = FORMS[$form];
    $h = $storeys * $storeyF * S;
    $sizes = [
        'facade' => [$w * TILE * S * 2, $h],
        'pignon' => [$d * TILE * S * 2, $h],
        'toit' => [$w * TILE * S * 2, (int) hypot($d / 2 * TILE * S, $roofF * S)],
    ];
    $out = [];
    foreach ($sizes as $part => [$tw, $th]) {
        $im = newImage($tw, $th, [252, 252, 250]);
        $tileGuide = imagecolorallocate($im, 200, 210, 230);
        for ($x = 0; $x <= $tw; $x += TILE * S * 2) {
            imageline($im, $x, 0, $x, $th, $tileGuide);
        }
        $storeyGuide = imagecolorallocate($im, 230, 205, 205);
        for ($storey = 1; $storey < $storeys; $storey++) {
            $y = $th - $storey * $storeyF * S;
            imageline($im, 0, $y, $tw, $y, $storeyGuide);
        }
        imagerectangle($im, 0, 0, $tw - 1, $th - 1, imagecolorallocate($im, 140, 140, 140));
        imagestring($im, 5, 3 * S, 2 * S, "$form / $part {$tw}x{$th}",
            imagecolorallocate($im, 120, 120, 120));
        $out[$part] = $im;
    }

    return $out;
}

// ---------------------------------------------------------------- commands

function cmdSheet(): void
{
    $combos = [
        ['hutte_1x1', 'logs', 'thatch', null, 'simple', 'simple'],
        ['hutte_1x1', 'stone', 'tiles', null, 'simple', 'simple'],
        ['longere_2x1', 'swamp', 'thatch', null, 'simple', 'simple'],
        ['longere_2x1', 'colombage', 'tiles', null, 'simple', 'simple'],
        ['halle_2x2', 'columns', 'tiles', 'temple', 'aucune', 'simple'],
        ['maison_2x2', 'stone', 'tiles', null, 'simple', 'hautes'],
        ['halle_2x2', 'darkstone', 'slate', 'banque', 'arche', 'arche'],
        ['halle_2x2', 'plaster', 'tiles', null, 'double', 'simple'],
        ['maison_2x2', 'darkstone', 'slate', 'attique', 'double', 'arche', 'BANQUE'],
        ['tour_1x1', 'plaster', 'flat', 'flat', 'double', 'hautes'],
    ];
    $cell = 110;
    $pad = 8;
    $sheet = newImage(5 * (2 * $cell + $pad) + $pad, 2 * (2 * $cell + $pad) + $pad + 30, [70, 70, 70]);
    $label = imagecolorallocate($sheet, 230, 230, 230);
    foreach ($combos as $n => $c) {
        [$form, $fac, $roof, $shape, $door, $win] = $c;
        $img = build($form, $fac, $roof, $shape, seed: $n + 3, door: $door, windows: $win,
            label: $c[6] ?? '');
        $big = newImage(imagesx($img) * 2, imagesy($img) * 2);
        imagecopyresized($big, $img, 0, 0, 0, 0, imagesx($big), imagesy($big),
            imagesx($img), imagesy($img));
        $x = $pad + ($n % 5) * (2 * $cell + $pad);
        $y = $pad + intdiv($n, 5) * (2 * $cell + $pad);
        $bx = $x + intdiv(2 * $cell - imagesx($big), 2);
        $by = $y + 2 * $cell - imagesy($big);
        imagecopy($sheet, $big, $bx, $by, 0, 0, imagesx($big), imagesy($big));
        // canvas outline: anything touching it would be cut in game
        imagerectangle($sheet, $bx, $by, $bx + imagesx($big) - 1, $by + imagesy($big) - 1,
            imagecolorallocate($sheet, 110, 110, 110));
        imagestring($sheet, 3, $x + 4, $y + 2 * $cell + 2, "$form $fac/$roof", $label);
    }
    @mkdir(__DIR__ . '/samples');
    imagepng($sheet, __DIR__ . '/samples/sheet.png');
    echo __DIR__ . "/samples/sheet.png\n";
}

function main(array $argv): void
{
    $cmd = $argv[1] ?? 'list';
    if ($cmd === 'list') {
        echo 'forms   : ' . implode(', ', array_keys(FORMS)) . "\n";
        echo 'facades : ' . implode(', ', FACADES) . "\n";
        echo 'roofs   : ' . implode(', ', ROOFS) . "\n";

        return;
    }
    if ($cmd === 'blueprint') {
        $targets = ($argv[2] ?? 'all') === 'all' ? array_keys(FORMS) : [$argv[2]];
        @mkdir(__DIR__ . '/blueprints');
        @mkdir(__DIR__ . '/parts/templates', recursive: true);
        foreach ($targets as $form) {
            imagepng(blueprint($form), __DIR__ . "/blueprints/$form.png");
            foreach (templates($form) as $part => $im) {
                imagepng($im, __DIR__ . "/parts/templates/{$form}_$part.png");
            }
            echo "$form: blueprint + templates\n";
        }

        return;
    }
    if ($cmd === 'sheet') {
        cmdSheet();

        return;
    }
    if ($cmd === 'build') {
        $form = $argv[2] ?? '';
        if (!isset(FORMS[$form])) {
            fwrite(STDERR, "unknown form '$form'\n");
            exit(1);
        }
        $opts = ['facade' => 'stone', 'roof' => 'tiles', 'shape' => null,
            'facade-img' => null, 'roof-img' => null, 'seed' => '1', 'name' => 'composed',
            'door' => 'simple', 'door-pos' => 'centre', 'windows' => 'simple', 'label' => ''];
        $mirror = false;
        $args = array_slice($argv, 3);
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '--mirror') {
                $mirror = true;
            } else {
                $opts[ltrim($args[$i], '-')] = $args[++$i];
            }
        }
        $img = build($form, $opts['facade'], $opts['roof'], $opts['shape'],
            $opts['facade-img'], $opts['roof-img'], $mirror, (int) $opts['seed'],
            in_array($opts['door'], DOORS, true) ? $opts['door'] : 'simple',
            DOOR_POS[$opts['door-pos']] ?? 0.5,
            in_array($opts['windows'], WINDOWS, true) ? $opts['windows'] : 'simple',
            mb_substr(preg_replace('/[^A-Za-z0-9 \'\-|\n💰]/u', '', $opts['label']), 0, 34));
        @mkdir(__DIR__ . '/out');
        imagepng($img, __DIR__ . "/out/{$opts['name']}.png");
        cropTiles($img, __DIR__ . '/out', $opts['name']);
        if (function_exists('imagewebp')) {
            imagewebp($img, __DIR__ . "/out/{$opts['name']}.webp");
        }
        echo __DIR__ . "/out/{$opts['name']}.png\n";

        return;
    }
    echo "commands: list | blueprint all|<form> | build <form> [options] | sheet\n";
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    main($argv);
}
