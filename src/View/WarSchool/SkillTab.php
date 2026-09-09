<?php

namespace App\View\WarSchool;

/** One tab of the war school: which category it lists and how it introduces it. */
final class SkillTab
{
    /**
     * @param string       $category the actions' category key, and the passives' when $spells is false
     * @param string       $title    the page title
     * @param string       $of       the empty-state wording: "de mêlée" → "Aucune compétence active de mêlée disponible."
     * @param list<string> $legend   the explanation lines under the title (inline HTML allowed)
     * @param bool         $spells   the spell tab: no passives, one slot cap per level instead of the shared count
     */
    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $of,
        public readonly array $legend,
        public readonly bool $spells = false,
    ) {
    }
}
