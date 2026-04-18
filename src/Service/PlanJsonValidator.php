<?php

namespace App\Service;

/**
 * Validates the JSON structure of a plan file.
 * Each z_level must explicitly declare either:
 *   - "MapUnavailable": true  → pas de carte pour ce niveau (voulu)
 *   - OR the 4 bounds fields: visibleBoundsMinX/MaxX/MinY/MaxY
 *
 * If a DB instance is provided, also checks for z-levels that exist in the
 * coords table but are not declared in the JSON at all.
 */
class PlanJsonValidator
{
    /**
     * @param object      $planData  Raw decoded JSON of the plan
     * @param string      $planId    Plan identifier (used for DB query)
     * @param object|null $db        Optional DB instance (Classes\Db) for cross-checking with coords table
     * @return array{errors: string[], warnings: string[], ok: string[]}
     */
    public static function validate(object $planData, string $planId, ?object $db = null): array
    {
        $errors   = [];
        $warnings = [];
        $ok       = [];

        if (!isset($planData->z_levels) || !is_array($planData->z_levels)) {
            // Plan sans z_levels — structure ancienne, pas de validation ici
            return ['errors' => $errors, 'warnings' => $warnings, 'ok' => $ok];
        }

        // Index des z déclarés dans le JSON
        $declaredZs = [];

        foreach ($planData->z_levels as $zLevel) {
            $z     = $zLevel->z ?? '?';
            $name  = $zLevel->{'z-name'} ?? "Z{$z}";
            $label = "Z{$z} ({$name})";
            $declaredZs[] = $z;

            // Cas 1 : MapUnavailable explicitement à true
            if (isset($zLevel->MapUnavailable) && $zLevel->MapUnavailable === true) {
                $ok[] = "{$label} : pas de carte (MapUnavailable)";
                continue;
            }

            // Cas 2 : MapUnavailable présent mais pas true → probablement une erreur
            if (isset($zLevel->MapUnavailable) && $zLevel->MapUnavailable !== true) {
                $warnings[] = "{$label} : MapUnavailable est présent mais sa valeur n'est pas true (valeur : " . json_encode($zLevel->MapUnavailable) . ")";
            }

            // Cas 3 : vérification des 4 bounds
            $boundsFields  = ['visibleBoundsMinX', 'visibleBoundsMaxX', 'visibleBoundsMinY', 'visibleBoundsMaxY'];
            $missingFields = [];
            foreach ($boundsFields as $field) {
                if (!isset($zLevel->$field)) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $c = '<code style="display:inline;white-space:nowrap">';
                $errors[] = "{$label} : champs manquants : {$c}" . implode("</code>, {$c}", $missingFields) . "</code>. Ajoutez les bounds ou {$c}\"MapUnavailable\": true</code>";
                continue;
            }

            // Cas 4 : bounds toutes à 0 — probablement oublié
            $allZero = $zLevel->visibleBoundsMinX == 0
                    && $zLevel->visibleBoundsMaxX == 0
                    && $zLevel->visibleBoundsMinY == 0
                    && $zLevel->visibleBoundsMaxY == 0;

            if ($allZero) {
                $c = '<code style="display:inline;white-space:nowrap">';
                $warnings[] = "{$label} : toutes les bounds sont à 0. Est-ce intentionnel ? Si ce niveau n'a pas de carte, ajoutez {$c}\"MapUnavailable\": true</code>";
            } else {
                $ok[] = "{$label} : bornes valides ({$zLevel->visibleBoundsMinX}/{$zLevel->visibleBoundsMaxX}, {$zLevel->visibleBoundsMinY}/{$zLevel->visibleBoundsMaxY})";
            }
        }

        // Cas 5 : z-levels présents en base mais absents du JSON
        if ($db !== null) {
            $sql = "SELECT DISTINCT z FROM coords WHERE plan = ? ORDER BY z DESC";
            $rows = $db->exe($sql, [$planId]);

            $dbZs = [];
            if ($rows) {
                while ($row = $rows->fetch_object()) {
                    $dbZs[] = (int)$row->z;
                }
            }

            foreach ($dbZs as $dbZ) {
                if (!in_array($dbZ, $declaredZs, true)) {
                    $c = '<code style="display:inline;white-space:nowrap">';
                    $errors[] = "Z{$dbZ} : existe en base de données mais n'est pas déclaré dans le JSON. Ajoutez-le avec ses bornes ou {$c}\"MapUnavailable\": true</code>";
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'ok' => $ok];
    }
}
