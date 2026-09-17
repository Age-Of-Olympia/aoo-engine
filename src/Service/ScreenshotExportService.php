<?php

namespace App\Service;

/**
 * Rend une capture SVG lisible hors du jeu.
 *
 * Une capture sort du moteur avec des chemins d'images RELATIFS et sans CSS :
 * dans la page du jeu tout se résout, mais le fichier pris à part perd ses
 * assets et ses styles. Ce service comble les trois écarts, et il est partagé
 * par les deux consommateurs qui en ont besoin :
 *
 *  - scripts/tools/export_arene.php, pour le montage des GIF ;
 *  - admin/screenshots.php, dont l'aperçu passe par une balise <img>.
 *
 * Le cas <img> est le plus exigeant : le SVG y est parsé en XML STRICT et
 * tourne en mode statique sécurisé, où aucune ressource externe n'est chargée,
 * relative ou absolue. Il lui faut donc à la fois un XML valide et des images
 * en base64. Aucune dépendance à la base : l'export doit tourner sur un simple
 * dossier rsynchronisé.
 */
class ScreenshotExportService
{
    /**
     * Classes dont la règle CSS agit sur la géométrie ou le masquage : leurs
     * images ne peuvent pas passer par <use>, qui ne propage pas ces règles de
     * la même manière. Ne PAS élargir à "toute image portant une classe" : les
     * centaines d'images de grille portent class="case ", qui n'a aucune règle,
     * et les exclure ramènerait la déduplication à presque rien.
     */
    private const CLASSES_GEOMETRIQUES = ['avatar-shadow', 'transparent-gradient'];

    /** @var array<string, true> */
    private array $assetsManquants = [];

    public function __construct(private readonly string $docroot)
    {
    }

    /**
     * Capture autonome : XML valide, styles embarqués, images incluses.
     */
    public function autonomiser(string $svg): string
    {
        $svg = $this->preparerPourBundle($svg);

        return $this->inlinerImages($svg, $this->referencesExternes($svg));
    }

    /**
     * Capture pour un dossier "bundle", où les assets sont posés à côté : on
     * corrige le XML et on embarque le CSS, mais on garde les chemins relatifs.
     */
    public function preparerPourBundle(string $svg): string
    {
        return $this->injecterStyles($this->fusionnerClassesDupliquees($svg));
    }

    /**
     * Assets référencés mais introuvables sur disque, accumulés depuis la
     * construction. L'appelant décide quoi en dire.
     *
     * @return array<int, string>
     */
    public function assetsManquants(): array
    {
        return array_keys($this->assetsManquants);
    }

    /**
     * Fusionne les attributs class dupliqués sur un même élément.
     *
     * View compose parfois la classe en l'accolant à la fin de l'URL
     * (Classes/View.php:387, `$img .= '" class="transparent-gradient'`). Quand
     * l'élément porte déjà sa propre classe, la balise sort avec DEUX attributs
     * class. Un navigateur l'accepte en HTML, mais en XML strict
     * "Attribute class redefined" est FATALE : une seule balise fautive sur
     * treize cents suffit à ne rien afficher du tout. La cause est dans View ;
     * ceci n'en corrige que l'effet.
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
     * Injecte les règles CSS des classes que le SVG porte réellement.
     *
     * Elles sont relues dans css/main.css à chaque export plutôt que recopiées
     * ici, pour qu'une retouche du thème ne laisse pas les captures dériver.
     * Le cas qui se voit le plus est .avatar-shadow : en jeu l'ombre est
     * réduite à 35px et posée à 50 % d'opacité, sans la règle elle s'affiche en
     * carré plein de 50px sur chaque combattant.
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
                    // Sélecteur composé uniquement de classes connues du SVG.
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
     * Images externes référencées : href de fichier et fond de la balise
     * racine. Les renvois internes (#foregrounds123) et les data: déjà encodés
     * sont exclus.
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

        // Les deux motifs capturent [^"]+ et [^']+ : la référence ne peut pas
        // être vide, seuls les renvois internes et les data: sont à écarter.
        $refs = array_filter(
            $refs,
            static fn(string $r): bool => !str_starts_with($r, '#')
                && !str_starts_with($r, 'data:')
        );

        return array_values(array_unique($refs));
    }

    /**
     * Inline les images en base64 en n'encodant chaque asset qu'UNE fois.
     *
     * Les tuiles passent par un <defs> référencé en <use> : une frame d'arène
     * compte environ treize cents références pour une quarantaine d'assets
     * distincts, soit 16 Mo en inlining naïf contre 0,5 Mo ici. Les images dont
     * une classe touche à la géométrie gardent leur forme d'origine avec leur
     * base64 en propre, elles ne sont qu'une vingtaine.
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

            // Un identifiant par couple (asset, taille) : deux définitions ne
            // peuvent pas partager le même id, le <use> ne saurait laquelle
            // viser.
            $idDef = $asset['id'] . str_replace(['"', ' ', '='], '', $taille);

            $defs[$idDef] = '<image id="' . $idDef . '"' . $taille . ' href="' . $asset['data'] . '"/>';

            // Tout est reporté, SAUF ce qui appartient à la définition : href
            // porte le base64, et width/height y sont déjà figés (sur un <use>
            // visant une <image>, ils ne s'appliqueraient d'ailleurs pas).
            //
            // Liste d'exclusion et non liste blanche : celle-ci retenait id, x,
            // y, class et les data-*, donc laissait tomber style="opacity: …",
            // que View pose sur les trois calques de décor (Classes/View.php,
            // gif 0.3 / webp 0.5 / png 1). Les calques translucides sortaient
            // opaques dans les captures autonomes. Une liste blanche perd
            // silencieusement chaque attribut ajouté plus tard à View.
            //
            // Les paires sont réémises une à une plutôt que découpées dans la
            // chaîne : $attrs contient le "/" final des balises auto-fermantes
            // et une espace de tête variable, deux pièges à XML invalide.
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

            // Sans balise <svg> le remplacement ne trouve rien et rendait le
            // bloc silencieusement : chaque <use> aurait alors pointé vers une
            // définition absente, donc une image vide. On préfixe plutôt que de
            // perdre les données.
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
