<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * players_effects.endTime cesse d'être un INSTANT pour devenir un
 * NOMBRE DE TOURS restants (décision 2026-07-26, reprise de la MR !645).
 *
 * Le décompte est désormais fait par TurnProcessingService à chaque
 * tour du joueur, ce qui rend la durée d'un effet lisible dans l'unité
 * où le jeu se joue : « trois tours », et non « soixante-douze heures »
 * pour un joueur dont le tour dure un jour et douze heures pour un
 * autre.
 *
 * Deux conversions, et la seconde est la délicate :
 *
 * 1. Les échéances restantes deviennent des tours. On divise par la
 *    durée d'un tour de référence (TurnScheduleService::BASE_TURN_SECONDS,
 *    la vitesse de base) : la vraie durée dépend de la vitesse de chaque
 *    joueur, mais un effet posé pour « environ deux jours » doit surtout
 *    rester d'un ordre de grandeur juste. Arrondi au tour supérieur —
 *    perdre un effet plus tôt qu'annoncé serait plus grave que le garder
 *    un tour de trop.
 *
 * 2. **Zéro changeait de sens.** Il voulait dire « sans fin » ; il veut
 *    maintenant dire « terminé, à retirer au prochain tour ». Sans cette
 *    migration, TOUS les effets permanents en cours — le vol des
 *    oiseaux, les poisons qu'il faut soigner, les traits posés à la main
 *    — seraient supprimés au premier tour joué après le déploiement. Ils
 *    passent donc à -1, la valeur « sans fin » du nouveau modèle
 *    (PlayerEffectService::DURATION_INFINITE), hors d'atteinte de la
 *    décrémentation comme de la purge.
 *
 * Les durées portées par les actions (paramètres JSON des instructions)
 * subissent la même conversion, pour la même raison : réglées en
 * secondes, elles poseraient des effets de plusieurs dizaines de
 * milliers de tours.
 *
 * Ces conversions changent le SENS des valeurs et ne sont donc pas
 * rejouables : les rejouer sur des données déjà converties les
 * abîmerait. C'est le registre de migrations qui garantit l'exécution
 * unique — d'où l'absence de garde supplémentaire ici, contrairement
 * aux DDL du projet.
 */
final class Version20260726120000_EffectDurationsInTurns extends AbstractMigration
{
    /** Durée d'un tour à vitesse de base, cf. TurnScheduleService. */
    private const BASE_TURN_SECONDS = 86400;

    /** Sans fin, cf. PlayerEffectService::DURATION_INFINITE. */
    private const INFINITE = -1;

    /**
     * Au-delà, la valeur ne peut être qu'un horodatage : un compteur de
     * tours n'atteint jamais cet ordre de grandeur. Ce seuil rend la
     * conversion rejouable sans abîmer des lignes déjà converties.
     */
    private const LOOKS_LIKE_A_TIMESTAMP = 1000000;

    public function getDescription(): string
    {
        return 'players_effects.endTime : des instants aux tours restants (0 = expiré, -1 = sans fin)';
    }

    public function up(Schema $schema): void
    {
        // 1. « Sans fin » AVANT tout : zéro va changer de sens.
        $this->addSql('UPDATE players_effects SET endTime = -1 WHERE endTime = 0');

        // 2. Échéances encore à venir → nombre de tours restants (au moins un).
        $this->addSql(
            'UPDATE players_effects
             SET endTime = GREATEST(1, CEIL((endTime - UNIX_TIMESTAMP()) / ' . self::BASE_TURN_SECONDS . '))
             WHERE endTime > ' . self::LOOKS_LIKE_A_TIMESTAMP . '
               AND endTime > UNIX_TIMESTAMP()'
        );

        // 3. Échéances déjà passées → terminé : retirées au prochain tour.
        $this->addSql(
            'UPDATE players_effects
             SET endTime = 0
             WHERE endTime > ' . self::LOOKS_LIKE_A_TIMESTAMP . '
               AND endTime <= UNIX_TIMESTAMP()'
        );

        /* 4. Les actions elles-mêmes portent la durée qu'elles posent,
         * en secondes, dans leurs paramètres JSON. Sans conversion, une
         * action réglée sur « deux jours » (172800) poserait un effet de
         * 172800 TOURS — éternel de fait. Et zéro y voulait dire
         * « illimité », un sens qu'il perd ici aussi. */
        foreach (['action_type_instructions', 'outcome_instructions'] as $table) {
            $this->addSql(
                'UPDATE ' . $table . "
                 SET parameters = JSON_REPLACE(parameters, '$.duration',
                     CASE
                         /* illimité, ancienne écriture */
                         WHEN JSON_VALUE(parameters, '$.duration') = 0 THEN " . self::INFINITE . "
                         /* la seconde unique servait de « jusqu'au prochain tour » */
                         WHEN JSON_VALUE(parameters, '$.duration') = 1 THEN 0
                         ELSE GREATEST(1, CEIL(JSON_VALUE(parameters, '$.duration') / " . self::BASE_TURN_SECONDS . "))
                     END)
                 WHERE JSON_VALUE(parameters, '$.duration') IS NOT NULL"
            );
        }
    }

    public function down(Schema $schema): void
    {
        /* Retour aux instants. Les tours restants redeviennent une
         * échéance à la vitesse de base, et « sans fin » retrouve son
         * ancienne écriture (zéro). Les effets déjà terminés (zéro dans
         * le nouveau modèle) deviendraient « sans fin » à l'envers : on
         * les supprime plutôt, ce que le tour suivant aurait fait. */
        $this->addSql('DELETE FROM players_effects WHERE endTime = 0');
        $this->addSql(
            'UPDATE players_effects
             SET endTime = UNIX_TIMESTAMP() + (endTime * ' . self::BASE_TURN_SECONDS . ')
             WHERE endTime > 0'
        );
        $this->addSql('UPDATE players_effects SET endTime = 0 WHERE endTime = -1');
    }
}
