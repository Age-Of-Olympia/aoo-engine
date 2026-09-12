<?php
/**
 * Race management — mutations (POST only). Companion to admin/races.php.
 *
 * Routed on ?action: create | update | delete. Delete is guarded: refused as
 * long as any character (player or PNJ) still has players.race = name —
 * retiring a race in use = uncheck "jouable" + check "cachée" instead.
 *
 * CSRF-validated; enforces the same access level as the races menu so a
 * direct POST can't bypass it. Redirects back (PRG) with a flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\Race;
use App\Service\ActionService;
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\FactionService;
use App\Service\RaceService;

(new AdminMenuAccessService())->enforce('races.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/races.php');
}

/* Deux sections, une table : les mutations d'une sorte « structure »
 * renvoient vers Types de bâtiments, pas vers Races — messages compris
 * (chaque formulaire, suppression incluse, poste son kind). */
$face = \App\View\Admin\TypeEditorFace::fromRequest($_POST);
$structureMode = $face->isStructure();
$backPage = $face->page;

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo($backPage);
}

$service = new RaceService();
$action = $_GET['action'] ?? '';
$name = strtolower(trim((string) ($_POST['name'] ?? '')));

// La suppression ne porte que le nom (et le kind) : traitée avant la
// validation des champs du formulaire (absents d'un POST de suppression).
if ($action === 'delete') {
    $race = $service->getRaceByName($name);
    if ($race === null) {
        setFlash('warning', $structureMode ? 'Type introuvable.' : 'Race introuvable.');
        redirectTo($backPage);
    }

    try {
        $service->deleteRace($race);
        setFlash('success', $structureMode
            ? "Type de bâtiment « {$name} » supprimé."
            : "Race « {$name} » supprimée (listes d'actions et de sorts comprises).");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo($backPage . '?action=edit&name=' . urlencode($name));
    }
    redirectTo($backPage);
}

/**
 * Validate the shared form fields; returns an error message or null.
 * bgColor must stay hex: it feeds sscanf("#%02x%02x%02x") in the map layers.
 */
$validate = static function (): ?string {
    if (trim((string) ($_POST['label'] ?? '')) === '') {
        return 'Le nom affiché est requis.';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['bgColor'] ?? ''))) {
        return 'Couleur de fond invalide (format attendu : #RRGGBB).';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['wound_color'] ?? ''))) {
        return 'Couleur de blessure invalide (format attendu : #RRGGBB).';
    }
    foreach (array_keys(CARACS) as $key) {
        if (!isset($_POST['carac'][$key]) || !is_numeric($_POST['carac'][$key])) {
            return "Caractéristique manquante ou invalide : {$key}.";
        }
    }
    return null;
};

/**
 * Apply every form field onto the entity (shared by create and update).
 *
 * @return array{notice: string, newFamily: string} notice: appended to the
 *         success flash ('' when all clean). newFamily: the family the
 *         posted structure_nature resolves to (Race::FAMILY_*) — the update
 *         branch compares it to $race->familyKey() to know whether the raw
 *         type_kind fixup below is needed.
 */
$applyForm = static function (Race $race) use ($face, $action): array {
    $notice = '';

    $race->setLabel(trim((string) $_POST['label']));
    $race->setDescription(trim((string) ($_POST['description'] ?? '')));
    // Sorte : personnage (défaut) ou structure. Une structure n'est jamais
    // proposée à l'inscription, quel que soit l'état de la case Jouable.
    $kind = ($_POST['kind'] ?? 'character') === 'structure' ? 'structure' : 'character';
    $race->setKind($kind);
    /* Nature (structures seulement) : édifice/obstacle (bâtiment), décor,
     * ressource ou plante.
     *
     * À la CRÉATION, chaque visage impose la sienne (resolveNature) — un
     * type créé depuis Types récoltables ne doit pas pouvoir naître ailleurs
     * que ressource. La vieille garde ne couvrait que le décor : un type créé
     * depuis Types récoltables était corrigé une ligne après sa création et
     * ne rejoignait jamais la palette des ressources.
     *
     * À la MODIFICATION, la vue édition offre un vrai choix de catégorie
     * (les 5 valeurs) : l'admin peut reclasser un type existant, posté ici
     * directement plutôt que pincé au visage courant. */
    $allowedNatures = ['edifice', 'obstacle', 'decor', 'ressource', 'plante'];
    $postedNature = (string) ($_POST['structure_nature'] ?? '');
    $newNature = ($action === 'update' && in_array($postedNature, $allowedNatures, true))
        ? $postedNature
        : $face->resolveNature($postedNature !== '' ? $postedNature : null);
    // Race::ofFamily() sait déjà dériver une famille de (kind, nature) — la
    // même règle que le déclencheur SQL races_type_kind_bu (voir
    // Version20260801200000_TheFamilyBecomesWritable). On lui demande une
    // coquille vide plutôt que de la redire ici : Doctrine, pas ce
    // déclencheur, écrit type_kind sur CE flush (il respecte une valeur déjà
    // posée par l'ORM, la sienne — pas celle qu'on voudrait), donc il faut
    // la famille cible pour la forcer nous-mêmes ensuite.
    $newFamily = Race::ofFamily($kind, $newNature)->familyKey();

    // La classe PHP de $race ne change qu'au PROCHAIN chargement (PRG déjà
    // en place) : elle reste ce que Doctrine a instancié ce tour-ci, même
    // après avoir changé structure_nature ici. On s'en sert pour savoir ce
    // qu'il faut vider avant que la classe d'aujourd'hui ne redevienne muette.
    $stillHarvestable = in_array($newNature, ['ressource', 'plante'], true);

    $race->setStructureNature($newNature);
    // Saignement : un élément de carte connu, ou rien.
    $bleeds = trim((string) ($_POST['bleeds'] ?? ''));
    $race->setBleeds($bleeds !== '' && (new \App\Service\EffectService())->exists($bleeds) ? $bleeds : '');
    /* Inscription : ce qu'un exemplaire neuf porte déjà, et jusqu'où on
     * peut le lire. Réservé aux types de DÉCOR — un personnage écrit
     * son message du jour lui-même. */
    if ($race instanceof \App\Entity\StructureType) {
        $race->setReadableFromAfar(booleanCheckbox('readable_from_afar'));
        $race->setDefaultText(trim((string) ($_POST['default_text'] ?? '')));

        /* Three states: empty means "follow my family" and must stay null.
         * Reading it as "no" would cut the type off its family on the first
         * save, silently. */
        $repairable = (string) ($_POST['repairable'] ?? '');
        $race->setRepairable($repairable === '' ? null : $repairable === '1');
    }
    /* Rendement du type : on s'adresse à ce qui SAIT SE RÉCOLTER, pas à une
     * famille. Le vider rend le type muet jusqu'à ce qu'un plan le déclare ;
     * ce qui ne se récolte pas n'a même plus la question à se poser.
     *
     * Un reclassement qui QUITTE ressource/plante efface le rendement
     * plutôt que de le laisser en base, invisible dans tous les
     * formulaires suivants — prêt à ressurgir si le type redevient
     * récoltable plus tard sans qu'on y repense. */
    if ($race instanceof \App\Interface\HarvestableInterface) {
        if ($stillHarvestable) {
            /* L'objet vient d'une liste ; on le valide quand même contre le
             * catalogue — un POST fabriqué ne doit pas installer un rendement
             * qui ne rapporte rien. Vide reste vide : c'est « ne rend rien ». */
            $harvestItem = trim((string) ($_POST['harvest_item'] ?? ''));

            if ($harvestItem !== '' && \Classes\Item::get_item_by_name($harvestItem) === false) {
                $notice .= " ⚠ Objet « {$harvestItem} » inconnu du catalogue — rendement inchangé.";
                $harvestItem = $race->getHarvestItem();
            }

            $race->setHarvestItem($harvestItem);
            $race->setHarvestExhaust(trim((string) ($_POST['harvest_exhaust'] ?? '')) !== ''
                ? max(1, min(100, (int) $_POST['harvest_exhaust']))
                : null);
            $race->setHarvestRegrow(trim((string) ($_POST['harvest_regrow'] ?? '')) !== ''
                ? max(1, min(1000, (int) $_POST['harvest_regrow']))
                : null);
        } elseif ($race->getHarvestItem() !== '' || $race->getHarvestExhaust() !== null || $race->getHarvestRegrow() !== null) {
            $race->setHarvestItem('');
            $race->setHarvestExhaust(null);
            $race->setHarvestRegrow(null);
            $notice .= ' ⚠ Catégorie changée : le rendement (récolte) de ce type a été effacé.';
        }
    }

    /* Combien rend une plante : deux bornes, et un maximum qui ne passe pas
     * sous le minimum — un intervalle vide ne rendrait rien du tout. Quitter
     * la catégorie Plante remet les bornes à leur défaut plutôt que de les
     * laisser trainer, pour la même raison que ci-dessus. */
    if ($race instanceof \App\Entity\PlantType) {
        if ($newNature === 'plante') {
            $min = max(1, min(99, (int) ($_POST['harvest_min'] ?? \App\Entity\PlantType::DEFAULT_MIN)));
            $max = max($min, min(99, (int) ($_POST['harvest_max'] ?? \App\Entity\PlantType::DEFAULT_MAX)));

            $race->setHarvestMin($min);
            $race->setHarvestMax($max);
        } else {
            $race->setHarvestMin(\App\Entity\PlantType::DEFAULT_MIN);
            $race->setHarvestMax(\App\Entity\PlantType::DEFAULT_MAX);
        }
    }
    $race->setBlocksPassage(booleanCheckbox('blocks_passage'));
    $race->setBlocksProjectiles(booleanCheckbox('blocks_projectiles'));
    // Only the building form shows the field; an absent input must not
    // zero what another face never offered to edit.
    if (isset($_POST['build_work'])) {
        $race->setBuildWork(max(0, min(999, (int) $_POST['build_work'])));
    }
    /* The footprint: a full w×h box, only when the dimensions CHANGED —
     * a hand-tuned figure (holes, per-cell roles from Cartes → Emprises)
     * keeps its shape as long as the box stays the same. */
    if (isset($_POST['fp_w'], $_POST['fp_h'])) {
        $fpW = max(1, min(8, (int) $_POST['fp_w']));
        $fpH = max(1, min(8, (int) $_POST['fp_h']));
        if ($fpW !== (int) ($_POST['fp_prev_w'] ?? 0) || $fpH !== (int) ($_POST['fp_prev_h'] ?? 0)) {
            $offsets = [];
            for ($dy = 0; $dy > -$fpH; $dy--) {
                for ($dx = 0; $dx < $fpW; $dx++) {
                    $offsets[] = [$dx, $dy];
                }
            }
            (new \App\Service\Map\EntityTypeFootprintService())->declare($race->getName(), $fpW, $fpH, $offsets);
        }
    }
    $race->setPlayable($kind === 'character' && booleanCheckbox('playable'));
    $race->setHidden(booleanCheckbox('hidden'));
    $race->setBgColor((string) $_POST['bgColor']);
    $race->setColor(stringWithDefault('color', 'black'));
    $race->setWoundColor((string) $_POST['wound_color']);

    // Faction de départ : validée contre le catalogue (admin/factions.php).
    // Une valeur orpheline est conservée seulement si elle ne change pas
    // (option ⚠ du select) — jamais introduite.
    $faction = strtolower(trim((string) ($_POST['faction'] ?? '')));
    if ($faction === '' || $faction === $race->getFaction()
        || (new FactionService())->getFactionByCode($faction) !== null) {
        $race->setFaction($faction);
    } else {
        $notice = " ⚠ Faction « {$faction} » inconnue du catalogue — champ inchangé.";
    }
    $race->setPlan(trim((string) ($_POST['plan'] ?? '')));
    $race->setAnimateurId(optionalInt('animateurId'));

    foreach (array_keys(CARACS) as $key) {
        $race->setCarac($key, (int) $_POST['carac'][$key]);
    }
    $race->setCapacity((int) ($_POST['capacity'] ?? 0));

    return ['notice' => $notice, 'newFamily' => $newFamily];
};

/** One name per non-empty line. */
$linesToNames = static fn (string $field): array =>
    preg_split('/\R+/', trim((string) ($_POST[$field] ?? ''))) ?: [];

/**
 * Typo guard: names absent from the known-action catalog are saved anyway
 * (an action can be configured afterwards) but flagged in the flash so a
 * misspelled name doesn't silently fail to grant at character creation.
 */
$unknownNamesNotice = static function (array $names): string {
    $known = (new ActionService())->getKnownActionNames();
    $unknown = array_filter(array_map('trim', $names), static fn (string $n): bool => $n !== '' && !isset($known[$n]));

    return $unknown === []
        ? ''
        : ' ⚠ Noms inconnus du jeu (vérifiez l\'orthographe) : ' . implode(', ', array_unique($unknown)) . '.';
};

if ($error = $validate()) {
    setFlash('warning', $error);
    redirectTo($backPage);
}

if ($action === 'create') {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
        setFlash('warning', ($structureMode ? 'Code de type' : 'Code de race') . ' invalide (minuscules, chiffres, _).');
        redirectTo($backPage . '?action=new');
    }
    if ($service->getRaceByName($name) !== null) {
        setFlash('warning', $structureMode
            ? "Le type « {$name} » existe déjà (ou une race porte déjà ce code)."
            : "La race « {$name} » existe déjà.");
        redirectTo($backPage);
    }

    /* La famille se choisit à la création : c'est le visage d'où l'on vient
       qui la dit. Elle peut être reclassée ensuite (Catégorie, en édition,
       cf. applyForm) — mais jamais vers/depuis un personnage : un mur ne
       devient pas un peuple. */
    $race = Race::ofFamily(
        $face->isStructure() ? 'structure' : 'character',
        $face->nature()
    );
    $race->setName($name);
    $race->setCode(strtoupper($name));
    $factionNotice = $applyForm($race)['notice'];
    $service->save($race);
    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', ($structureMode ? "Type de bâtiment « {$name} » créé." : "Race « {$name} » créée.")
        . $factionNotice . $notice);
    redirectTo($backPage);
}

if ($action === 'update') {
    $race = $service->getRaceByName($name);
    if ($race === null) {
        setFlash('warning', $structureMode ? 'Type introuvable.' : 'Race introuvable.');
        redirectTo($backPage);
    }

    $formResult = $applyForm($race);
    $factionNotice = $formResult['notice'];
    $service->save($race);

    /* Doctrine écrit type_kind d'après la classe PHP de $race — celle
     * chargée AVANT ce reclassement, donc l'ancienne famille. Un
     * reclassement (Catégorie changée en édition) doit forcer la vraie
     * valeur par une écriture brute, hors ORM : sans elle, la ligne redevient
     * son ancienne classe au prochain chargement, malgré structure_nature. */
    if ($formResult['newFamily'] !== $race->familyKey()) {
        \App\Factory\EntityManagerFactory::getEntityManager()->getConnection()->executeStatement(
            'UPDATE races SET type_kind = ? WHERE name = ?',
            [$formResult['newFamily'], $name]
        );
        RaceService::clearCache();
    }

    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', ($structureMode ? 'Type « ' : 'Race « ') . $race->getLabel() . ' » enregistré'
        . ($structureMode ? '' : 'e') . '.' . $factionNotice . $notice);
    redirectTo($backPage . '?action=edit&name=' . urlencode($name));
}

setFlash('warning', 'Action inconnue.');
redirectTo($backPage);
