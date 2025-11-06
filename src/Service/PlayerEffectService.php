<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerEffect;

class PlayerEffectService
{
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
            } 
            else{
                $existingEffect->setValue($value);
                $existingEffect->setEndTime($endTime);
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