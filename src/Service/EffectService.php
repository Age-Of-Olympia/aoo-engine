<?php

namespace App\Service;

use App\Entity\Effect;
use App\Factory\EntityManagerFactory;
use RuntimeException;

/**
 * Single gateway to effect definitions, now stored in the DB (effects,
 * effect_corruption_materials) instead of the EFFECTS_* / ELE_* /
 * ITEM_CORRUPT* constants of config/constants.php.
 *
 * The catalog is tiny (~60 rows) and consulted on hot paths (caracs
 * loading, every icon render), so it is loaded whole once per request.
 * The player-state side stays in {@see PlayerEffectService}.
 */
class EffectService
{
    /** Icon shown for a name the catalog does not know. */
    public const FALLBACK_ICON = 'ra-fairy-wand';

    /** @var array<string, Effect>|null Per-request catalog, keyed by name. */
    private static ?array $catalog = null;

    /**
     * EM résolu paresseusement : la construction du service doit rester
     * possible sans base (tests unitaires — même contrat de dégradation
     * qu'OptionCatalog), et les instructions d'action l'instancient en
     * plein combat.
     */
    private function entityManager(): \Doctrine\ORM\EntityManager
    {
        return EntityManagerFactory::getEntityManager();
    }

    /** @return array<string, Effect> The whole catalog, keyed by lowercase name. */
    private function catalog(): array
    {
        if (self::$catalog === null) {
            try {
                /* One block, controls included. The static catalog outlives
                 * EntityManager clears (every fixture test starts with one):
                 * a lazy collection initialized AFTER such a clear enters
                 * the identity map MANAGED while its parent stays detached,
                 * and the next flush dies on the "new" entity it finds
                 * through EffectControl#effect. Fetch-joined, parent and
                 * controls detach together. */
                $effects = $this->entityManager()->createQuery(
                    'SELECT e, c FROM ' . Effect::class . ' e LEFT JOIN e.controls c ORDER BY e.id ASC'
                )->getResult();
            } catch (\Throwable) {
                // Base indisponible (bootstrap de tests unitaires) : catalogue
                // vide, NON mis en cache pour ne pas empoisonner une lecture
                // ultérieure avec base.
                return [];
            }

            self::$catalog = [];
            foreach ($effects as $effect) {
                self::$catalog[$effect->getName()] = $effect;
            }
        }

        return self::$catalog;
    }

    public function getEffectByName(string $name): ?Effect
    {
        return $this->catalog()[strtolower($name)] ?? null;
    }

    /**
     * Whether the catalog knows this name — the existence check that
     * gated every effect write when EFFECTS_RA_FONT was the master list.
     */
    public function exists(string $name): bool
    {
        return $this->getEffectByName($name) !== null;
    }

    /**
     * Un élément au sol de ce nom laisse-t-il construire et aménager sa
     * case ? Un nom hors catalogue bloque (prudence : on ne bâtit pas
     * sur l'inconnu).
     */
    public function isBuildableOver(string $name): bool
    {
        return $this->getEffectByName($name)?->isBuildableOver() ?? false;
    }

    public function getIcon(string $name): string
    {
        $effect = $this->getEffectByName($name);

        return $effect ? $effect->getIcon() : self::FALLBACK_ICON;
    }

    public function getLabel(string $name): string
    {
        $effect = $this->getEffectByName($name);

        return $effect ? $effect->getLabel() : ucfirst(strtr($name, '_', ' '));
    }

    public function isHidden(string $name): bool
    {
        $effect = $this->getEffectByName($name);

        return $effect !== null && $effect->isHidden();
    }

    /** @return string[] Names of the ephemeral stances (ex-EFFECTS_HIDDEN). */
    public function getHiddenNames(): array
    {
        return array_keys(array_filter(
            $this->catalog(),
            static fn (Effect $effect): bool => $effect->isHidden()
        ));
    }

    /** @return array<string, string> effect name => carac lowered by 1 (ex-ELE_DEBUFFS). */
    public function getDebuffCaracs(): array
    {
        return $this->caracMap('getDebuffCarac');
    }

    /** @return array<string, string> effect name => carac raised by 1 (ex-ELE_BUFFS). */
    public function getBuffCaracs(): array
    {
        return $this->caracMap('getBuffCarac');
    }

    /** @return array<string, string> */
    private function caracMap(string $getter): array
    {
        $map = [];
        foreach ($this->catalog() as $name => $effect) {
            $carac = $effect->{$getter}();
            if ($carac !== null) {
                $map[$name] = $carac;
            }
        }

        return $map;
    }

    /**
     * Effects this one cancels when applied (ex-ELE_CONTROLS : eau
     * éteint feu… — une liste, certains effets en annulent plusieurs).
     *
     * @return string[]
     */
    public function getControlledEffects(string $name): array
    {
        $effect = $this->getEffectByName($name);

        return $effect ? $effect->getControlNames() : [];
    }

    /**
     * Effects that cancel this one (the inverse the old ELE_IS_CONTROLED
     * constant hand-maintained, now computed).
     *
     * @return string[]
     */
    public function getControllersOf(string $name): array
    {
        $name = strtolower($name);

        $controllers = [];
        foreach ($this->catalog() as $controller => $effect) {
            if (in_array($name, $effect->getControlNames(), true)) {
                $controllers[] = $controller;
            }
        }

        return $controllers;
    }

    /** @return array<string, string[]> corruption name => breakable materials (ex-ITEM_CORRUPTIONS). */
    public function getCorruptionMaterials(): array
    {
        $map = [];
        foreach ($this->catalog() as $name => $effect) {
            if ($effect->getCorruptionBreakChance() !== null) {
                $map[$name] = $effect->getCorruptionMaterialNames();
            }
        }

        return $map;
    }

    /** @return array<string, int> corruption name => extra break chance (ex-ITEM_CORRUPT_BREAKCHANCES). */
    public function getCorruptionBreakChances(): array
    {
        $map = [];
        foreach ($this->catalog() as $name => $effect) {
            if ($effect->getCorruptionBreakChance() !== null) {
                $map[$name] = $effect->getCorruptionBreakChance();
            }
        }

        return $map;
    }

    /**
     * Contributions d'un modificateur de combat sur des effets PORTÉS
     * (players_effects) : chaque effet contribue valeur × modificateur
     * catalogue. Retourne les sommes positive et négative séparées avec
     * les libellés contributeurs — le détail des jets et des dégâts les
     * affiche de part et d'autre du calcul.
     *
     * @param iterable<\App\Entity\PlayerEffect> $carried
     * @param string $getter getRollAttackMod | getRollDefenseMod |
     *                       getDamageDealtMod | getDamageTakenMod |
     *                       getPushAttackMod | getPushDefenseMod
     * @return array{pos: int, neg: int, posLabels: string[], negLabels: string[]}
     */
    public function modifierContributions(iterable $carried, string $getter): array
    {
        $result = ['pos' => 0, 'neg' => 0, 'posLabels' => [], 'negLabels' => []];

        foreach ($carried as $playerEffect) {
            $effect = $this->getEffectByName($playerEffect->getName());
            if ($effect === null) {
                continue;
            }

            $contribution = $effect->{$getter}() * (int) ($playerEffect->getValue() ?? 1);
            if ($contribution > 0) {
                $result['pos'] += $contribution;
                $result['posLabels'][] = $effect->getLabel();
            } elseif ($contribution < 0) {
                $result['neg'] += -$contribution;
                $result['negLabels'][] = $effect->getLabel();
            }
        }

        return $result;
    }

    /**
     * Facteur cumulé sur les dégâts subis (encaisse : ×0.75) — produit
     * des facteurs des effets portés, 1.0 = neutre.
     *
     * @param iterable<\App\Entity\PlayerEffect> $carried
     */
    public function damageTakenFactor(iterable $carried): float
    {
        $factor = 1.0;
        foreach ($carried as $playerEffect) {
            $effect = $this->getEffectByName($playerEffect->getName());
            if ($effect !== null) {
                $factor *= $effect->getDamageTakenFactor();
            }
        }

        return $factor;
    }

    /**
     * Parmi des effets portés, ceux dont le comportement de tour matche —
     * blocage de récupération d'une carac, régénération ou malus de
     * mouvement (le moteur de tour les consomme).
     *
     * @param iterable<\App\Entity\PlayerEffect> $carried
     * @return Effect[]
     */
    public function turnEffects(iterable $carried, string $behavior, string $carac = ''): array
    {
        $matching = [];
        foreach ($carried as $playerEffect) {
            $effect = $this->getEffectByName($playerEffect->getName());
            if ($effect === null) {
                continue;
            }

            $matches = match ($behavior) {
                'block_recovery' => $effect->getBlockRecovery() === $carac,
                'turn_regen' => $effect->isTurnRegen(),
                'turn_mvt_malus' => $effect->isTurnMvtMalus(),
                default => false,
            };
            if ($matches) {
                $matching[] = $effect;
            }
        }

        return $matching;
    }

    /**
     * Postures de défense PORTÉES (dodge_scope non vide), dans l'ordre
     * des effets portés — DodgeCondition les déclenche toutes si leurs
     * conditions matchent, comme les branches historiques.
     *
     * @param iterable<\App\Entity\PlayerEffect> $carried
     * @return Effect[]
     */
    public function carriedStances(iterable $carried): array
    {
        $stances = [];
        foreach ($carried as $playerEffect) {
            $effect = $this->getEffectByName($playerEffect->getName());
            if ($effect !== null && $effect->getDodgeScope() !== '') {
                $stances[] = $effect;
            }
        }

        return $stances;
    }

    /**
     * L'un des effets portés donne-t-il le vol (grants_flight) ?
     *
     * @param iterable<\App\Entity\PlayerEffect> $carried
     */
    public function grantsFlight(iterable $carried): bool
    {
        foreach ($carried as $playerEffect) {
            $effect = $this->getEffectByName($playerEffect->getName());
            if ($effect !== null && $effect->grantsFlight()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Multiplicateur des coûts déclarés « imposture » : 1 + la somme des
     * valeurs portées des effets cost_multiplier (un seul effet porté à
     * la valeur v → ×(v+1), la formule historique).
     *
     * @param iterable<\App\Entity\PlayerEffect> $carried
     */
    public function costMultiplier(iterable $carried): int
    {
        $multiplier = 1;
        foreach ($carried as $playerEffect) {
            $effect = $this->getEffectByName($playerEffect->getName());
            if ($effect !== null && $effect->isCostMultiplier()) {
                $multiplier += (int) ($playerEffect->getValue() ?? 1);
            }
        }

        return $multiplier;
    }

    /**
     * Premier effet porté qui bloque marchand/écoles (blocks_trading),
     * ou null — son libellé sert au message de refus.
     *
     * @param iterable<\App\Entity\PlayerEffect> $carried
     */
    public function tradingBlocker(iterable $carried): ?Effect
    {
        foreach ($carried as $playerEffect) {
            $effect = $this->getEffectByName($playerEffect->getName());
            if ($effect !== null && $effect->blocksTrading()) {
                return $effect;
            }
        }

        return null;
    }

    /** @return Effect[] The whole catalog, map markers included (admin list). */
    public function getAllEffects(): array
    {
        return array_values($this->catalog());
    }

    /**
     * @return string[] Names offered wherever gameplay references an
     *                  effect (workbench dropdowns, races.bleeds…) —
     *                  the catalog minus the map markers.
     */
    public function getGameplayEffectNames(): array
    {
        return array_keys(array_filter(
            $this->catalog(),
            static fn (Effect $effect): bool => !$effect->isMapMarker()
        ));
    }

    /**
     * Persist a (new or edited) effect and drop the request cache.
     */
    public function save(Effect $effect): void
    {
        $this->entityManager()->persist($effect);
        $this->entityManager()->flush();
        self::clearCache();
    }

    /**
     * Replace a corruption's material list (admin edit).
     *
     * @param string[] $materials
     */
    public function replaceCorruptionMaterials(Effect $effect, array $materials): void
    {
        $this->replaceNameList('effect_corruption_materials', $effect, $materials);
    }

    /**
     * Replace the list of effects this one cancels (admin edit).
     *
     * @param string[] $controlledNames
     */
    public function replaceControls(Effect $effect, array $controlledNames): void
    {
        $this->replaceNameList('effect_controls', $effect, $controlledNames);
    }

    /**
     * Plain SQL delete+insert, same rationale as
     * RaceService::replaceNameLists (name strings without FK identity,
     * sidesteps ORM insert-before-delete collisions on the unique key).
     *
     * @param string[] $names
     */
    private function replaceNameList(string $table, Effect $effect, array $names): void
    {
        $connection = $this->entityManager()->getConnection();
        $names = array_values(array_unique(array_filter(array_map('trim', $names))));

        $connection->executeStatement("DELETE FROM {$table} WHERE effect_id = ?", [$effect->getId()]);
        foreach ($names as $position => $name) {
            $connection->executeStatement(
                "INSERT INTO {$table} (effect_id, name, position) VALUES (?, ?, ?)",
                [$effect->getId(), $name, $position]
            );
        }

        $this->entityManager()->refresh($effect);
        self::clearCache();
    }

    /**
     * Porteurs actuels par effet en une requête (la liste admin l'affiche
     * pour tout le catalogue sans une requête par ligne).
     *
     * @return array<string, int> effect name => carriers
     */
    public function countCarriersByEffectName(): array
    {
        $rows = $this->entityManager()->getConnection()->fetchAllAssociative(
            'SELECT name, COUNT(*) AS carriers FROM players_effects GROUP BY name'
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['name']] = (int) $row['carriers'];
        }

        return $counts;
    }

    /**
     * Personnages (joueurs et PNJ) portant actuellement cet effet — le
     * garde-fou de la suppression.
     */
    public function countPlayersUsingEffect(string $name): int
    {
        return (int) $this->entityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM players_effects WHERE name = ?',
            [strtolower($name)]
        );
    }

    /**
     * Supprime un effet qu'aucun personnage ne porte. players_effects.name
     * étant une colonne texte sans contrainte, supprimer un effet encore
     * porté laisserait des lignes fantômes (icône de repli, aucun
     * comportement) — refusé. Les références dans les paramètres
     * d'actions ne sont pas comptées : le workbench affichera la
     * sentinelle « inconnue ».
     *
     * @throws RuntimeException quand des personnages portent encore l'effet
     */
    public function deleteEffect(Effect $effect): void
    {
        $carriers = $this->countPlayersUsingEffect($effect->getName());
        if ($carriers > 0) {
            throw new RuntimeException(sprintf(
                'L\'effet « %s » est encore porté par %d personnage(s) — attendez son expiration ou retirez-le avant de le supprimer.',
                $effect->getName(),
                $carriers
            ));
        }

        $this->entityManager()->remove($effect);
        $this->entityManager()->flush();
        self::clearCache();
    }

    /**
     * Drop the per-request cache (after admin edits or in tests).
     */
    public static function clearCache(): void
    {
        self::$catalog = null;
    }
}
