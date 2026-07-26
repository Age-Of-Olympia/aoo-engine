<?php

namespace Tests\Player;

use App\Service\PlayerEffectService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Les effets se comptent en TOURS : players_effects.endTime porte le
 * nombre de tours restants, perd un point à chaque tour du joueur, et
 * l'effet tombe à zéro.
 *
 * Zéro a donc changé de sens. Il voulait dire « sans fin » du temps où
 * endTime portait un instant ; il veut maintenant dire « terminé ». Il
 * fallait une autre écriture pour l'effet qui ne s'éteint jamais — le
 * vol des oiseaux, un trait de race, un poison qu'il faut soigner : une
 * durée NÉGATIVE, hors d'atteinte de la décrémentation comme de la
 * purge.
 *
 * Ces tests tiennent les deux moitiés de la règle : ce qui doit
 * s'user s'use, ce qui ne doit pas s'user ne s'use pas.
 */
#[Group('entities-golden-master')]
class EffectDurationInTurnsTest extends LegacyPlayerFixtureTestCase
{
    private const EFFECT = 'adrenaline';

    private function remainingTurns(int $playerId): ?int
    {
        $value = $this->link->fetchOne(
            'SELECT endTime FROM players_effects WHERE player_id = ? AND name = ?',
            [$playerId, self::EFFECT]
        );

        return $value === false ? null : (int) $value;
    }

    public function testAnEffectLosesOneTurnPerTurn(): void
    {
        $player = $this->createRealPlayer('GmTurns');
        $player->get_data();
        $player->add_effect(self::EFFECT, 3);

        $this->assertSame(3, $this->remainingTurns((int) $player->id));

        $service = new PlayerEffectService();
        $service->consumeOneTurnByPlayerId((int) $player->id);

        $this->assertSame(2, $this->remainingTurns((int) $player->id), 'un tour consommé, deux restants');
    }

    public function testAnEffectAtZeroIsCountedAsOverAndPurged(): void
    {
        $player = $this->createRealPlayer('GmTurns');
        $player->get_data();
        $player->add_effect(self::EFFECT, 1);

        $service = new PlayerEffectService();
        $service->consumeOneTurnByPlayerId((int) $player->id);

        $this->assertSame(0, $this->remainingTurns((int) $player->id), 'le dernier tour consommé le met à zéro');
        $this->assertSame(1, $service->countExpiredByPlayerId((int) $player->id));

        $player->purge_effects();

        $this->assertNull($this->remainingTurns((int) $player->id), 'un effet terminé est retiré');
    }

    /**
     * L'exigence explicite : garder l'effet sans fin — le vol des
     * oiseaux — alors que zéro a changé de camp.
     */
    public function testAnEndlessEffectIsNeitherConsumedNorPurged(): void
    {
        $player = $this->createRealPlayer('GmTurns');
        $player->get_data();
        $player->add_effect(self::EFFECT, PlayerEffectService::DURATION_INFINITE);

        $service = new PlayerEffectService();

        for ($turn = 0; $turn < 5; $turn++) {
            $service->consumeOneTurnByPlayerId((int) $player->id);
            $player->purge_effects();
        }

        $this->assertSame(
            PlayerEffectService::DURATION_INFINITE,
            $this->remainingTurns((int) $player->id),
            'cinq tours plus tard, l\'effet sans fin est intact'
        );
        $this->assertSame(0, $service->countExpiredByPlayerId((int) $player->id), 'et il n\'est jamais annoncé terminé');
    }

    /**
     * Un effet déjà terminé ne doit pas être poussé dans le négatif par
     * un tour de plus : il deviendrait éternel par accident, ce qui est
     * exactement l'inverse de ce qu'on veut.
     */
    public function testAnExpiredEffectIsNotPushedIntoTheEndlessRange(): void
    {
        $player = $this->createRealPlayer('GmTurns');
        $player->get_data();
        $player->add_effect(self::EFFECT, 0);

        $service = new PlayerEffectService();
        $service->consumeOneTurnByPlayerId((int) $player->id);

        $this->assertSame(0, $this->remainingTurns((int) $player->id), 'zéro reste zéro');
    }

    public function testTheRemainingTimeIsSpelledInTurns(): void
    {
        $this->assertSame('∞', PlayerEffectService::describeRemaining(PlayerEffectService::DURATION_INFINITE));
        $this->assertSame('(reposez-vous)', PlayerEffectService::describeRemaining(0));
        $this->assertSame('1 tour', PlayerEffectService::describeRemaining(1));
        $this->assertSame('3 tours', PlayerEffectService::describeRemaining(3));
    }
}
