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
function redirectTo(string $url): void
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
