<?php

namespace App\Trait;

use App\Interface\TakesTurnsInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le tour, tel que le portent ceux qui en prennent un.
 *
 * L'implémentation partagée derrière {@see TakesTurnsInterface}, sur le même
 * modèle que {@see HarvestableFieldsTrait} : les colonnes sont MAPPÉES ici et
 * atterrissent dans l'unique table `players` pour les deux familles qui
 * utilisent ce trait — `Character` et `Building`, qui ne forment pas un
 * sous-arbre. C'est ce qui permet à une capacité de traverser des familles sans
 * remonter sur le tronc : un décor et une épée à terre n'ont pas d'horaire de
 * tour, et n'en portent donc pas la colonne.
 *
 * **Lecture seule.** La valeur vit dans le satellite `turns` ;
 * {@see \App\Service\TurnService} est son seul écrivain et tient la colonne
 * miroir avec elle. Un setter ici atteindrait le miroir seul.
 */
trait TakesTurnsFieldsTrait
{
    #[ORM\Column(type: "integer")]
    protected int $nextTurnTime = 0;

    #[ORM\Column(type: "boolean")]
    protected bool $nextTurnRescheduled = false;

    #[ORM\Column(type: "integer")]
    protected int $lastActionTime = 0;

    /**
     * Le délai anti-berserk. Hors contrat — c'est une règle de combat, pas la
     * question « quand cette chose peut-elle agir » — mais c'est une colonne du
     * tour, donc elle vit ici et dans `turns`.
     */
    #[ORM\Column(type: "integer")]
    protected int $antiBerserkTime = 0;

    public function getNextTurnTime(): int
    {
        return $this->nextTurnTime;
    }

    public function isNextTurnRescheduled(): bool
    {
        return $this->nextTurnRescheduled;
    }

    public function getLastActionTime(): int
    {
        return $this->lastActionTime;
    }

    public function getAntiBerserkTime(): int
    {
        return $this->antiBerserkTime;
    }
}
