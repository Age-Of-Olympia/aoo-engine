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
 * A war-school tab: the actives of a category and, except for spells, its
 * passives, each row with its buy button. The tabs differ by data only
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

    public function render(Player $player, string $category): void
    {
        $tab = self::tabs()[$category];
        $prereqs = SkillPrerequisiteService::forPlayer($player->getId());
        $gold = $player->get_gold();

        SkillPurchaseHandler::handlePost($player, $prereqs);

        $html = $this->header($tab, $prereqs, $gold)
            . $this->legend($tab)
            . $this->actives($tab, $player, $prereqs, $gold)
            . ($tab->spells ? '' : $this->passives($tab, $player, $prereqs, $gold));

        echo Str::minify($html);
        echo '<script src="js/warschool.js?v=20260714"></script>';
    }

    private function header(SkillTab $tab, SkillPrerequisiteService $prereqs, int $gold): string
    {
        if ($tab->spells) {
            $slots = [];
            for ($level = 1; $level <= 5; $level++) {
                $full = $prereqs->hasFreeSpellSlot($level) ? '' : ' class="ws-full"';
                $slots[] = 'lvl ' . $level . ' : <span' . $full . '>' . $prereqs->spellCountAt($level) . '/' . $prereqs->spellSlotsAt($level) . '</span>';
            }
            $info = 'Emplacements de sorts : ' . implode('&nbsp;&middot;&nbsp;', $slots);
        } else {
            $info = 'Compétences apprises : ' . $prereqs->capCount() . '/' . NUMBER_MAX_COMP . ' (sorts + passifs cumulés)';
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

    private function actives(SkillTab $tab, Player $player, SkillPrerequisiteService $prereqs, int $gold): string
    {
        $actions = $this->actions->getActionsByCategory($tab->category);
        $costView = new ActionCostView($this->actions);

        $rows = '';
        foreach ($actions as $action) {
            $capped = $tab->spells ? !$prereqs->hasFreeSpellSlot($action->getLevel()) : $prereqs->isFull();
            $price = $this->actions->getPrice($action->getLevel());
            $rows .= $this->row(
                $action,
                $player,
                '<td align="center"><strong>' . $costView->forAction($action) . '</strong></td>',
                SkillPurchaseHandler::buyButton($action->getName(), 'active', $price, $gold, $prereqs->owns($action->getName()), $this->learnable($action, $player), $capped, $prereqs->isUsable($action))
            );
        }

        return $this->section(
            $tab->spells ? null : 'Compétences actives',
            $rows,
            ['Icône', 'Nom', 'Effet', 'Coût', 'Race', 'Prix'],
            $tab->spells ? 'Aucun sort disponible.' : 'Aucune compétence active ' . $tab->of . ' disponible.'
        );
    }

    private function passives(SkillTab $tab, Player $player, SkillPrerequisiteService $prereqs, int $gold): string
    {
        $rows = '';
        foreach ($this->passives->getActionPassivesByCategory($tab->category) as $passive) {
            $price = $this->passives->getPrice($passive->getLevel());
            $rows .= $this->row(
                $passive,
                $player,
                '',
                SkillPurchaseHandler::buyButton($passive->getName(), 'passive', $price, $gold, $prereqs->owns($passive->getName()), $this->learnable($passive, $player), $prereqs->isFull(), $prereqs->isPassiveUsable($passive))
            );
        }

        return $this->section('Compétences passives', $rows, ['Icône', 'Nom', 'Effet', 'Race', 'Prix'], 'Aucune compétence passive ' . $tab->of . ' disponible.');
    }

    /** @param list<string> $columns */
    private function section(?string $title, string $rows, array $columns, string $empty): string
    {
        $body = $rows === ''
            ? '<p>' . $this->esc($empty) . '</p>'
            : '<table border="1" align="center" class="marbre"><thead><tr><th>' . implode('</th><th>', $columns) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';

        return '<div class="section">' . ($title === null ? '' : '<h2>' . $title . '</h2>') . $body . '</div>';
    }

    /** One row; $costCell is empty for passives, which have no cost column. */
    private function row(Action|ActionPassive $skill, Player $player, string $costCell, string $buyButton): string
    {
        $name = $skill->getName();
        $race = $skill->getRace();
        $image = file_exists('img/spells/' . $name . '.jpeg') ? $name : 'todo';

        return '<tr>'
            . '<td><img src="img/spells/' . $this->esc($image) . '.jpeg" /></td>'
            . '<td align="left"><strong class="' . self::colorClass($skill->getCategory()) . '">' . $this->esc($skill->getDisplayName()) . '</strong><br /><sup>Niveau ' . $skill->getLevel() . '</sup></td>'
            . '<td align="left" class="ws-effect"><i>' . $this->esc($skill->getText()) . '</i></td>'
            . $costCell
            . '<td align="center"><strong style="color: ' . RaceService::getRaceColor($race) . ';">' . (empty($race) ? 'Commun' : $this->esc(ucfirst($race))) . '</strong></td>'
            . '<td>' . $buyButton . '</td>'
            . '</tr>';
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
