<?php
/**
 * Effect catalog — mutations (POST only). Companion to admin/effects.php.
 *
 * Routed on ?action: create | update | delete. Delete is guarded: refused as
 * long as any character still carries the effect (players_effects rows) —
 * an effect referenced only by action parameters can be deleted, the
 * workbench then shows its ⚠ sentinel option.
 *
 * CSRF-validated; enforces the same access level as the effects menu so a
 * direct POST can't bypass it. Redirects back (PRG) with a flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\Effect;
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\EffectService;

(new AdminMenuAccessService())->enforce('effects.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/effects.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/effects.php');
}

$service = new EffectService();
$action = $_GET['action'] ?? '';
$name = strtolower(trim((string) ($_POST['name'] ?? '')));

// La suppression ne porte que le nom : traitée avant la validation des
// champs du formulaire (absents d'un POST de suppression).
if ($action === 'delete') {
    $effect = $service->getEffectByName($name);
    if ($effect === null) {
        setFlash('warning', 'Effet introuvable.');
        redirectTo('/admin/effects.php');
    }

    try {
        $service->deleteEffect($effect);
        setFlash('success', "Effet « {$name} » supprimé.");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/effects.php?action=edit&name=' . urlencode($name));
    }
    redirectTo('/admin/effects.php');
}

/** Validate the shared form fields; returns an error message or null. */
$validate = static function () use ($service): ?string {
    if (trim((string) ($_POST['label'] ?? '')) === '') {
        return 'Le nom affiché est requis.';
    }
    if (!preg_match('/^ra-[a-z0-9-]+$/', (string) ($_POST['icon'] ?? ''))) {
        return 'Icône invalide (classe RPG-Awesome attendue, ex : ra-small-fire).';
    }
    $breakChance = trim((string) ($_POST['corruption_break_chance'] ?? ''));
    if ($breakChance !== '' && (!is_numeric($breakChance) || (int) $breakChance < 0 || (int) $breakChance > 100)) {
        return 'Chance de casse invalide (0-100, ou vide).';
    }
    foreach (['buff_carac', 'debuff_carac'] as $field) {
        $carac = trim((string) ($_POST[$field] ?? ''));
        if ($carac !== '' && !isset(CARACS[$carac])) {
            return "Caractéristique inconnue : {$carac}.";
        }
    }
    foreach ((array) ($_POST['controls'] ?? []) as $controlled) {
        $controlled = trim((string) $controlled);
        if ($controlled !== '' && !$service->exists($controlled)) {
            return "Effet à annuler inconnu du catalogue : {$controlled}.";
        }
    }
    return null;
};

/** Apply every form field onto the entity (shared by create and update). */
$applyForm = static function (Effect $effect): void {
    $effect->setLabel(trim((string) $_POST['label']));
    $effect->setDescription(trim((string) ($_POST['description'] ?? '')));
    $effect->setIcon((string) $_POST['icon']);
    $effect->setHidden(booleanCheckbox('hidden'));
    $effect->setMapMarker(booleanCheckbox('is_map_marker'));
    $effect->setBuffCarac(trim((string) ($_POST['buff_carac'] ?? '')));
    $effect->setDebuffCarac(trim((string) ($_POST['debuff_carac'] ?? '')));

    foreach (['setRollAttackMod' => 'roll_attack_mod', 'setRollDefenseMod' => 'roll_defense_mod',
              'setDamageDealtMod' => 'damage_dealt_mod', 'setDamageTakenMod' => 'damage_taken_mod',
              'setPushAttackMod' => 'push_attack_mod', 'setPushDefenseMod' => 'push_defense_mod'] as $setter => $field) {
        $effect->{$setter}(max(-1, min(1, (int) ($_POST[$field] ?? 0))));
    }
    $factor = (float) ($_POST['damage_taken_factor'] ?? 1);
    $effect->setDamageTakenFactor($factor > 0 ? $factor : 1.0);
    $effect->setBlockRecovery(trim((string) ($_POST['block_recovery'] ?? '')));
    $effect->setTurnRegen(booleanCheckbox('turn_regen'));
    $effect->setTurnMvtMalus(booleanCheckbox('turn_mvt_malus'));

    // Posture de défense + présences — les setters valident les enums
    // (valeur inconnue → champ vidé, jamais de valeur folle en base).
    $effect->setDodgeScope(trim((string) ($_POST['dodge_scope'] ?? '')));
    $effect->setDodgeAttackerWeapon(trim((string) ($_POST['dodge_attacker_weapon'] ?? '')));
    $effect->setDodgeDefenderWeapon(trim((string) ($_POST['dodge_defender_weapon'] ?? '')));
    $effect->setDodgeReaction(trim((string) ($_POST['dodge_reaction'] ?? '')));
    $effect->setDodgeMessage(trim((string) ($_POST['dodge_message'] ?? '')));
    $effect->setGrantsFlight(booleanCheckbox('grants_flight'));
    $effect->setCostMultiplier(booleanCheckbox('cost_multiplier'));
    $effect->setBlocksTrading(booleanCheckbox('blocks_trading'));
    $effect->setStackRefreshDuration(booleanCheckbox('stack_refresh_duration'));

    $breakChance = trim((string) ($_POST['corruption_break_chance'] ?? ''));
    $effect->setCorruptionBreakChance($breakChance === '' ? null : (int) $breakChance);
};

/** One material per non-empty line. */
$materials = static fn (): array =>
    preg_split('/\R+/', trim((string) ($_POST['corruption_materials'] ?? ''))) ?: [];

/** Selected cancellation targets, lowercased. */
$controls = static fn (): array =>
    array_map(static fn ($name): string => strtolower(trim((string) $name)), (array) ($_POST['controls'] ?? []));

if ($error = $validate()) {
    setFlash('warning', $error);
    redirectTo('/admin/effects.php');
}

if ($action === 'create') {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
        setFlash('warning', 'Code d\'effet invalide (minuscules, chiffres, _).');
        redirectTo('/admin/effects.php?action=new');
    }
    if ($service->getEffectByName($name) !== null) {
        setFlash('warning', "L'effet « {$name} » existe déjà.");
        redirectTo('/admin/effects.php');
    }

    $effect = new Effect($name);
    $applyForm($effect);
    $service->save($effect);
    $service->replaceCorruptionMaterials($effect, $materials());
    $service->replaceControls($effect, $controls());

    setFlash('success', "Effet « {$name} » créé.");
    redirectTo('/admin/effects.php');
}

if ($action === 'update') {
    $effect = $service->getEffectByName($name);
    if ($effect === null) {
        setFlash('warning', 'Effet introuvable.');
        redirectTo('/admin/effects.php');
    }

    $applyForm($effect);
    $service->save($effect);
    $service->replaceCorruptionMaterials($effect, $materials());
    $service->replaceControls($effect, $controls());

    setFlash('success', 'Effet « ' . $effect->getLabel() . ' » enregistré.');
    redirectTo('/admin/effects.php?action=edit&name=' . urlencode($name));
}

setFlash('warning', 'Action inconnue.');
redirectTo('/admin/effects.php');
