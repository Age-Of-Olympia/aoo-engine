<?php

namespace App\Service\WarSchool;

use App\Factory\EntityManagerFactory;
use Classes\Db;
use Classes\Item;
use Classes\Player;
use Doctrine\DBAL\ArrayParameterType;

/**
 * Ce qu'un sort coûte à l'apprentissage : des ressources, pas de l'or.
 *
 * Les quatre familles ne sont inscrites nulle part en base — ni colonne
 * `rarity`, ni catalogue, et `items.race` ne les recoupe pas (une ressource
 * « de race » se paie quelle que soit la race du joueur). Elles vivent donc
 * ici, en un seul endroit, plutôt que d'être redécouvertes par la vue, le
 * validateur d'achat et le wiki chacun de leur côté.
 *
 * Le joueur choisit librement dans la famille demandée : la facture dit
 * « une rare », pas « de l'étain ». Deux fois la même ressource est un choix
 * valide — c'est alors deux exemplaires qu'il faut avoir.
 *
 * Les compétences non-sorts continuent de se payer en or
 * ({@see \App\Service\ActionService::getPrice()}).
 */
final class SpellPriceService
{
    /** @var array<string, list<string>> famille => noms d'objets (items.name) */
    public const TIERS = [
        'commune'   => ['pierre', 'adonis', 'bois'],
        'raciale'   => ['cuivre', 'fer', 'cendre', 'tourbe', 'bois_petrifie'],
        'rare'      => ['etain', 'pierre_mana', 'nickel', 'cuir', 'salpetre'],
        'lointaine' => ['ivoire', 'mycelium', 'perle', 'poussiere_etoile', 'soufre'],
    ];

    /** @var array<string, string> */
    public const TIER_LABELS = [
        'commune'   => 'Ressource commune',
        'raciale'   => 'Ressource de race',
        'rare'      => 'Ressource rare',
        'lointaine' => 'Ressource lointaine',
    ];

    /** @var array<int, array<string, int>> niveau => famille => quantité */
    private const COSTS = [
        1 => ['commune' => 2],
        2 => ['commune' => 1, 'raciale' => 1],
        3 => ['rare' => 1],
        4 => ['rare' => 1, 'raciale' => 2],
        5 => ['rare' => 1, 'lointaine' => 1],
    ];

    /** @var array<string, string>|null libellés du catalogue, lus une fois par requête */
    private static ?array $labels = null;

    /**
     * Le barème du niveau, famille => quantité. Un niveau hors barème est
     * ramené dans ses bornes : mieux vaut facturer le niveau voisin que
     * laisser un sort gratuit sur une donnée aberrante.
     *
     * @return array<string, int>
     */
    public static function costFor(int $level): array
    {
        return self::COSTS[max(1, min(5, $level))];
    }

    /**
     * Une famille par ressource à payer, dans l'ordre des menus déroulants :
     * le niveau 4 rend ['rare', 'raciale', 'raciale'], soit trois choix.
     *
     * @return list<string>
     */
    public static function slots(int $level): array
    {
        $slots = [];
        foreach (self::costFor($level) as $tier => $count) {
            for ($n = 0; $n < $count; $n++) {
                $slots[] = $tier;
            }
        }

        return $slots;
    }

    /**
     * La facture détaillée telle que le joueur l'a remplie : objet => nombre,
     * ou null si les choix ne correspondent pas au barème — mauvais nombre de
     * ressources, ou une ressource prise hors de la famille demandée.
     *
     * Le null est un refus, pas une facture vide : un appelant qui le traite
     * comme « rien à payer » offrirait le sort.
     *
     * @param list<string> $choices un nom d'objet par emplacement, dans l'ordre de slots()
     * @return array<string, int>|null
     */
    public static function tally(int $level, array $choices): ?array
    {
        $slots = self::slots($level);
        if (count($choices) !== count($slots)) {
            return null;
        }

        $tally = [];
        foreach ($slots as $index => $tier) {
            $name = $choices[$index];
            if (!in_array($name, self::TIERS[$tier], true)) {
                return null;
            }
            $tally[$name] = ($tally[$name] ?? 0) + 1;
        }

        return $tally;
    }

    /**
     * Débite la facture, tout ou rien.
     *
     * add_item() refuse déjà de descendre un stock sous zéro ; la transaction
     * est là pour la facture à plusieurs lignes, où la deuxième ressource peut
     * manquer après que la première a été prise.
     *
     * @param array<string, int> $tally objet => nombre
     */
    public static function pay(Player $player, array $tally, ?string &$message = null): bool
    {
        $db = new Db();
        $db->beginTransaction();

        foreach ($tally as $name => $count) {
            $item = Item::get_item_by_name($name);
            if ($item === false || !$item->add_item($player, -$count)) {
                $db->rollback();
                $message = 'Il vous manque des ressources : ' . $count . ' ' . self::labelOf($name) . '.';

                return false;
            }
        }

        $db->commit();

        return true;
    }

    /**
     * Ce que le joueur porte de chacune des ressources du barème, en une
     * requête — dix-huit `Item::get_n()` pour afficher une page de sorts
     * seraient dix-huit allers-retours.
     *
     * Le sac seulement : la banque ne paie pas, puisque add_item() n'y
     * touche pas non plus.
     *
     * @return array<string, int> objet => nombre porté (absent = 0)
     */
    public static function owned(Player $player): array
    {
        $names = array_merge(...array_values(self::TIERS));

        $rows = EntityManagerFactory::getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT i.name AS name, SUM(pi.n) AS n
               FROM players_items pi
               INNER JOIN items i ON i.id = pi.item_id
              WHERE pi.player_id = ? AND pi.slot = '' AND i.name IN (?)
              GROUP BY i.name",
            [(int) $player->id, $names],
            [\Doctrine\DBAL\ParameterType::INTEGER, ArrayParameterType::STRING]
        );

        $owned = [];
        foreach ($rows as $row) {
            $owned[(string) $row['name']] = (int) $row['n'];
        }

        return $owned;
    }

    /**
     * Le libellé affichable de chaque ressource du barème — « Bois » plutôt
     * que « bois », et surtout « Poussière d'étoile » plutôt que
     * « poussiere_etoile ».
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        if (self::$labels !== null) {
            return self::$labels;
        }

        self::$labels = [];
        foreach (array_merge(...array_values(self::TIERS)) as $name) {
            self::$labels[$name] = self::labelOf($name);
        }

        return self::$labels;
    }

    /**
     * Le catalogue porte le libellé (colonnes ou JSON selon l'objet). À
     * défaut on rend le slug lisible : une ressource absente de CET
     * environnement ne doit pas effacer la ligne de coût, juste s'afficher
     * moins joliment.
     */
    private static function labelOf(string $name): string
    {
        $item = Item::get_item_by_name($name);
        $data = $item === false ? null : $item->get_data();
        $label = is_object($data) && isset($data->name) ? $data->name : null;

        return is_string($label) && $label !== '' ? $label : ucfirst(str_replace('_', ' ', $name));
    }
}