<?php

namespace Tests\Action\Combat;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use App\Entity\Action;
use App\Factory\ActionFactory;
use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use App\Service\Action\ActionEventLogger;
use App\Service\ActionExecutorService;
use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A passive writes in the journal: a public line when it takes effect during
 * an action, a personal line when the player learns it.
 */
#[Group('passives')]
#[Group('action-combat')]
class PassiveJournalTest extends LegacyPlayerFixtureTestCase
{
    /** @var int[] action_passives ids created here, removed in tearDown */
    private array $passiveIds = [];

    /** @var int[] players granted a catalog passive here (players_passives has no key onto players) */
    private array $holderIds = [];

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->holderIds !== []) {
            $this->link->executeStatement('DELETE FROM players_passives WHERE player_id IN (?)', [$this->holderIds], [ArrayParameterType::INTEGER]);
        }
        // Journal rows go with the fixture players (RESTRICT keys onto players).
        if ($this->link !== null && $this->passiveIds !== []) {
            $this->link->executeStatement('DELETE FROM players_passives WHERE passive_id IN (?)', [$this->passiveIds], [ArrayParameterType::INTEGER]);
            $this->link->executeStatement('DELETE FROM action_passives WHERE id IN (?)', [$this->passiveIds], [ArrayParameterType::INTEGER]);
        }
        parent::tearDown();
    }

    /**
     * A fixed +2 melee-roll passive with both journal lines.
     *
     * @return array{0: int, 1: string} id, name
     */
    private function createPassive(?string $triggerTemplate = '{actor} a profité de {passive} contre {target}.'): array
    {
        $name = 'test_' . bin2hex(random_bytes(3));
        $this->link->insert('action_passives', [
            'name' => $name, 'traits' => json_encode(['cc']), 'type' => 'att',
            'carac' => 'fixed', 'value' => 2, 'level' => 1,
            'display_name' => 'Talent', 'text' => 'test',
            'trigger_template' => $triggerTemplate,
            'learn_template' => 'Vous avez appris {passive}.',
        ]);
        $id = (int) $this->link->lastInsertId();
        $this->passiveIds[] = $id;
        EntityManagerFactory::getEntityManager()->clear();

        return [$id, $name];
    }

    /**
     * The actor, holding the test passive, attacks in melee and the journal is written.
     *
     * @return array{0: \Classes\Player, 1: \Classes\Player} actor, target
     */
    private function attackWithPassive(?string $triggerTemplate): array
    {
        $actor = $this->createRealPlayer('PjAttacker');
        $target = $this->createRealPlayer('PjTarget');
        $this->movePlayerTo($target->id, 0, 1);
        $target = PlayerFactory::legacy($target->id);
        [$passiveId] = $this->createPassive($triggerTemplate);
        $this->link->insert('players_passives', ['player_id' => $actor->getId(), 'passive_id' => $passiveId]);
        EntityManagerFactory::getEntityManager()->clear();

        $actor->getCoords();
        $target->getCoords();
        $actor->get_caracs();
        $target->get_caracs();
        $this->snapshotBloodAt((int) $target->data->coords_id);
        $this->snapshotBloodAt((int) $actor->data->coords_id);

        $action = ActionFactory::getAction('melee');
        if (!$action instanceof Action) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        $results = (new ActionExecutorService($action, $actor, $target))->executeAction();
        ActionEventLogger::write($action, $results, $actor, $target);

        return [$actor, $target];
    }

    public function testRollPassiveWritesAPublicLineWithTheAttack(): void
    {
        [$actor, $target] = $this->attackWithPassive('{actor} a profité de {passive} contre {target}.');

        // The roll bonus applies hit or miss: the line is there either way.
        $row = $this->link->fetchAssociative(
            "SELECT player_id, target_id, text FROM players_logs WHERE player_id = ? AND type = 'passive'",
            [$actor->getId()]
        );
        $this->assertNotFalse($row, 'the passive that took effect leaves a public line');
        $this->assertSame((int) $target->getId(), (int) $row['target_id']);
        $this->assertSame($actor->data->name . ' a profité de Talent contre ' . $target->data->name . '.', $row['text']);
    }

    public function testEmptyTriggerTextWritesNoLine(): void
    {
        [$actor] = $this->attackWithPassive(null);

        $this->assertFalse($this->link->fetchOne(
            "SELECT id FROM players_logs WHERE player_id = ? AND type = 'passive'",
            [$actor->getId()]
        ));
    }

    public function testLearningAPassiveWritesAPersonalLine(): void
    {
        $player = $this->createRealPlayer('PjLearner');
        [, $name] = $this->createPassive();

        $player->add_action_passive($name);

        $this->assertTrue($player->have_action_passive($name));
        $this->assertSame(
            ['learn', 'Vous avez appris Talent.'],
            array_values((array) $this->link->fetchAssociative(
                'SELECT type, text FROM players_logs WHERE player_id = ? AND target_id = ?',
                [$player->getId(), $player->getId()]
            ))
        );
    }

    public function testInepuisableIsNotedWhenItTakesAMalusOff(): void
    {
        $id = $this->link->fetchOne("SELECT id FROM action_passives WHERE name = 'inepuisable'");
        if ($id === false) {
            $this->link->insert('action_passives', [
                'name' => 'inepuisable', 'traits' => json_encode(['malus']), 'type' => 'malus',
                'carac' => '', 'value' => 1, 'level' => 1, 'display_name' => 'Inépuisables', 'text' => 'test',
            ]);
            $id = (int) $this->link->lastInsertId();
            $this->passiveIds[] = $id;
        }
        $actor = $this->createRealPlayer('PjHitter');
        $target = $this->createRealPlayer('PjTireless');
        $this->link->insert('players_passives', ['player_id' => $target->getId(), 'passive_id' => (int) $id]);
        $this->holderIds[] = $target->getId();
        EntityManagerFactory::getEntityManager()->clear();

        $instruction = new MalusOutcomeInstruction();
        $instruction->setParameters([]);
        $instruction->execute($actor, $target, new ConditionObject());

        $noted = $target->playerPassiveService->takeTriggered();
        $this->assertSame(['inepuisable'], array_map(
            static fn ($passive): string => $passive->getName(),
            array_values($noted)
        ));
    }
}
