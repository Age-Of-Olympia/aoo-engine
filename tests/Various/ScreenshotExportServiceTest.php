<?php

namespace Tests\Various;

use App\Service\ScreenshotExportService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Épingle les garanties de ScreenshotExportService.
 *
 * Une capture doit rester lisible hors du jeu : parsable en XML STRICT, sans
 * ressource externe, et d'un poids tenable. Chaque test ci-dessous fige un
 * défaut qui a réellement cassé les exports, pas une hypothèse.
 */
class ScreenshotExportServiceTest extends TestCase
{
    private string $docroot;

    protected function setUp(): void
    {
        $this->docroot = sys_get_temp_dir() . '/aoo-export-' . uniqid();

        mkdir($this->docroot . '/img/tiles', 0777, true);
        mkdir($this->docroot . '/css', 0777, true);

        // PNG 1x1 valide : le service lit le fichier et l'encode réellement.
        file_put_contents(
            $this->docroot . '/img/tiles/herbe.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        );

        file_put_contents($this->docroot . '/css/main.css', <<<CSS
            .avatar-shadow { width: 35px; opacity: 0.5; }
            .blink { animation: blinker 1.5s linear infinite; }
            @keyframes blinker { 50% { opacity: 0; } }
            .jamais-utilisee { color: red; }
            CSS);
    }

    protected function tearDown(): void
    {
        foreach (['/img/tiles/herbe.png', '/css/main.css'] as $fichier) {
            @unlink($this->docroot . $fichier);
        }
        foreach (['/img/tiles', '/img', '/css', ''] as $dossier) {
            @rmdir($this->docroot . $dossier);
        }
    }

    private function service(): ScreenshotExportService
    {
        return new ScreenshotExportService($this->docroot);
    }

    /**
     * Le défaut d'origine : View composait la classe en l'écrivant dans l'URL,
     * si bien qu'un élément portant déjà la sienne sortait avec DEUX attributs
     * class. Fatal en XML strict, donc plus rien ne s'affichait.
     */
    #[Group('screenshot-export')]
    public function testFusionneLesAttributsClassDupliques(): void
    {
        $svg = '<image href="a.png" class="transparent-gradient" class="avatar-shadow" />';

        $sortie = $this->service()->fusionnerClassesDupliquees($svg);

        $this->assertSame(1, substr_count($sortie, ' class="'), 'un seul attribut class doit subsister');
        $this->assertStringContainsString('transparent-gradient', $sortie);
        $this->assertStringContainsString('avatar-shadow', $sortie);
    }

    #[Group('screenshot-export')]
    public function testLaisseIntacteUneBaliseAClasseUnique(): void
    {
        $svg = '<image href="a.png" class="case go" />';

        $this->assertSame($svg, $this->service()->fusionnerClassesDupliquees($svg));
    }

    /**
     * Les renvois internes (<use href="#id">) et les data: déjà encodés ne sont
     * pas des fichiers : tenter de les résoudre les signalerait à tort comme
     * manquants.
     */
    #[Group('screenshot-export')]
    public function testReferencesExternesEcarteRenvoisInternesEtDataUri(): void
    {
        $svg = '<image href="img/tiles/herbe.png"/><use href="#foregrounds42"/>'
            . '<image href="data:image/png;base64,AAAA"/><image href="img/tiles/herbe.png"/>';

        $this->assertSame(['img/tiles/herbe.png'], $this->service()->referencesExternes($svg));
    }

    /**
     * Le nerf de la guerre : une frame d'arène compte environ treize cents
     * références pour une quarantaine d'assets. Sans déduplication elle pèse
     * 16 Mo au lieu de 0,5.
     */
    #[Group('screenshot-export')]
    public function testNEncodeChaqueAssetQuUneSeuleFois(): void
    {
        $svg = '<svg>' . str_repeat('<image width="50" height="50" href="img/tiles/herbe.png"/>', 20) . '</svg>';

        $sortie = $this->service()->inlinerImages($svg, ['img/tiles/herbe.png']);

        $this->assertSame(1, substr_count($sortie, 'data:image'), 'un seul encodage base64');
        $this->assertSame(20, substr_count($sortie, '<use '), 'chaque tuile devient un <use>');
        $this->assertSame(1, substr_count($sortie, '<defs>'));
    }

    /**
     * .avatar-shadow porte une règle géométrique (width: 35px) que <use> ne
     * propage pas de la même manière : ces images gardent leur forme d'origine.
     */
    #[Group('screenshot-export')]
    public function testLesImagesAClasseGeometriqueNePassentPasParUse(): void
    {
        $svg = '<image width="50" height="50" href="img/tiles/herbe.png" class="avatar-shadow"/>';

        $sortie = $this->service()->inlinerImages($svg, ['img/tiles/herbe.png']);

        $this->assertStringNotContainsString('<use ', $sortie);
        $this->assertStringContainsString('class="avatar-shadow"', $sortie);
        $this->assertStringContainsString('data:image', $sortie);
    }

    /**
     * class="case " est portée par des centaines d'images de grille et n'a
     * aucune règle CSS : l'exclure de la déduplication la réduirait à néant.
     */
    #[Group('screenshot-export')]
    public function testUneClasseSansPorteeGeometriqueResteDeduplicable(): void
    {
        $svg = '<svg>' . str_repeat('<image width="50" height="50" href="img/tiles/herbe.png" class="case "/>', 5) . '</svg>';

        $sortie = $this->service()->inlinerImages($svg, ['img/tiles/herbe.png']);

        $this->assertSame(5, substr_count($sortie, '<use '));
        $this->assertSame(1, substr_count($sortie, 'data:image'));
        $this->assertStringContainsString('class="case "', $sortie, 'la classe est reportee sur le <use>');
    }

    /**
     * View pose l'opacité en style inline sur les calques de décor (gif 0.3,
     * webp 0.5, png 1). Reportée sur le <use>, elle vaut pour l'image
     * référencée ; oubliée, les calques translucides sortent opaques.
     */
    #[Group('screenshot-export')]
    public function testReporteLeStyleInlineSurLeUse(): void
    {
        $svg = '<svg><image width="50" height="50" x="10" y="20" href="img/tiles/herbe.png"'
            . ' style="opacity: 0.3;" pointer-events="none" data-coords="1,2"/></svg>';

        $sortie = $this->service()->inlinerImages($svg, ['img/tiles/herbe.png']);

        $this->assertStringContainsString('<use ', $sortie);
        $this->assertStringContainsString('style="opacity: 0.3;"', $sortie);
        $this->assertStringContainsString('pointer-events="none"', $sortie);
        $this->assertStringContainsString('data-coords="1,2"', $sortie);
        $this->assertSame(0, preg_match('/<use[^>]*\shref="img\//', $sortie), 'le href du <use> vise la definition');
        $this->assertSame(0, preg_match('/<use[^>]*\swidth=/', $sortie), 'la taille reste dans le <defs>');
    }

    /**
     * Deux calques du même asset ne diffèrent que par leur opacité : ils
     * partagent une définition et portent chacun son style.
     */
    #[Group('screenshot-export')]
    public function testDeuxCalquesPartagentLaDefinitionEtGardentLeurOpacite(): void
    {
        $svg = '<svg>'
            . '<image width="50" height="50" href="img/tiles/herbe.png" style="opacity: 0.3;"/>'
            . '<image width="50" height="50" href="img/tiles/herbe.png" style="opacity: 0.5;"/>'
            . '</svg>';

        $sortie = $this->service()->inlinerImages($svg, ['img/tiles/herbe.png']);

        $this->assertSame(1, substr_count($sortie, 'data:image'), 'une seule definition partagee');
        $this->assertStringContainsString('style="opacity: 0.3;"', $sortie);
        $this->assertStringContainsString('style="opacity: 0.5;"', $sortie);
    }

    /**
     * Une balise auto-fermante finit par "/" et peut n'avoir aucune espace de
     * tête exploitable : recopier la chaîne d'attributs telle quelle produisait
     * un "//>" ou un "…"x=" collé, fatal en XML strict.
     */
    #[Group('screenshot-export')]
    public function testLeUseResteDuXmlValideQuelsQueSoientLesAttributs(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image href="img/tiles/herbe.png" width="50" height="50" style="opacity: 0.3;" />'
            . '</svg>';

        $sortie = $this->service()->inlinerImages($svg, ['img/tiles/herbe.png']);

        $this->assertSame(0, substr_count($sortie, '//>'));

        $precedent = libxml_use_internal_errors(true);
        $charge    = simplexml_load_string($sortie);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        $this->assertNotFalse($charge, 'la sortie doit se parser en XML strict');
    }

    /**
     * Le bloc <defs> s'insère après la balise racine. Quand elle manque, le
     * remplacement ne trouvait rien et le bloc était perdu en silence : chaque
     * <use> pointait alors vers une définition absente, donc une image vide.
     */
    #[Group('screenshot-export')]
    public function testNePerdJamaisLesDefinitionsFauteDeBaliseRacine(): void
    {
        $svg = '<image width="50" height="50" href="img/tiles/herbe.png"/>';

        $sortie = $this->service()->inlinerImages($svg, ['img/tiles/herbe.png']);

        $this->assertSame(1, substr_count($sortie, '<defs>'));
        $this->assertSame(1, substr_count($sortie, 'data:image'));

        preg_match('/<image id="([^"]+)"/', $sortie, $definition);
        preg_match('/<use href="#([^"]+)"/', $sortie, $usage);
        $this->assertSame($definition[1], $usage[1], 'le <use> doit viser une definition presente');
    }

    #[Group('screenshot-export')]
    public function testUnAssetIntrouvableEstSignaleEtLaisseIntact(): void
    {
        $service = $this->service();

        $sortie = $service->inlinerImages('<image href="img/tiles/absente.png"/>', ['img/tiles/absente.png']);

        $this->assertSame(['img/tiles/absente.png'], $service->assetsManquants());
        $this->assertStringContainsString('img/tiles/absente.png', $sortie);
    }

    /**
     * Sans le CSS embarqué, une capture ouverte seule perd ses styles : les
     * ombres, invisibles en jeu, s'affichent en carrés pleins.
     */
    #[Group('screenshot-export')]
    public function testInjecteLesReglesDesSeulesClassesPresentes(): void
    {
        $svg = '<svg><image class="avatar-shadow"/></svg>';

        $sortie = $this->service()->injecterStyles($svg);

        $this->assertStringContainsString('<style>', $sortie);
        $this->assertStringContainsString('width: 35px', $sortie);
        $this->assertStringNotContainsString('jamais-utilisee', $sortie, 'le CSS inutile n est pas embarque');
    }

    #[Group('screenshot-export')]
    public function testEmbarqueLesKeyframesDesAnimationsReferencees(): void
    {
        $sortie = $this->service()->injecterStyles('<svg><image class="blink"/></svg>');

        $this->assertStringContainsString('@keyframes blinker', $sortie);
    }

    /**
     * La garantie de bout en bout : une capture autonome doit être du XML
     * valide et ne dépendre d'aucun fichier extérieur. C'est ce que réclame la
     * balise <img> de l'aperçu d'administration, qui n'affiche rien sinon.
     */
    #[Group('screenshot-export')]
    public function testUneCaptureAutonomeEstDuXmlValideSansReferenceExterne(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image width="50" height="50" href="img/tiles/herbe.png" class="case "/>'
            . '<image width="50" height="50" href="img/tiles/herbe.png" class="transparent-gradient" class="avatar-shadow"/>'
            . '</svg>';

        $sortie = $this->service()->autonomiser($svg);

        $this->assertSame(0, preg_match('#href="img/#', $sortie), 'aucune reference externe ne subsiste');

        $precedent = libxml_use_internal_errors(true);
        $charge    = simplexml_load_string($sortie);
        $erreurs   = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        $this->assertNotFalse($charge, 'la capture doit se parser en XML strict');
        $this->assertSame([], $erreurs, 'aucune erreur XML ne doit subsister');
    }
}
