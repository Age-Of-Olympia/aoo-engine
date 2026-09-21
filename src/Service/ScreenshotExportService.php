<?php

namespace App\Service;

/**
 * Makes an SVG capture readable outside the game.
 *
 * A capture leaves the engine with relative image paths and no CSS. This
 * service fixes the XML, embeds the styles and, when asked, inlines the images.
 * Shared by scripts/tools/export_arene.php (GIF montage) and
 * admin/screenshots.php (preview through <img>, which parses strict XML and
 * loads no external resource). No database access: the export runs on a plain
 * rsynced directory.
 */
class ScreenshotExportService
{
    /**
     * Classes whose CSS rule affects geometry or masking: their images cannot go
     * through <use>, which does not propagate those rules the same way. Do not
     * widen to "any image with a class": the grid images carry class="case ",
     * which has no rule, and excluding them would defeat the deduplication.
     */
    private const CLASSES_GEOMETRIQUES = ['avatar-shadow', 'transparent-gradient'];

    /** @var array<string, true> */
    private array $assetsManquants = [];

    public function __construct(private readonly string $docroot)
    {
    }

    /** Self-contained capture: valid XML, embedded styles, inlined images. */
    public function autonomiser(string $svg): string
    {
        $svg = $this->preparerPourBundle($svg);

        return $this->inlinerImages($svg, $this->referencesExternes($svg));
    }

    /**
     * Capture for a "bundle" directory with the assets copied alongside: XML
     * fixed and CSS embedded, paths kept relative.
     */
    public function preparerPourBundle(string $svg): string
    {
        return $this->injecterStyles($this->fusionnerClassesDupliquees($svg));
    }

    /**
     * Referenced assets missing on disk, accumulated since construction.
     *
     * @return array<int, string>
     */
    public function assetsManquants(): array
    {
        return array_keys($this->assetsManquants);
    }

    /**
     * Merges duplicated class attributes on one element. Browsers tolerate them
     * in HTML; in strict XML "Attribute class redefined" is fatal for the whole
     * document.
     */
    public function fusionnerClassesDupliquees(string $svg): string
    {
        return preg_replace_callback('/<[a-z]+\b[^>]*>/i', function (array $m): string {
            $balise = $m[0];

            if (preg_match_all('/\sclass="([^"]*)"/i', $balise, $classes) < 2) {
                return $balise;
            }

            $fusion = implode(' ', array_unique(
                preg_split('/\s+/', trim(implode(' ', $classes[1]))) ?: []
            ));

            $premier = true;

            return preg_replace_callback('/\sclass="[^"]*"/i', function () use (&$premier, $fusion): string {
                if ($premier) {
                    $premier = false;
                    return ' class="' . $fusion . '"';
                }
                return '';
            }, $balise) ?? $balise;
        }, $svg) ?? $svg;
    }

    /**
     * Embeds the CSS rules of the classes the SVG actually uses, read from
     * css/main.css at export time so a theme change does not leave the captures
     * behind (e.g. .avatar-shadow: 35px at 50% opacity, else a full square).
     */
    public function injecterStyles(string $svg): string
    {
        $cssFile = $this->docroot . '/css/main.css';
        if (!is_readable($cssFile)) {
            return $svg;
        }

        $css     = (string) file_get_contents($cssFile);
        $classes = $this->classesUtilisees($svg);

        if ($classes === []) {
            return $svg;
        }

        $regles = [];

        if (preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $blocs, PREG_SET_ORDER)) {
            foreach ($blocs as $bloc) {
                foreach (explode(',', $bloc[1]) as $selecteur) {
                    $selecteur = trim($selecteur);
                    if ($selecteur === '' || !str_starts_with($selecteur, '.')) {
                        continue;
                    }
                    // Selector made only of classes present in the SVG.
                    $morceaux = array_filter(explode('.', $selecteur));
                    if ($morceaux !== [] && array_diff($morceaux, $classes) === []) {
                        $regles[] = $selecteur . ' {' . trim($bloc[2]) . '}';
                        break;
                    }
                }
            }
        }

        foreach ($regles as $regle) {
            if (preg_match('/animation:\s*([A-Za-z0-9_-]+)/', $regle, $nom)
                && preg_match('/@keyframes\s+' . preg_quote($nom[1], '/') . '\s*\{(?:[^{}]|\{[^}]*\})*\}/', $css, $kf)) {
                $regles[] = $kf[0];
            }
        }

        if ($regles === []) {
            return $svg;
        }

        $style = '<style><![CDATA[' . "\n" . implode("\n", array_unique($regles)) . "\n" . ']]></style>';

        return preg_replace('/(<svg[^>]*>)/i', '$1' . $style, $svg, 1) ?? $svg;
    }

    /**
     * External image references: file hrefs and the root tag background.
     * Internal references (#id) and data: URIs are excluded.
     *
     * @return array<int, string>
     */
    public function referencesExternes(string $svg): array
    {
        $refs = [];

        if (preg_match_all('/href="([^"]+)"/i', $svg, $m)) {
            $refs = $m[1];
        }
        if (preg_match("/background:\s*url\('([^']+)'\)/i", $svg, $m)) {
            $refs[] = $m[1];
        }

        // Both patterns capture a non-empty string: only internal references
        // and data: URIs are filtered out.
        $refs = array_filter(
            $refs,
            static fn(string $r): bool => !str_starts_with($r, '#')
                && !str_starts_with($r, 'data:')
        );

        return array_values(array_unique($refs));
    }

    /**
     * Inlines images as base64, encoding each asset once. Tiles go through a
     * <defs> referenced by <use>: an arena frame holds ~1300 references for ~40
     * assets, 16 MB naive versus 0.5 MB here. Images with a geometric class keep
     * their own <image> and base64.
     *
     * @param array<int, string> $refs
     */
    public function inlinerImages(string $svg, array $refs): string
    {
        $encodes = [];
        $index   = 0;

        foreach ($refs as $ref) {
            $data = $this->encoderBase64($this->docroot . '/' . ltrim($ref, '/'));
            if ($data === null) {
                $this->assetsManquants[$ref] = true;
                continue;
            }
            $encodes[$ref] = ['data' => $data, 'id' => 'asset-' . $index++];
        }

        $defs = [];

        $svg = preg_replace_callback('/<image\b([^>]*)\/?>/i', function (array $m) use ($encodes, &$defs): string {
            $attrs = $m[1];

            if (preg_match('/\bclass="([^"]*)"/i', $attrs, $c)
                && array_intersect(preg_split('/\s+/', trim($c[1])) ?: [], self::CLASSES_GEOMETRIQUES) !== []) {
                return $this->remplacerHref($m[0], $encodes);
            }

            if (!preg_match('/\bhref="([^"]+)"/i', $attrs, $h) || !isset($encodes[$h[1]])) {
                return $m[0];
            }

            $asset  = $encodes[$h[1]];
            $taille = '';
            foreach (['width', 'height'] as $dimension) {
                if (preg_match('/\b' . $dimension . '="([^"]*)"/i', $attrs, $d)) {
                    $taille .= ' ' . $dimension . '="' . $d[1] . '"';
                }
            }

            // One id per (asset, size) pair: two definitions cannot share an id.
            $idDef = $asset['id'] . str_replace(['"', ' ', '='], '', $taille);

            $defs[$idDef] = '<image id="' . $idDef . '"' . $taille . ' href="' . $asset['data'] . '"/>';

            // Everything is carried over except what belongs to the definition
            // (href holds the base64, width/height are fixed there). Exclusion
            // list rather than allow list, so an attribute added to View later
            // (style="opacity: …" on scenery layers) is not silently dropped.
            // Pairs are re-emitted one by one: $attrs holds the trailing "/" of
            // self-closing tags and a variable leading space.
            $garde = '';
            if (preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)="([^"]*)"/', $attrs, $paires, PREG_SET_ORDER)) {
                foreach ($paires as [, $nom, $valeur]) {
                    if (in_array(strtolower($nom), ['href', 'width', 'height'], true)) {
                        continue;
                    }
                    $garde .= ' ' . $nom . '="' . $valeur . '"';
                }
            }

            return '<use href="#' . $idDef . '"' . $garde . '/>';
        }, $svg) ?? $svg;

        if (preg_match("/background:\s*url\('([^']+)'\)/i", $svg, $m) && isset($encodes[$m[1]])) {
            $svg = str_replace($m[1], $encodes[$m[1]]['data'], $svg);
        }

        if ($defs !== []) {
            $bloc    = '<defs>' . implode('', $defs) . '</defs>';
            $injecte = preg_replace('/(<svg[^>]*>)/i', '$1' . $bloc, $svg, 1);

            // Without a <svg> tag the replacement finds nothing and every <use>
            // would point at a missing definition: prefix the block instead.
            $svg = ($injecte !== null && $injecte !== $svg) ? $injecte : $bloc . $svg;
        }

        return $svg;
    }

    /**
     * @return array<int, string>
     */
    private function classesUtilisees(string $svg): array
    {
        $classes = [];

        if (preg_match_all('/class="([^"]*)"/i', $svg, $m)) {
            foreach ($m[1] as $attribut) {
                foreach (preg_split('/\s+/', trim($attribut)) ?: [] as $classe) {
                    if ($classe !== '') {
                        $classes[$classe] = true;
                    }
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * @param array<string, array{data: string, id: string}> $encodes
     */
    private function remplacerHref(string $balise, array $encodes): string
    {
        if (!preg_match('/\bhref="([^"]+)"/i', $balise, $h) || !isset($encodes[$h[1]])) {
            return $balise;
        }

        return str_replace($h[1], $encodes[$h[1]]['data'], $balise);
    }

    private function encoderBase64(string $chemin): ?string
    {
        if (!is_readable($chemin)) {
            return null;
        }

        $mime = @mime_content_type($chemin) ?: 'image/png';
        $data = @file_get_contents($chemin);

        return $data === false ? null : 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
