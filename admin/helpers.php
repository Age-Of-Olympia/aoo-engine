<?php
/**
 * Admin Helper Functions
 *
 * Common utility functions for admin panel.
 * Provides consistent patterns for input handling, output escaping, and data operations.
 */

/**
 * Escape output for HTML (XSS prevention)
 *
 * @param mixed $value Value to escape
 * @return string Escaped string (empty string if null)
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Short type label for an Action / instruction entity: its class short name
 * without the trailing "Action" suffix, lowercased (e.g. MeleeAction → "melee").
 *
 * @param object $action Entity whose class name ends in "Action"
 */
function action_type_label(object $action): string
{
    $shortName = (new ReflectionClass($action))->getShortName();

    return strtolower(substr($shortName, 0, -strlen('Action')));
}

/**
 * Get optional string from POST data
 *
 * @param string $key POST key
 * @return string|null String value or null if empty
 */
function optionalString(string $key): ?string
{
    $value = $_POST[$key] ?? null;
    return !empty($value) ? trim((string)$value) : null;
}

/**
 * Get optional integer from POST data
 *
 * @param string $key POST key
 * @return int|null Integer value or null if empty
 */
function optionalInt(string $key): ?int
{
    $value = $_POST[$key] ?? null;

    // Handle "0" as valid (not empty)
    if ($value === 0 || $value === '0') {
        return 0;
    }

    return !empty($value) ? (int)$value : null;
}

/**
 * Get boolean from checkbox (checked = true)
 *
 * @param string $key POST key
 * @return bool True if checkbox was checked
 */
function booleanCheckbox(string $key): bool
{
    return isset($_POST[$key]);
}

/**
 * Get string with default value
 *
 * @param string $key POST key
 * @param string $default Default value if not set
 * @return string String value or default
 */
function stringWithDefault(string $key, string $default): string
{
    return optionalString($key) ?? $default;
}

/**
 * Get integer with default value
 *
 * @param string $key POST key
 * @param int $default Default value if not set
 * @return int Integer value or default
 */
function intWithDefault(string $key, int $default): int
{
    return optionalInt($key) ?? $default;
}

/**
 * Convert string array to trimmed values, removing empties
 *
 * @param array $values Array of strings
 * @return array Cleaned array
 */
function cleanStringArray(array $values): array
{
    return array_filter(array_map('trim', $values), function($value) {
        return $value !== '' && $value !== null;
    });
}

/**
 * Safely get array value
 *
 * @param array $array Source array
 * @param string $key Array key
 * @param mixed $default Default value if key doesn't exist
 * @return mixed Value or default
 */
function arrayGet(array $array, string $key, $default = null)
{
    return $array[$key] ?? $default;
}

/**
 * Check if value is "truthy" for database boolean
 *
 * @param mixed $value Value to check
 * @return int 1 if truthy, 0 otherwise
 */
function dbBoolean($value): int
{
    return $value ? 1 : 0;
}

/**
 * Render flash message (and clear it)
 *
 * @return string HTML for flash message or empty string
 */
function renderFlashMessage(): string
{
    if (!isset($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type = e($flash['type'] ?? 'info');
    $message = e($flash['message'] ?? '');

    return <<<HTML
    <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
        {$message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
HTML;
}

/**
 * Set flash message
 *
 * @param string $type Type (success, danger, warning, info)
 * @param string $message Message to display
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Redirect and exit
 *
 * @param string $url URL to redirect to
 */
function redirectTo(string $url): never
{
    header("Location: {$url}");
    exit;
}

/**
 * Render checked attribute for checkbox
 *
 * @param bool $condition Condition
 * @return string 'checked' or empty string
 */
function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

/**
 * Render selected attribute for select option
 *
 * @param bool $condition Condition
 * @return string 'selected' or empty string
 */
function selected(bool $condition): string
{
    return $condition ? 'selected' : '';
}

/**
 * Render <option> tags from a [value => label] map.
 *
 * Pass $placeholder to prepend a disabled "-- Choose --" style option with
 * an empty value. $current is matched against keys (strict string compare).
 *
 * @param array<string,string> $options [value => label]
 * @param string|null $current Currently selected value (null = none)
 * @param string|null $placeholder Optional placeholder label (null = omit)
 */
function renderSelectOptions(array $options, ?string $current = null, ?string $placeholder = null): string
{
    $html = '';
    if ($placeholder !== null) {
        $html .= '<option value="">' . e($placeholder) . '</option>';
    }
    foreach ($options as $value => $label) {
        $value = (string)$value;
        $isSelected = $current !== null && $current === $value;
        $html .= sprintf(
            '<option value="%s"%s>%s</option>',
            e($value),
            $isSelected ? ' selected' : '',
            e($label)
        );
    }
    return $html;
}

/**
 * Render a <datalist> element for autocomplete on <input list="...">.
 *
 * Each option shows the value plus the label in its description slot, so the
 * browser's autocomplete dropdown surfaces what the key does, not just its
 * name.
 *
 * @param string $id Datalist id (must match input list="...")
 * @param array<string,string> $options [value => label]
 */
function renderDatalist(string $id, array $options): string
{
    $html = '<datalist id="' . e($id) . '">';
    foreach ($options as $value => $label) {
        $html .= sprintf(
            '<option value="%s" label="%s"></option>',
            e((string)$value),
            e($label)
        );
    }
    $html .= '</datalist>';
    return $html;
}

/* ------------------------------------------------------------------ */
/* Rapports de validation des plans (section « Cartes »)               */
/* ------------------------------------------------------------------ */

/**
 * Rend un groupe de validation (erreurs / avertissements / OK) sous forme
 * d'accordéon <details> coloré. Partagé entre local_maps.php (vue d'ensemble
 * et détail) et plans.php pour un rendu cohérent (DRY). Un groupe vide
 * n'affiche rien.
 *
 * @param string[] $items Messages déjà prêts à l'affichage (parties dynamiques échappées par PlanJsonValidator)
 */
function render_validation_group(array $items, string $variant, string $icon, string $color, string $label, bool $open): void
{
    if (empty($items)) {
        return;
    }
    $openAttr   = $open ? ' open' : '';
    $badgeStyle = 'background-color:' . $color . ';color:#fff;';
    echo '<details class="alert alert-' . $variant . ' mb-2" style="padding:0;"' . $openAttr . '>';
    echo   '<summary style="cursor:pointer;padding:.5rem .75rem;font-weight:600;">';
    echo     '<i class="fas ' . $icon . '"></i> ' . e($label);
    echo     ' <span class="badge" style="' . $badgeStyle . '">' . count($items) . '</span>';
    echo   '</summary>';
    echo   '<ul class="mb-0" style="padding:.25rem .75rem .75rem 2.2rem;font-size:13px;line-height:1.6;">';
    foreach ($items as $msg) {
        echo '<li>' . $msg . '</li>';
    }
    echo   '</ul>';
    echo '</details>';
}

/**
 * Rend les groupes de validation en distinguant les domaines « niveaux Z » et
 * « biomes » : erreurs (Z puis biomes), avertissements (Z puis biomes), puis
 * (optionnel) les validations OK. Chaque groupe vide est ignoré.
 *
 * @param array{z: array{errors: string[], warnings: string[], ok: string[]}, biome: array{errors: string[], warnings: string[], ok: string[]}} $validation
 */
function render_validation_report(array $validation, bool $includeOk = true): void
{
    $z = $validation['z'];
    $b = $validation['biome'];

    render_validation_group($z['errors'],   'danger',  'fa-times-circle',        '#dc3545', 'Erreurs (niveaux Z)', true);
    render_validation_group($b['errors'],   'danger',  'fa-times-circle',        '#dc3545', 'Erreurs (biomes)',    true);
    render_validation_group($z['warnings'], 'warning', 'fa-exclamation-triangle', '#f0ad4e', 'Avertissements (niveaux Z)', true);
    render_validation_group($b['warnings'], 'warning', 'fa-exclamation-triangle', '#f0ad4e', 'Avertissements (biomes)',    true);
    if ($includeOk) {
        render_validation_group($z['ok'], 'success', 'fa-check-circle', '#198754', 'Validations OK (niveaux Z)', false);
        render_validation_group($b['ok'], 'success', 'fa-check-circle', '#198754', 'Validations OK (biomes)',    false);
    }
}

/* ------------------------------------------------------------------ */
/* Filtre de saison des plans (section « Cartes »)                     */
/* ------------------------------------------------------------------ */

/**
 * Plans hors « _s2 » qui font tout de même partie de la saison 2 :
 * la map globale (olympia) et les enfers.
 */
const SEASON2_EXTRA_PLANS = ['olympia', 'enfers'];

/**
 * Un plan relève-t-il de la saison 2 ? Vrai pour tout id « _s2 » et pour
 * les plans hors-saison listés dans SEASON2_EXTRA_PLANS.
 *
 * @param object $plan Plan issu de ViewService::getAllPlans()
 */
function is_season2_plan(object $plan): bool
{
    return $plan->isS2 || in_array($plan->id, SEASON2_EXTRA_PLANS, true);
}

/**
 * Filtre de saison courant des pages « Cartes » : « s2 » (saison en cours,
 * défaut), « s1 » (plans sans suffixe _s2) ou « all ». Le choix, posté via
 * season_filter, persiste en session pour survivre à la navigation par
 * formulaires POST et s'appliquer à toutes les pages de la section.
 */
function current_season_filter(): string
{
    $valid = ['s2', 's1', 'all'];

    $requested = $_REQUEST['season_filter'] ?? null;
    if (is_string($requested) && in_array($requested, $valid, true)) {
        $_SESSION['admin_season_filter'] = $requested;
    }

    $current = $_SESSION['admin_season_filter'] ?? 's2';
    return in_array($current, $valid, true) ? $current : 's2';
}

/**
 * Un plan passe-t-il le filtre de saison ? « s1 » = plans sans _s2 (la map
 * globale et les enfers, jouables dans les deux saisons, y figurent aussi).
 *
 * @param object $plan Plan issu de ViewService::getAllPlans()
 */
function plan_matches_season_filter(object $plan, string $filter): bool
{
    return match ($filter) {
        's2'    => is_season2_plan($plan),
        's1'    => !$plan->isS2,
        default => true,
    };
}

/** Libellé humain d'un filtre de saison (titres de sections). */
function season_filter_label(string $filter): string
{
    return match ($filter) {
        's2'    => 'saison 2',
        's1'    => 'saison 1',
        default => 'toutes saisons',
    };
}

/**
 * Sélecteur de saison auto-soumis (radios inline). Formulaire POST sans
 * autre champ : changer de saison réinitialise volontairement la sélection
 * de plan, qui peut ne plus correspondre au nouveau filtre. Pas de jeton
 * CSRF : aucun état serveur modifié hormis la préférence d'affichage.
 */
function render_season_filter(string $current): string
{
    $choices = ['s2' => 'Saison 2 (courante)', 's1' => 'Saison 1', 'all' => 'Toutes'];

    $html = '<form method="post" class="d-flex align-items-center gap-3 flex-wrap" style="font-size:13px;">';
    $html .= '<span class="text-muted"><i class="fas fa-filter"></i> Plans affichés :</span>';
    foreach ($choices as $value => $label) {
        $html .= '<label class="mb-0" style="cursor:pointer;">'
            . '<input type="radio" name="season_filter" value="' . e($value) . '"'
            . checked($current === $value)
            . ' onchange="this.form.submit()"> '
            . e($label)
            . '</label>';
    }
    $html .= '</form>';

    return $html;
}
