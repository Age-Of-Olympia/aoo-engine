<?php

namespace App\Service;

use Classes\Db;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerEffect;

class PlayerEffectService
{

    /**
     * Durée d'effet sans fin, en tours.
     *
     * Depuis le passage des durées en TOURS, endTime porte le nombre de
     * tours restants : il est décrémenté à chaque tour et l'effet part à
     * zéro. Il fallait donc une valeur pour « ne s'éteint jamais » — le
     * vol des oiseaux, un trait de race, une bénédiction permanente.
     * Une valeur NÉGATIVE ne peut être atteinte par la décrémentation
     * (qui ne vise que les durées >= 1) ni par la purge (qui ne vise que
     * zéro) : l'effet reste en place sans cas particulier dans les
     * requêtes de tour.
     */
    public const DURATION_INFINITE = -1;

    /** Un effet sans fin ne se décrémente pas et ne s'expire pas. */
    public static function isInfinite(int $endTime): bool
    {
        return $endTime < 0;
    }

    /**
     * Effets arrivés à terme, à retirer.
     *
     * Zéro = terminé. Les durées négatives sont sans fin et ne comptent
     * jamais ici — c'est ce qui garde le vol des oiseaux en vol.
     */
    public function countExpiredByPlayerId(int $playerId): int
    {
        return (int) (new Db())->exe(
            'SELECT COUNT(*) AS n FROM players_effects WHERE endTime = 0 AND player_id = ?',
            [$playerId]
        )->fetch_object()->n;
    }

    /**
     * Un tour consommé sur les effets datés du joueur.
     *
     * Ne vise que les durées >= 1 : un effet sans fin (négatif) n'est
     * pas décrémenté, et un effet déjà à zéro n'est pas poussé dans le
     * négatif — ce qui le rendrait éternel par accident.
     */
    public function consumeOneTurnByPlayerId(int $playerId): void
    {
        (new Db())->exe(
            'UPDATE players_effects SET endTime = endTime - 1 WHERE player_id = ? AND endTime >= 1',
            [$playerId]
        );
    }

    /**
     * Temps restant tel qu'on l'annonce au joueur.
     *
     * Quatre écrans le rendaient chacun de leur côté (fiche, barre du
     * HUD, page des PNJ, console d'administration) — quatre copies de la
     * même lecture, qu'il aurait fallu corriger quatre fois au passage
     * des heures aux tours. Une seule ici.
     */
    public static function describeRemaining(?int $endTime): string
    {
        $endTime = (int) $endTime;

        if (self::isInfinite($endTime)) {
            return '∞';
        }

        if ($endTime <= 0) {
            return '(reposez-vous)';
        }

        return $endTime . ' tour' . ($endTime > 1 ? 's' : '');
    }

    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getEffectsByPlayerId(int $playerId): array
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        return $repo->findBy(['player_id' => $playerId]);
    }

    public function getEffectValueByPlayerIdByEffectName(int $playerId, string $name): int
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        $results = $repo->findBy([
            'player_id' => $playerId,
            'name' => $name
        ]);

        if(!empty($results)){
            return $results[0]->getValue();
        }
        return 0;
    }

    public function hasEffectByPlayerIdByEffectName(int $playerId, string $name): bool
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        $results = $repo->findBy([
            'player_id' => $playerId,
            'name' => $name
        ]);

        return !empty($results);
    }

    public function removeAllEffectsForPlayer(int $playerId)
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        $playerEffects = $repo->findBy(['player_id' => $playerId]);

        foreach ($playerEffects as $playerEffect) {

                $this->entityManager->remove($playerEffect);
        }

        $this->entityManager->flush();
    }

    public function addEffectByPlayerId(int $playerId, string $name, int $endTime, int $value, bool $stackable): void
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        // Check si l'effet est déjà présent sur le personnage
        $existingEffect = $repo->findOneBy([
        'player_id' => $playerId,
        'name' => $name,
        ]);

        if ($existingEffect) {
            if ($stackable) {
                $existingEffect->setValue($existingEffect->getValue() + $value);
                // Certains effets empilables rafraîchissent aussi leur durée
                // à la re-pose (catalogue : stack_refresh_duration, ex-imposture).
                if ((new EffectService())->getEffectByName($name)?->isStackRefreshDuration()) {
                    $existingEffect->setEndTime($endTime);
                }
            } 
            else{
                if($existingEffect->getValue() <= $value){
                    $existingEffect->setValue($value);
                    $existingEffect->setEndTime($endTime);
                }
            }

            $this->entityManager->persist($existingEffect);
        } 
        else {
            $newEffect = new PlayerEffect();
            $newEffect->setPlayer_Id($playerId);
            $newEffect->setName($name);
            $newEffect->setEndTime($endTime);
            $newEffect->setValue($value);

            $this->entityManager->persist($newEffect);
        }

        $this->entityManager->flush();
    }

    public function removeEffectByPlayerId(int $playerId, string $name): void
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        // Check si l'effet est déjà présent sur le personnage
        $existingEffect = $repo->findOneBy([
        'player_id' => $playerId,
        'name' => $name,
        ]);

        if ($existingEffect) {
            $this->entityManager->remove($existingEffect);
            $this->entityManager->flush();
        }
    }    

    public function subEffectByPlayerId(int $playerId, string $name, int $value): void
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        // Check si l'effet est déjà présent sur le personnage
        $existingEffect = $repo->findOneBy([
        'player_id' => $playerId,
        'name' => $name,
        ]);

        if ($existingEffect) {
            $val = $existingEffect->getValue($name);
            if($value > $val){
                $this->removeEffectByPlayerId($playerId, $name);
            }
            else{
                $existingEffect->setValue($value - $val);
            }

            $this->entityManager->persist($existingEffect);
        } 
        $this->entityManager->flush();
    }
}