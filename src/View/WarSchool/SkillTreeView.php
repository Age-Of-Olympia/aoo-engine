<?php

namespace App\View\WarSchool;

use App\Entity\Action;
use App\Entity\ActionPassive;
use App\Service\ActionPassiveService;
use App\Service\ActionService;
use App\Service\RaceService;
use App\Service\WarSchool\SkillPrerequisiteService;
use App\Trait\EscapesHtmlTrait;
use App\View\Action\ActionCostView;
use Classes\Player;
use Classes\Str;

/**
 * A war-school tab as a tree: one column per level, the actives and (except
 * for spells) the passives of the category as cards, each card coloured by
 * its state — learned, open, locked. The column head carries the gate that
 * opens it (skills owned at the level below, or free spell slots), so the
 * player reads the rule where it applies. The tabs differ by data only
 * ({@see SkillTab}); the spell tab differs by its cap rule.
 */
final class SkillTreeView
{
    use EscapesHtmlTrait;

    private const WIKI_LINE = 'Les différents Effets sont décrits sur la <a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets" target="_blank" class="ws-wiki">page correspondante</a> du Wiki';
    private const OFFENSIVE_LINE = 'Les compétences <strong class="ws-off">offensives</strong> sont en rouge et font des dégâts basés sur la <strong>F</strong> et réduits par la <strong>E</strong>';
    private const CURSE_LINE = 'Les compétences <strong class="ws-curse">déstabilisantes</strong> sont en violet et ne font pas de dégâts';
    private const PERSONAL_LINE = 'Les compétences <strong class="ws-buff">personnelles</strong> sont en bleu et appliquent un bonus personnel';

    /** GET key => category, as scripts/warschool/body.php receives the tab. */
    public const GET_KEYS = ['melee' => 'melee', 'distance' => 'distance', 'magic' => 'magic', 'spells' => 'spell', 'stealth' => 'stealth', 'survival' => 'survival'];

    /** @var array<string, SkillTab>|null */
    private static ?array $tabs = null;

    /** @var array<string, string>|null name => display name, actions and passives */
    private ?array $displayNames = null;

    public function __construct(
        private readonly ActionService $actions = new ActionService(),
        private readonly ActionPassiveService $passives = new ActionPassiveService(),
    ) {
    }

    /** @return array<string, SkillTab> category => tab */
    public static function tabs(): array
    {
        return self::$tabs ??= [
            'melee' => new SkillTab('melee', 'Compétences de Mêlée', 'de mêlée', [
                'Les compétences de Mêlée touchent avec la <strong>CC</strong> et s\'esquivent avec la <strong>CC</strong> ou l\'<strong>Agi</strong> (la meilleure des deux)',
                self::OFFENSIVE_LINE,
                self::CURSE_LINE,
                self::PERSONAL_LINE,
            ]),
            'distance' => new SkillTab('distance', 'Compétences à Distance', 'à distance', [
                'Les compétences à Distance touchent avec la <strong>CT</strong> et s\'esquivent avec la <strong>CC</strong> et l\'<strong>Agi</strong> (75% de la meilleure, 25% de l\'autre)',
                'Les compétences à Distance subissent les malus de Tir',
                self::OFFENSIVE_LINE,
                self::CURSE_LINE,
                self::PERSONAL_LINE,
            ]),
            'magic' => new SkillTab('magic', 'Compétences de Magie', 'de magie', []),
            'stealth' => new SkillTab('stealth', 'Compétences de Furtivité', 'de furtivité', [
                'Les compétences de Furtivité prennent en compte le fait de l\'utiliser ou de s\'en prémunir',
                'Certaines compétences ont leurs coûts basés sur l\'<strong class="ws-warn">Imposture</strong><i class="ra ra-player-teleport ws-warn"></i>.',
                'L\'<strong class="ws-warn">Imposture</strong> représente la difficulté d\'un personnage à rester furtif dans le temps.',
                'Plus elle sera haute, plus les coûts des compétences furtives seront chères.',
                'L\'Effet <strong class="ws-stealth">Furtivité</strong><i class="ra ra-player ws-stealth"></i> affecte également le personnage le temps de la compétence de Furtivité.',
                'Les compétences <strong class="ws-off">offensives</strong> sont en rouge et les stats de dégâts ou de touche dépendent du style de combat associé',
                self::PERSONAL_LINE,
            ]),
            'survival' => new SkillTab('survival', 'Compétences de Survie', 'de survie', [self::PERSONAL_LINE]),
            'spell' => new SkillTab('spell', 'Sorts', '', [
                'Les sorts touchent avec la <strong>FM</strong> et s\'esquivent avec la <strong>FM</strong>',
                'Les sorts <strong class="ws-off">offensives</strong> sont en rouge et font des dégâts basés sur la <strong>Pui</strong> et réduits par la <strong>Rés</strong>',
                'Les <strong class="ws-curse">malédictions</strong> sont en violet et ne font pas de dégâts directs',
                'Les sorts de <strong class="ws-support">soutien</strong> sont en vert et appliquent un bonus à une cible alliée',
                'Les sorts <strong class="ws-buff">personnels</strong> sont en bleu et appliquent un bonus personnel',
            ], spells: true),
        ];
    }

    /**
     * The tabs' styles, inline: the HUD panel receives the body by AJAX,
     * without the Ui wrapper that loads the sheets. Scoped to .ws-content.
     */
    public static function styles(): string
    {
        return '<style>'
            . '.ws-content h1{font-size:1.6em}'
            . '.ws-content h2{font-family:sans-serif;font-size:1.1em;font-weight:bold}'
            . '.ws-content h3{font-family:sans-serif;font-size:1.05em;font-weight:normal}'
            . '.ws-content .ws-info{font-family:sans-serif;font-size:1.05em;text-align:center;margin:6px 0}'
            . '.ws-content .ws-legend{cursor:pointer;margin-bottom:20px;background:rgba(0,0,0,.05);padding:10px;border-radius:5px}'
            . '.ws-content .ws-legend summary{display:flex;align-items:center;justify-content:center;font-weight:bold;margin:15px 0;outline:none}'
            . '.ws-content .ws-legend summary h3{margin:0;display:inline;font-size:1.17em}'
            . '.ws-content .ws-legend h3{margin:5px 0}'
            . '.ws-content .ws-wiki{text-decoration:underline;color:#2980b9}'
            . '.ws-content .ws-off{color:#c0392b}.ws-content .ws-curse{color:#8e44ad}.ws-content .ws-buff{color:#2980b9}.ws-content .ws-support{color:#27ae60}'
            . '.ws-content .ws-warn{color:red}.ws-content .ws-stealth{color:blue}.ws-content .ws-full{color:red}'
            /* the tree: one column per level, scrolling sideways when narrow */
            . '.ws-content .ws-tree{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(200px,1fr);gap:10px;overflow-x:auto;padding:4px;font-family:sans-serif;text-align:left}'
            . '.ws-content .ws-level{display:flex;flex-direction:column;gap:8px}'
            . '.ws-content .ws-level-head{display:flex;justify-content:space-between;align-items:baseline;gap:6px;padding:6px 8px;border-radius:5px;background:rgba(0,0,0,.08)}'
            . '.ws-content .ws-level-head.ws-locked{color:#888}'
            . '.ws-content .ws-level-head.ws-open{border-left:4px solid #27ae60}'
            . '.ws-content .ws-gate{font-size:.85em}'
            . '.ws-content .ws-card{display:flex;gap:8px;padding:6px;border-radius:5px;border:2px solid rgba(0,0,0,.15);background:rgba(255,255,255,.35)}'
            . '.ws-content .ws-card img{width:48px;height:48px;flex:none;border-radius:4px}'
            . '.ws-content .ws-card-body{display:flex;flex-direction:column;gap:3px;min-width:0}'
            . '.ws-content .ws-card.ws-owned{border-color:#27ae60;background:rgba(39,174,96,.12)}'
            . '.ws-content .ws-card.ws-open{border-color:#2980b9;border-style:dashed}'
            . '.ws-content .ws-card.ws-locked{opacity:.55}'
            . '.ws-content .ws-state{font-size:.8em;text-transform:uppercase;letter-spacing:.05em;padding:1px 6px;border-radius:9px;color:#fff;background:#888}'
            . '.ws-content .ws-owned .ws-state{background:#27ae60}'
            . '.ws-content .ws-open .ws-state{background:#2980b9}'
            . '.ws-content .ws-meta{display:flex;flex-wrap:wrap;gap:4px 10px;font-size:.85em}'
            . '.ws-content .ws-kind{text-transform:uppercase;letter-spacing:.05em;font-size:.8em;color:#666}'
            . '.ws-content .ws-effect{font-size:.9em}'
            . '.ws-content .ws-needs{font-size:.85em}'
            . '.ws-content .ws-ok{color:#27ae60}.ws-content .ws-ko{color:#c0392b}'
            . '.ws-content .ws-card .buy-skill-btn,.ws-content .ws-card button{margin-top:4px;align-self:flex-start}'
            . '.ws-content .ws-overview summary h3{font-weight:bold}'
            . '</style>';
    }

    public function render(Player $player, string $category): void
    {
        $tab = self::tabs()[$category];
        $prereqs = SkillPrerequisiteService::forPlayer($player->getId());
        $gold = $player->get_gold();

        SkillPurchaseHandler::handlePost($player, $prereqs);

        $html = $this->header($tab, $prereqs, $gold)
            . $this->legend($tab)
            . $this->tree($tab, $player, $prereqs, $gold);

        echo Str::minify($html);
        echo '<script src="js/warschool.js?v=20260714"></script>';
    }

    /** Every tree, read-only: the player's standing without a school around. */
    public function overview(Player $player): string
    {
        $prereqs = SkillPrerequisiteService::forPlayer($player->getId());

        $html = '';
        foreach (self::tabs() as $tab) {
            $html .= '<details class="ws-legend ws-overview"><summary><h3>' . $this->esc($tab->title) . '</h3></summary>'
                . $this->tree($tab, $player, $prereqs, null)
                . '</details>';
        }

        return $html;
    }

    private function header(SkillTab $tab, SkillPrerequisiteService $prereqs, int $gold): string
    {
        if ($tab->spells) {
            $info = 'Un sort occupe un emplacement de son niveau ; les passifs d\'emplacement en ouvrent';
        } else {
            $required = SkillPrerequisiteService::requiredPerLevel($tab->category);
            $info = 'Compétences apprises : ' . $prereqs->capCount() . '/' . NUMBER_MAX_COMP . ' (sorts + passifs cumulés)'
                . '&nbsp;&middot;&nbsp;Un niveau s\'ouvre avec ' . $required . ' compétence' . ($required > 1 ? 's' : '') . ' ' . $tab->of . ' apprise' . ($required > 1 ? 's' : '') . ' à chaque niveau inférieur';
        }

        return '<h1>' . $this->esc($tab->title) . '</h1>'
            . '<p class="ws-info">Vous avez ' . $gold . ' Po&nbsp;&middot;&nbsp;' . $info . '</p>';
    }

    private function legend(SkillTab $tab): string
    {
        $lines = '';
        foreach ([...$tab->legend, self::WIKI_LINE] as $line) {
            $lines .= '<h3>' . $line . '</h3>';
        }

        return '<details class="ws-legend">'
            . '<summary><h3>Plus d\'informations sur les ' . ($tab->spells ? 'Sorts' : 'Compétences') . '</h3></summary>'
            . $lines
            . '</details>';
    }

    /**
     * The grid: one column per level, cards inside. $gold null renders
     * without buy buttons (the overview).
     */
    private function tree(SkillTab $tab, Player $player, SkillPrerequisiteService $prereqs, ?int $gold): string
    {
        /** @var array<int, list<Action|ActionPassive>> level => skills */
        $byLevel = [];
        foreach ($this->actions->getActionsByCategory($tab->category) as $action) {
            $byLevel[$action->getLevel()][] = $action;
        }
        if (!$tab->spells) {
            foreach ($this->passives->getActionPassivesByCategory($tab->category) as $passive) {
                $byLevel[$passive->getLevel()][] = $passive;
            }
        }

        if ($byLevel === []) {
            return '<div class="section"><p>' . $this->esc($tab->spells ? 'Aucun sort disponible.' : 'Aucune compétence ' . $tab->of . ' disponible.') . '</p></div>';
        }
        ksort($byLevel);

        // need/forbidden lists may point at another tree: resolve over the whole catalogue
        $displayNames = $this->displayNames ??= $this->actions->getAllNames() + $this->passives->getAllNames();

        $costView = new ActionCostView($this->actions);
        $columns = '';
        foreach ($byLevel as $level => $skills) {
            $cards = '';
            foreach ($skills as $skill) {
                $cards .= $this->card($skill, $tab, $player, $prereqs, $gold, $costView, $displayNames);
            }
            $columns .= '<div class="ws-level">' . $this->levelHead($tab, $level, $prereqs) . $cards . '</div>';
        }

        return '<div class="section ws-tree">' . $columns . '</div>';
    }

    /** "Niveau N" plus the gate that opens it, in the player's numbers. */
    private function levelHead(SkillTab $tab, int $level, SkillPrerequisiteService $prereqs): string
    {
        $open = $prereqs->isLevelOpen($tab->category, $level);

        if ($tab->spells) {
            $gate = 'Emplacements : ' . $prereqs->spellCountAt($level) . '/' . $prereqs->spellSlotsAt($level);
        } elseif ($level === 1) {
            $gate = 'Toujours ouvert';
        } else {
            $required = SkillPrerequisiteService::requiredPerLevel($tab->category);
            $gate = 'Niveau ' . ($level - 1) . ' : ' . min($prereqs->treeCountAt($tab->category, $level - 1), $required) . '/' . $required . ' apprise' . ($required > 1 ? 's' : '');
        }

        return '<div class="ws-level-head' . ($open ? ' ws-open' : ' ws-locked') . '">'
            . '<strong>Niveau ' . $level . '</strong>'
            . '<span class="ws-gate">' . $this->esc($gate) . '</span>'
            . '</div>';
    }

    /** @param array<string, string> $displayNames */
    private function card(
        Action|ActionPassive $skill,
        SkillTab $tab,
        Player $player,
        SkillPrerequisiteService $prereqs,
        ?int $gold,
        ActionCostView $costView,
        array $displayNames
    ): string {
        $name = $skill->getName();
        $isPassive = $skill instanceof ActionPassive;
        $owned = $prereqs->owns($name);
        $learnable = $this->learnable($skill, $player);
        $usable = $isPassive ? $prereqs->isPassiveUsable($skill) : $prereqs->isUsable($skill);
        [$state, $stateLabel] = $owned
            ? ['ws-owned', 'Apprise']
            : (($learnable && $usable) ? ['ws-open', 'Disponible'] : ['ws-locked', 'Verrouillée']);

        $race = $skill->getRace();
        $image = file_exists('img/spells/' . $name . '.jpeg') ? $name : 'todo';

        $meta = '<span class="ws-kind">' . ($tab->spells ? 'Sort' : ($isPassive ? 'Passif' : 'Actif')) . '</span>'
            . '<span style="color: ' . RaceService::getRaceColor($race) . ';">' . (empty($race) ? 'Commun' : $this->esc(ucfirst($race))) . '</span>'
            . ($isPassive ? '' : '<span>' . $costView->forAction($skill) . '</span>');

        $button = '';
        if ($gold !== null) {
            $capped = $tab->spells ? !$prereqs->hasFreeSpellSlot($skill->getLevel()) : $prereqs->isFull();
            $price = $isPassive ? $this->passives->getPrice($skill->getLevel()) : $this->actions->getPrice($skill->getLevel());
            $button = SkillPurchaseHandler::buyButton($name, $isPassive ? 'passive' : 'active', $price, $gold, $owned, $learnable, $capped, $usable, $tab->spells ? 'Aucun emplacement' : 'Max atteint');
        }

        return '<div class="ws-card ' . $state . '">'
            . '<img src="img/spells/' . $this->esc($image) . '.jpeg" alt="" />'
            . '<div class="ws-card-body">'
            . '<strong class="' . self::colorClass($skill->getCategory()) . '">' . $this->esc($skill->getDisplayName()) . '</strong>'
            . '<div class="ws-meta"><span class="ws-state">' . $stateLabel . '</span>' . $meta . '</div>'
            . '<i class="ws-effect">' . $this->esc($skill->getText()) . '</i>'
            . $this->needs($skill->getPrerequisites(), $prereqs, $displayNames)
            . $button
            . '</div>'
            . '</div>';
    }

    /** The need/forbidden lists as lines, each name ticked when it holds. */
    private function needs(?string $json, SkillPrerequisiteService $prereqs, array $displayNames): string
    {
        $lists = json_decode($json ?? '', true) ?: [];
        $lines = '';
        foreach (['need' => 'Requiert', 'forbidden' => 'Incompatible avec'] as $key => $label) {
            $items = [];
            foreach ($lists[$key] ?? [] as $other) {
                $ok = ($key === 'need') === $prereqs->owns($other);
                $items[] = '<span class="' . ($ok ? 'ws-ok' : 'ws-ko') . '">' . $this->esc($displayNames[$other] ?? $other) . '</span>';
            }
            if ($items !== []) {
                $lines .= '<div class="ws-needs">' . $label . ' : ' . implode(', ', $items) . '</div>';
            }
        }

        return $lines;
    }

    private function learnable(Action|ActionPassive $skill, Player $player): bool
    {
        $race = $skill->getRace();

        return empty($race) || $player->data->race == $race;
    }

    /** The name colour follows the sub-category after the dash (melee-off, spell-buff…). */
    private static function colorClass(?string $category): string
    {
        $sub = explode('-', (string) $category)[1] ?? '';

        return in_array($sub, ['off', 'support', 'buff', 'curse'], true) ? 'ws-' . $sub : '';
    }
}
