<?php

namespace Tests\Various;

use App\Service\DialogService;
use Classes\Db;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Un dialogue porte deux réglages distincts, et ils le sont à dessein :
 *
 *  - sa NATURE dit ce qu'on en fait. On LIT une pancarte ; on
 *    S'ADRESSE à un marchand. Le verbe du bouton et la formule du
 *    refus en découlent.
 *  - sa PORTÉE dit s'il faut s'approcher. Un panneau se lit de loin,
 *    une échoppe demande qu'on s'y présente.
 *
 * Les croiser serait tentant mais faux : on peut vouloir une
 * inscription qu'il faut approcher pour déchiffrer, ou un crieur public
 * qu'on entend de loin.
 *
 * Le défaut, lui, n'est pas neutre : tout ce qui existait avant ces
 * colonnes doit continuer de se comporter comme avant — interactif, et
 * de près.
 */
#[Group('dialogs')]
class DialogTraitsTest extends TestCase
{
    private const FIXTURE = 'test_traits_dialog';

    private ?DialogService $service = null;

    protected function setUp(): void
    {
        /* Classes\Db passe par $GLOBALS['link'] : même amorçage que la
         * fixture des tests hérités, sans quoi le service ne trouve pas
         * de connexion. */
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
            $GLOBALS['link'] = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $GLOBALS['link']->executeQuery('SELECT 1');

            $this->service = new DialogService();
            (new Db())->exe('SELECT kind FROM dialogs LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('base ou colonnes de dialogue indisponibles : ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->service !== null) {
            (new Db())->exe('DELETE FROM dialogs WHERE name = ?', [self::FIXTURE]);
            DialogService::clearCache();
        }
    }

    private function save(array $fields): void
    {
        $this->service->saveGameDialog(
            self::FIXTURE,
            [['id' => 'bonjour', 'text' => 'Route de Thèbes', 'options' => []]],
            $fields
        );
        DialogService::clearCache();
    }

    public function testAnUnknownDialogBehavesLikeBeforeTheseColumnsExisted(): void
    {
        $traits = $this->service->traits('dialogue_qui_nexiste_pas');

        $this->assertSame(DialogService::KIND_INTERACTIVE, $traits['kind'], 'on s\'y adresse');
        $this->assertFalse($traits['readableFromAfar'], 'et il faut être à côté');
    }

    public function testASignIsReadAndCanBeReadFromAfar(): void
    {
        $this->save(['kind' => DialogService::KIND_INFORMATIVE, 'readable_from_afar' => true]);

        $traits = $this->service->traits(self::FIXTURE);

        $this->assertSame(DialogService::KIND_INFORMATIVE, $traits['kind']);
        $this->assertTrue($traits['readableFromAfar']);
    }

    /**
     * Les deux réglages ne sont pas deux noms d'une même chose : une
     * inscription peut demander qu'on s'approche pour la déchiffrer.
     */
    public function testNatureAndRangeAreIndependent(): void
    {
        $this->save(['kind' => DialogService::KIND_INFORMATIVE, 'readable_from_afar' => false]);

        $traits = $this->service->traits(self::FIXTURE);

        $this->assertSame(DialogService::KIND_INFORMATIVE, $traits['kind'], 'on la lit');
        $this->assertFalse($traits['readableFromAfar'], 'mais de près');
    }

    public function testSavingKeepsTheShopBehaviourByDefault(): void
    {
        $this->save([]);

        $traits = $this->service->traits(self::FIXTURE);

        $this->assertSame(DialogService::KIND_INTERACTIVE, $traits['kind']);
        $this->assertFalse($traits['readableFromAfar']);
    }
}
