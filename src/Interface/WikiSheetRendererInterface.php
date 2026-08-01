<?php

namespace App\Interface;

use App\Interface\ObjectExporterInterface;
/**
 * Rend une famille du catalogue en fiche « wiki compatible »
 * (markup DokuWiki, collé tel quel sur age-of-olympia.net/wiki).
 *
 * Décision du 2026-07-19 : MÊME PROCESSUS que l'export de bundles,
 * AUTRE FORMAT — chaque renderer consomme les tableaux de l'exporter de
 * sa famille (ObjectExporterInterface::exportAll, clés naturelles) et ne fait
 * que la mise en forme. Une seule source de vérité par catalogue :
 * JSON pour les machines, DokuWiki pour les humains, garantis
 * cohérents. Modèle : le wiki des effets (description rédigée + faits
 * dérivés du moteur — « le wiki ne peut pas mentir »).
 */
interface WikiSheetRendererInterface
{
    /** La famille servie — même clé que l'ExporterRegistry ('action'…). */
    public function objectType(): string;

    /** Libellé humain de la famille (sélecteur de la page admin). */
    public function title(): string;

    /** La fiche complète, en markup DokuWiki. */
    public function render(): string;
}
