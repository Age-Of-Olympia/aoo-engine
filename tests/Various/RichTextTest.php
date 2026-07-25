<?php

namespace Tests\Various;

use Classes\Str;
use PHPUnit\Framework\TestCase;

/**
 * Str::richText — le rendu des textes libres écrits par les joueurs
 * (message du jour, histoire) et par les admins (texte d'un type de
 * bâtiment).
 *
 * Ces textes étaient affichés bruts : n'importe quel joueur pouvait
 * faire exécuter du script chez tous ceux qui l'observaient, de façon
 * persistante. La règle posée ici est « échapper d'abord, ré-autoriser
 * ensuite » : la mise en forme simple passe, et RIEN d'autre — ce qui
 * n'est pas dans la liste blanche doit finir en texte visible, pas en
 * balise. Ce test est la preuve de cette asymétrie.
 */
class RichTextTest extends TestCase
{
    public function testSimpleFormattingIsKept(): void
    {
        $this->assertSame(
            'Salut <b>toi</b> et <i>vous</i>',
            Str::richText('Salut <b>toi</b> et <i>vous</i>')
        );
    }

    public function testSelfClosingBreakIsKept(): void
    {
        $this->assertSame('a<br>b<br>c', Str::richText('a<br>b<br/>c'));
    }

    public function testScriptIsNeutralisedIntoVisibleText(): void
    {
        $out = Str::richText('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testEventHandlerCannotRideOnAWhitelistedTag(): void
    {
        $out = Str::richText('<b onclick="alert(1)">gras</b>');

        /* La balise porte un attribut : elle ne correspond à aucun motif
         * de la liste blanche. Le gestionnaire d'évènement subsiste dans
         * la page — mais comme TEXTE échappé, pas comme balise : il n'y
         * a aucun « < » non échappé pour le porter. */
        $this->assertStringNotContainsString('<b', $out);
        $this->assertStringContainsString('&lt;b onclick=', $out);
        $this->assertSame('', strip_tags($out) === $out ? '' : 'du balisage a survécu');
    }

    public function testImageWithErrorHandlerIsNeutralised(): void
    {
        $out = Str::richText('<img src=x onerror="fetch(1)">');

        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('&lt;img', $out);
    }

    public function testLinksAreNotAllowed(): void
    {
        $out = Str::richText('<a href="javascript:alert(1)">clic</a>');

        $this->assertStringNotContainsString('<a href', $out);
        $this->assertStringContainsString('&lt;a href=', $out);
    }

    public function testAnUnclosedTagCannotBleedIntoTheRestOfThePage(): void
    {
        $this->assertSame('<b>gras jamais refermé</b>', Str::richText('<b>gras jamais refermé'));
    }

    public function testAnOrphanClosingTagIsDropped(): void
    {
        $this->assertSame('texte', Str::richText('texte</b>'));
    }

    public function testBadlyNestedTagsComeBackWellNested(): void
    {
        $this->assertSame('<b>a<i>b</i></b>', Str::richText('<b>a<i>b</b>'));
    }

    public function testNewlinesBecomeBreaks(): void
    {
        $this->assertStringContainsString('<br />', Str::richText("une\ndeux"));
    }

    public function testQuotesAndAmpersandsSurviveAsText(): void
    {
        $out = Str::richText('Tom & "Jerry"');

        $this->assertStringContainsString('&amp;', $out);
        $this->assertStringNotContainsString('<', str_replace('<br />', '', $out));
    }

    public function testNullIsAnEmptyString(): void
    {
        $this->assertSame('', Str::richText(null));
    }
}
