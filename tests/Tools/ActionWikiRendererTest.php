<?php

namespace Tests\Tools;

use App\Service\Wiki\ActionWikiRenderer;
use App\Service\Wiki\WikiRendererRegistry;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La fiche wiki des actions : générée depuis les tableaux de
 * l'exporter (même processus que l'export, autre format), coûts et
 * visées dérivés des conditions — le wiki ne peut pas mentir.
 */
#[Group('items-golden-master')]
class ActionWikiRendererTest extends LegacyPlayerFixtureTestCase
{
    public function testTheSheetIsDokuWikiWithDerivedFacts(): void
    {
        $registry = new WikiRendererRegistry();
        $this->assertArrayHasKey('action', $registry->titles(), 'la famille action est enregistrée');

        $sheet = (new ActionWikiRenderer())->render();

        $this->assertStringContainsString('====== Actions ======', $sheet, 'en-tête DokuWiki');
        $this->assertStringContainsString('^ Nom ^ Type ^ Visée ^ Coût ^ Description ^', $sheet, 'tableaux DokuWiki');

        if (str_contains($sheet, '| Consommer |')) {
            $this->assertMatchesRegularExpression(
                '/\| Consommer \|[^|]*\| Soi-même \|/',
                $sheet,
                "la visée 'self' de consommer est dérivée de sa condition TargetType"
            );
        }
    }
}
