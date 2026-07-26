<?php

namespace Tests\Various;

use App\Service\TileDialogMigrationService;
use Classes\Db;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Lecture d'un `map_dialogs.params`, la partie de la reprise où l'on
 * peut se tromper en silence.
 *
 * Trois formes se croisent en base, et la troisième est un piège. Une
 * alerte entre guillemets ; un code du catalogue ; ou du TEXTE, qui a
 * parfaitement le droit de contenir des virgules. L'ancien rendu
 * découpait sur la virgule et lisait « nom, avatar, code » — sept
 * textes de l'expérimental étaient débités ainsi.
 *
 * On ne découpe donc que si les morceaux désignent RÉELLEMENT un
 * dialogue connu. Une phrase reste une phrase.
 */
#[Group('dialogs')]
class TileDialogMigrationTest extends TestCase
{
    private ?TileDialogMigrationService $service = null;

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
            $GLOBALS['link'] = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $GLOBALS['link']->executeQuery('SELECT 1');

            $this->service = new TileDialogMigrationService(new Db());
        } catch (\Throwable $e) {
            $this->markTestSkipped('base indisponible : ' . $e->getMessage());
        }
    }

    public function testATextKeepsItsCommas(): void
    {
        [$text, $dialog] = $this->service->readParams(
            'Ne jamais tatouer un esprit troublé. L\'encre glisse, la Ligne se brise.'
        );

        $this->assertSame('', $dialog, 'ce n\'est pas un dialogue');
        $this->assertSame(
            'Ne jamais tatouer un esprit troublé. L\'encre glisse, la Ligne se brise.',
            $text,
            'la phrase arrive entière'
        );
    }

    public function testARawAlertBecomesPlainText(): void
    {
        [$text, $dialog] = $this->service->readParams('"Le ragoût \\"façon Argael\\" est servi."');

        $this->assertSame('', $dialog);
        $this->assertSame('Le ragoût "façon Argael" est servi.', $text, 'les guillemets échappés reviennent');
    }

    public function testAKnownCatalogNameBecomesAConversation(): void
    {
        $existing = (new Db())->exe('SELECT name FROM dialogs LIMIT 1');
        if (!$existing || !$existing->num_rows) {
            $this->markTestSkipped('catalogue de dialogues vide');
        }
        $name = (string) $existing->fetch_object()->name;

        [$text, $dialog] = $this->service->readParams($name);

        $this->assertSame($name, $dialog, 'un code connu désigne bien une conversation');
        $this->assertSame('', $text);
    }

    /**
     * Le format historique « nom,avatar,code » ne vaut que si le
     * troisième morceau désigne un dialogue : sinon c'est une phrase à
     * trois virgules, pas une déclaration.
     */
    public function testAThreePartValueIsOnlySplitWhenItsLastPartIsADialog(): void
    {
        [$text, $dialog] = $this->service->readParams('Les Géants observent, les Elfes doutent, les Olympiens marchandent');

        $this->assertSame('', $dialog);
        $this->assertStringContainsString('les Elfes doutent', $text, 'la phrase n\'est pas découpée');
    }

    /**
     * Les cocotiers ne sont pas une erreur de saisie mais une règle de
     * jeu à trancher : cocotier1 est déclaré récoltable quand
     * cocotier2 et cocotier3 sont déclarés solides, et leurs quelque
     * quatre-vingt-dix cases se disent toutes récoltables. Les
     * signaler comme une anomalie réclamerait une correction qui n'en
     * est pas une — et une correction automatique retirerait la récolte
     * à autant de cases.
     */
    public function testTheCoconutPalmsAreSetAsideUntilTheRuleIsSettled(): void
    {
        $this->assertTrue(TileDialogMigrationService::isSetAside('cocotier1'));
        $this->assertTrue(TileDialogMigrationService::isSetAside('cocotier3'));
        $this->assertFalse(
            TileDialogMigrationService::isSetAside('piedestal_pierre'),
            'ce qui est bien une erreur de saisie reste signalé'
        );
    }

    public function testAnUnknownDialogNameIsTreatedAsText(): void
    {
        [$text, $dialog] = $this->service->readParams('dialog');

        $this->assertSame('', $dialog, 'le placeholder « dialog » ne désigne aucun dialogue');
        $this->assertSame('dialog', $text);
    }
}
