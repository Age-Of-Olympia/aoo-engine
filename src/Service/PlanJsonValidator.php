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
     * @param object        $planData        Raw decoded JSON of the plan
     * @param string        $planId          Plan identifier (used for DB query)
     * @param object|null   $db              Optional DB instance (Classes\Db) for cross-checking with coords table
     * @return array{
     *     errors: string[], warnings: string[], ok: string[],
     *     z: array{errors: string[], warnings: string[], ok: string[]}
     * } Messages agrégés (errors/warnings/ok) et détaillés par domaine (z)
     */
    public static function validate(object $planData, string $planId, ?object $db = null): array
    {
        $zErrors = []; $zWarnings = []; $zOk = [];

        if (isset($planData->z_levels) && is_array($planData->z_levels)) {
            // Index des z déclarés dans le JSON
            $declaredZs = [];

            foreach ($planData->z_levels as $zLevel) {
                $z     = $zLevel->z ?? '?';
                $name  = $zLevel->{'z-name'} ?? "Z{$z}";
                $label = "Z" . self::esc($z) . " (" . self::esc($name) . ")";
                // Normaliser en int pour comparer avec les z issus de la base
                // (SELECT ... (int)$row->z) : sinon "0" (string JSON) ≠ 0 (int DB)
                // en comparaison stricte → faux positif « Z absent du JSON ».
                $declaredZs[] = is_numeric($z) ? (int) $z : $z;

                // Cas 1 : MapUnavailable explicitement à true
                if (isset($zLevel->MapUnavailable) && $zLevel->MapUnavailable === true) {
                    $zOk[] = "{$label} : pas de carte (MapUnavailable)";
                    continue;
                }

                // Cas 2 : MapUnavailable présent mais pas true → probablement une erreur
                if (isset($zLevel->MapUnavailable) && $zLevel->MapUnavailable !== true) {
                    $zWarnings[] = "{$label} : MapUnavailable est présent mais sa valeur n'est pas true (valeur : " . json_encode($zLevel->MapUnavailable) . ")";
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
                    $zErrors[] = "{$label} : champs manquants : {$c}" . implode("</code>, {$c}", $missingFields) . "</code>. Ajoutez les bounds ou {$c}\"MapUnavailable\": true</code>";
                    continue;
                }

                // Cas 4 : bounds toutes à 0, probablement oublié
                $allZero = $zLevel->visibleBoundsMinX == 0
                        && $zLevel->visibleBoundsMaxX == 0
                        && $zLevel->visibleBoundsMinY == 0
                        && $zLevel->visibleBoundsMaxY == 0;

                if ($allZero) {
                    $c = '<code style="display:inline;white-space:nowrap">';
                    $zWarnings[] = "{$label} : toutes les bounds sont à 0. Est-ce intentionnel ? Si ce niveau n'a pas de carte, ajoutez {$c}\"MapUnavailable\": true</code>";
                } else {
                    $zOk[] = "{$label} : bornes valides ({$zLevel->visibleBoundsMinX}/{$zLevel->visibleBoundsMaxX}, {$zLevel->visibleBoundsMinY}/{$zLevel->visibleBoundsMaxY})";
                }
            }

            // Cas 5 : z-levels présents en base mais absents du JSON
            if ($db !== null) {
                $sql = "SELECT z, COUNT(*) AS nb FROM coords WHERE plan = ? GROUP BY z ORDER BY z DESC";
                $rows = $db->exe($sql, [$planId]);

                $dbZs = [];
                if ($rows) {
                    while ($row = $rows->fetch_object()) {
                        $dbZs[(int)$row->z] = (int)$row->nb;
                    }
                }

                foreach ($dbZs as $dbZ => $nbCoords) {
                    if (!in_array($dbZ, $declaredZs, true)) {
                        $c = '<code style="display:inline;white-space:nowrap">';
                        $zErrors[] = "Z{$dbZ} : existe en base de données ({$nbCoords} coord" . ($nbCoords > 1 ? 's' : '') . ") mais n'est pas déclaré dans le JSON. Ajoutez-le avec ses bornes ou {$c}\"MapUnavailable\": true</code>";
                    }
                }
            }
        }

        return [
            'z'        => ['errors' => $zErrors, 'warnings' => $zWarnings, 'ok' => $zOk],
            'errors'   => $zErrors,
            'warnings' => $zWarnings,
            'ok'       => $zOk,
        ];
    }

    /**
     * Échappe une valeur dynamique (nom de z issu du JSON)
     * pour une insertion sûre dans un message rendu en HTML. Le balisage
     * littéral des messages (ex. <code>) reste volontairement intact.
     */
    private static function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
