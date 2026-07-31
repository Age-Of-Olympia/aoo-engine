<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un peuple jouable — ce que « race » a toujours voulu dire.
 *
 * C'est la seule famille dont les CARACS, la faction de départ, le plan de
 * naissance et le drapeau « jouable » veulent dire quelque chose ; les listes
 * de noms et les recettes n'appartiennent qu'à elle aussi. Ils vivent encore
 * sur le tronc, et descendront ici quand les champs déménageront.
 */
#[ORM\Entity]
class CharacterRace extends Race
{
    public function familyKey(): string
    {
        return self::FAMILY_CHARACTER;
    }
}
