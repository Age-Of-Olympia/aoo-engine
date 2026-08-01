<?php

namespace App\View\Action;

/**
 * The "hérité de … / surchargé ici" banner shared by the per-type XP and log
 * editors. Expects the using class to provide esc() (via {@see EscapesHtmlTrait}).
 */
trait RendersInheritanceBannerTrait
{
    private function inheritanceBanner(string $typeKey, ?string $inheritedFrom, ?string $overriddenParent): string
    {
        if ($inheritedFrom !== null) {
            return '<p class="wb-inherited">Hérité du type « ' . $this->esc($inheritedFrom) . ' ». '
                . 'Enregistrer créera une surcharge pour « ' . $this->esc($typeKey) . ' ».</p>';
        }
        if ($overriddenParent !== null) {
            return '<p class="wb-inherited">Hérité du type « ' . $this->esc($overriddenParent) . ' » mais surchargé ici.</p>';
        }

        return '';
    }
}
