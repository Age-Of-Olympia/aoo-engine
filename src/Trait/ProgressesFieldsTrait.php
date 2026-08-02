<?php

namespace App\Trait;

use App\Interface\ProgressesInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * La progression, telle que la portent ceux qui progressent.
 *
 * L'implémentation partagée derrière {@see ProgressesInterface}, sur le même
 * modèle que {@see HarvestableFieldsTrait} : les colonnes sont MAPPÉES ici et
 * atterrissent dans l'unique table `players` pour les deux familles qui
 * utilisent ce trait — `Character` et `Building`, qui ne forment pas un
 * sous-arbre. Une ressource ou une plante n'a pas de niveau, et n'en porte donc
 * pas la colonne.
 *
 * **Lecture seule.** La valeur vit dans le satellite `progression` ;
 * {@see \App\Service\ProgressionService} est son seul écrivain et tient les
 * colonnes miroirs avec elle. Un setter ici atteindrait les miroirs seuls.
 */
trait ProgressesFieldsTrait
{
    #[ORM\Column(type: "integer")]
    protected int $xp = 0;

    #[ORM\Column(type: "integer")]
    protected int $rank = 1;

    #[ORM\Column(type: "integer", name: "bonus_points")]
    protected int $bonusPoints = 0;

    #[ORM\Column(type: "integer")]
    protected int $pi = 0;

    public function getXp(): int
    {
        return $this->xp;
    }

    /** The level, as the game names it. */
    public function getRank(): int
    {
        return $this->rank;
    }

    public function getBonusPoints(): int
    {
        return $this->bonusPoints;
    }

    public function getPi(): int
    {
        return $this->pi;
    }
}
