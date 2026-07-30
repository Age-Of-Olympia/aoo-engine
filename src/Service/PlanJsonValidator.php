<?php

namespace App\Service;

use App\Service\Map\StructureTypeService;

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
     * @param list<string>|null $knownItemNames Optional preloaded list of existing item names.
     *                                       When provided, biome resources are checked against
     *                                       this in-memory set instead of one DB query per biome ;
     *                                       essential when validating every plan at once (overview).
     * @return array{
     *     errors: string[], warnings: string[], ok: string[],
     *     z: array{errors: string[], warnings: string[], ok: string[]},
     *     biome: array{errors: string[], warnings: string[], ok: string[]}
     * } Messages agrégés (errors/warnings/ok) et détaillés par domaine (z / biome)
     */
    public static function validate(object $planData, string $planId, ?object $db = null, ?array $knownItemNames = null): array
    {
        // Accumulateurs séparés par domaine pour distinguer, à l'affichage,
        // les problèmes de niveaux Z de ceux des biomes.
        $bErrors = []; $bWarnings = []; $bOk = [];
        $zErrors = []; $zWarnings = []; $zOk = [];

        // Set O(1) des items connus (si fourni), pour éviter une requête par biome.
        $itemSet = $knownItemNames !== null ? array_flip($knownItemNames) : null;

        // La validation des biomes est indépendante des z_levels : un plan peut
        // déclarer des biomes sans z_levels (et inversement).
        self::validateBiomes($planData, $db, $itemSet, $bErrors, $bWarnings, $bOk);

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
            'biome'    => ['errors' => $bErrors, 'warnings' => $bWarnings, 'ok' => $bOk],
            'errors'   => array_merge($zErrors, $bErrors),
            'warnings' => array_merge($zWarnings, $bWarnings),
            'ok'       => array_merge($zOk, $bOk),
        ];
    }

    /**
     * Valide les biomes déclarés dans le JSON du plan.
     *
     * Pour qu'une fouille fonctionne (voir ResourceService::findResourcesAround),
     * deux conditions doivent être vraies pour chaque biome :
     *   - le nom du wall existe au catalogue resource_types avec la valeur -1 (récoltable) ;
     *   - le nom de la ressource correspond à un item existant en base
     *     (Item::get_item_by_name reçoit ce nom).
     * Toute typo sur l'un ou l'autre provoque une fouille muette, sans erreur.
     *
     * @param array<string,int>|null $itemSet Set O(1) des items connus (name => index) ou null pour requêter la base
     * @param string[] $errors   Modifié par référence
     * @param string[] $warnings Modifié par référence
     * @param string[] $ok       Modifié par référence
     */
    private static function validateBiomes(object $planData, ?object $db, ?array $itemSet, array &$errors, array &$warnings, array &$ok): void
    {
        if (!isset($planData->biomes) || !is_array($planData->biomes)) {
            return;
        }

        $types = StructureTypeService::all();

        // Suivi des walls déjà rencontrés pour signaler les doublons : au runtime,
        // ResourceService indexe les biomes par wall ($biomes[$wall] = $ressource),
        // donc une seconde entrée pour le même wall écrase silencieusement la première.
        $seenWalls = [];

        foreach ($planData->biomes as $i => $biome) {
            // Valeurs brutes pour la logique (lookups resource_types / requête items),
            // valeurs échappées pour l'affichage (les messages sont rendus en HTML).
            $wallName      = $biome->wall      ?? null;
            $ressourceName = $biome->ressource ?? null;
            $wallEsc       = self::esc($wallName);
            $ressEsc       = self::esc($ressourceName);
            $label = "Biome #" . ($i + 1) . ($wallName ? " (wall: {$wallEsc})" : '');

            // Biome entièrement vide (placeholder {"wall":"","ressource":""}) :
            // inoffensif mais sans effet : un seul warning plutôt que deux erreurs
            // trompeuses de "clé manquante" (les clés existent mais sont vides).
            // Une valeur uniquement composée d'espaces compte comme vide.
            if (trim((string) $wallName) === '' && trim((string) $ressourceName) === '') {
                $warnings[] = "{$label} : biome vide (placeholder), sans effet, à supprimer";
                continue;
            }

            // Wall dupliqué : le runtime ne garde que la dernière ressource associée.
            if ($wallName && isset($seenWalls[$wallName])) {
                $warnings[] = "{$label} : wall '{$wallEsc}' déjà déclaré au biome #" . ($seenWalls[$wallName] + 1) . " → seule la dernière ressource est prise en compte à la récolte";
            } elseif ($wallName) {
                $seenWalls[$wallName] = $i;
            }

            // Vérifier le wall au catalogue des types (doit exister ET être de nature « ressource »)
            if (!$wallName) {
                $errors[] = "{$label} : clé 'wall' manquante";
            } elseif (!array_key_exists($wallName, $types)) {
                $errors[] = "{$label} : wall '{$wallEsc}' inconnu du catalogue des types, probablement une typo";
            } elseif ($types[$wallName]['nature'] !== StructureTypeService::NATURE_RESOURCE) {
                $warnings[] = "{$label} : wall '{$wallEsc}' est de nature « " . self::esc($types[$wallName]['nature'])
                    . " » au catalogue → non récoltable en l'état";
            } else {
                $ok[] = "{$label} : wall valide (nature « ressource » au catalogue)";
            }

            // Vérifier la ressource : elle doit correspondre à un item existant en base.
            // Set préchargé prioritaire (validation en masse) ; sinon requête unitaire.
            if (!$ressourceName) {
                $errors[] = "{$label} : clé 'ressource' manquante";
            } else {
                $exists = null;
                if ($itemSet !== null) {
                    $exists = isset($itemSet[$ressourceName]);
                } elseif ($db !== null) {
                    $rows   = $db->exe("SELECT COUNT(*) AS nb FROM items WHERE name = ?", [$ressourceName]);
                    $exists = $rows ? ((int) $rows->fetch_object()->nb) > 0 : false;
                }
                if ($exists === false) {
                    $errors[] = "{$label} : ressource '{$ressEsc}' introuvable en base (aucun item de ce nom), nom d'item correct ?";
                } elseif ($exists === true) {
                    $ok[] = "{$label} : ressource '{$ressEsc}' valide (item trouvé en base)";
                }
            }

            // exhaust / regrow : absents → comportement de récolte non défini
            if (!isset($biome->exhaust)) {
                $warnings[] = "{$label} : clé 'exhaust' manquante (taux d'épuisement non défini)";
            }
            if (!isset($biome->regrow)) {
                $warnings[] = "{$label} : clé 'regrow' manquante (taux de repousse non défini)";
            }
        }
    }

    /**
     * Échappe une valeur dynamique (nom de z, wall, ressource issus du JSON)
     * pour une insertion sûre dans un message rendu en HTML. Le balisage
     * littéral des messages (ex. <code>) reste volontairement intact.
     */
    private static function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
