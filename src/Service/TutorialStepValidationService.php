<?php

namespace App\Service;

use App\Tutorial\TutorialContextKeys;
use App\Tutorial\TutorialOptions;
use InvalidArgumentException;

/**
 * Tutorial Step Validation Service
 *
 * Validates user input for tutorial step creation/editing.
 * Provides defense-in-depth security by validating all inputs
 * before they reach the database layer.
 *
 * Accepted option values are sourced from {@see TutorialOptions}, which is
 * also consumed by the admin editor dropdowns — single source of truth.
 */
class TutorialStepValidationService
{

    /**
     * Validate step number
     *
     * @param mixed $value User input
     * @return float Validated step number
     * @throws InvalidArgumentException If invalid
     */
    public function validateStepNumber($value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Le numéro d\'étape doit être un nombre.');
        }

        $stepNumber = (float)$value;

        if ($stepNumber < 0 || $stepNumber > 999) {
            throw new InvalidArgumentException('Le numéro d\'étape doit être compris entre 0 et 999.');
        }

        return $stepNumber;
    }

    /**
     * Validate step ID (optional alphanumeric identifier)
     *
     * @param mixed $value User input
     * @return string|null Validated step ID or null
     * @throws InvalidArgumentException If invalid
     */
    public function validateStepId($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $stepId = (string)$value;

        if (strlen($stepId) > 50) {
            throw new InvalidArgumentException('L\'identifiant d\'étape ne doit pas dépasser 50 caractères.');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $stepId)) {
            throw new InvalidArgumentException('L\'identifiant d\'étape ne peut contenir que des lettres, des chiffres, des tirets et des tirets bas.');
        }

        return $stepId;
    }

    /**
     * Validate step type
     *
     * @param mixed $value User input
     * @return string Validated step type
     * @throws InvalidArgumentException If invalid
     */
    public function validateStepType($value): string
    {
        $stepType = (string)$value;
        $valid = TutorialOptions::stepTypeKeys();

        if (!in_array($stepType, $valid, true)) {
            throw new InvalidArgumentException(
                'Type d\'étape inconnu. Valeurs possibles : ' . implode(', ', $valid)
            );
        }

        return $stepType;
    }

    /**
     * Validate version string
     *
     * @param mixed $value User input
     * @return string Validated version
     * @throws InvalidArgumentException If invalid
     */
    public function validateVersion($value): string
    {
        $version = (string)$value;

        if (empty($version)) {
            return '1.0.0';
        }

        if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9]+)?$/', $version)) {
            throw new InvalidArgumentException('La version doit avoir la forme X.Y.Z ou X.Y.Z-suffixe (ex. 1.0.0 ou 2.0.0-craft).');
        }

        return $version;
    }

    /**
     * Validate XP reward
     *
     * @param mixed $value User input
     * @return int Validated XP (0 if empty)
     */
    public function validateXpReward($value): int
    {
        if (empty($value)) {
            return 0;
        }

        $xp = (int)$value;

        if ($xp < 0) {
            throw new InvalidArgumentException('La récompense en XP ne peut pas être négative.');
        }

        if ($xp > 10000) {
            throw new InvalidArgumentException('La récompense en XP ne peut pas dépasser 10 000.');
        }

        return $xp;
    }

    /**
     * Validate coordinate (X or Y)
     *
     * @param mixed $value User input
     * @return int|null Validated coordinate or null
     * @throws InvalidArgumentException If invalid
     */
    public function validateCoordinate($value): ?int
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        $coord = (int)$value;

        if ($coord < -100 || $coord > 100) {
            throw new InvalidArgumentException('Les coordonnées doivent être comprises entre -100 et 100.');
        }

        return $coord;
    }

    /**
     * Validate validation type
     *
     * @param mixed $value User input
     * @return string|null Validated validation type or null
     * @throws InvalidArgumentException If invalid
     */
    public function validateValidationType($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $validationType = (string)$value;
        $valid = TutorialOptions::validationTypeKeys();

        if (!in_array($validationType, $valid, true)) {
            throw new InvalidArgumentException(
                'Type de validation inconnu. Valeurs possibles : ' . implode(', ', $valid)
            );
        }

        return $validationType;
    }

    /**
     * Validate interaction mode
     *
     * @param mixed $value User input
     * @return string Validated interaction mode
     * @throws InvalidArgumentException If invalid
     */
    public function validateInteractionMode($value): string
    {
        $mode = (string)($value ?? 'blocking');
        $valid = TutorialOptions::interactionModeKeys();

        if (!in_array($mode, $valid, true)) {
            throw new InvalidArgumentException(
                'Mode d\'interaction inconnu. Valeurs possibles : ' . implode(', ', $valid)
            );
        }

        return $mode;
    }

    /**
     * Validate tooltip position
     *
     * @param mixed $value User input
     * @return string Validated tooltip position
     * @throws InvalidArgumentException If invalid
     */
    public function validateTooltipPosition($value): string
    {
        $position = (string)($value ?? 'bottom');
        $valid = TutorialOptions::tooltipPositionKeys();

        if (!in_array($position, $valid, true)) {
            throw new InvalidArgumentException(
                'Position de bulle inconnue. Valeurs possibles : ' . implode(', ', $valid)
            );
        }

        return $position;
    }

    /**
     * Validate movement/PA required (allows -1 for race-adaptive max)
     *
     * @param mixed $value User input
     * @param int $max Maximum allowed value
     * @return int|null Validated integer or null
     * @throws InvalidArgumentException If invalid
     */
    public function validateResourceRequired($value, int $max = 999): ?int
    {
        if (empty($value) && $value !== 0 && $value !== '0' && $value !== -1 && $value !== '-1') {
            return null;
        }

        $int = (int)$value;

        // Allow -1 for race-adaptive max
        if ($int === -1) {
            return -1;
        }

        if ($int < 0) {
            throw new InvalidArgumentException('La valeur doit être -1 (maximum de la race) ou un nombre positif.');
        }

        if ($int > $max) {
            throw new InvalidArgumentException("La valeur ne peut pas dépasser {$max}.");
        }

        return $int;
    }

    /**
     * Validate positive integer (or null if empty)
     *
     * @param mixed $value User input
     * @param int $max Maximum allowed value
     * @return int|null Validated integer or null
     * @throws InvalidArgumentException If invalid
     */
    public function validatePositiveInt($value, int $max = 999): ?int
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        $int = (int)$value;

        if ($int < 0) {
            throw new InvalidArgumentException('La valeur ne peut pas être négative.');
        }

        if ($int > $max) {
            throw new InvalidArgumentException("La valeur ne peut pas dépasser {$max}.");
        }

        return $int;
    }

    /**
     * Validate CSS selector
     *
     * @param mixed $value User input
     * @return string|null Validated selector or null
     */
    public function validateCssSelector($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $selector = trim((string)$value);

        if (strlen($selector) > 500) {
            throw new InvalidArgumentException('Le sélecteur CSS ne peut pas dépasser 500 caractères.');
        }

        return $selector;
    }

    /**
     * Validate string with max length
     *
     * @param mixed $value User input
     * @param int $maxLength Maximum length
     * @return string|null Validated string or null
     * @throws InvalidArgumentException If too long
     */
    public function validateString($value, int $maxLength = 255): ?string
    {
        if (empty($value)) {
            return null;
        }

        $string = trim((string)$value);

        if (strlen($string) > $maxLength) {
            throw new InvalidArgumentException("La valeur ne peut pas dépasser {$maxLength} caractères.");
        }

        return $string;
    }

    /**
     * Validate text (longer strings)
     *
     * @param mixed $value User input
     * @param int $maxLength Maximum length
     * @return string|null Validated text or null
     * @throws InvalidArgumentException If too long
     */
    public function validateText($value, int $maxLength = 65535): ?string
    {
        if (empty($value)) {
            return null;
        }

        $text = trim((string)$value);

        if (strlen($text) > $maxLength) {
            throw new InvalidArgumentException("Le texte ne peut pas dépasser {$maxLength} caractères.");
        }

        return $text;
    }

    /**
     * Validate boolean checkbox value
     *
     * @param mixed $value User input (isset check)
     * @return bool True if checkbox was checked
     */
    public function validateCheckbox($value): bool
    {
        return !empty($value);
    }

    /**
     * Validate a context-change key.
     *
     * Only keys the runtime actually dispatches on are accepted — see
     * {@see TutorialContextKeys::CONTEXT_CHANGES}. Empty values return null
     * so the save service can skip blank rows without erroring.
     *
     * @param mixed $value User input
     * @return string|null Validated key or null if empty
     * @throws InvalidArgumentException If key is not whitelisted
     */
    public function validateContextChangeKey($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = trim((string)$value);
        if ($key === '') {
            return null;
        }

        $valid = TutorialContextKeys::contextChangeKeys();
        if (!in_array($key, $valid, true)) {
            throw new InvalidArgumentException(
                'Clé de contexte inconnue. Valeurs possibles : ' . implode(', ', $valid)
            );
        }

        return $key;
    }

    /**
     * Validate a next-step-preparation key.
     *
     * Only keys the runtime actually dispatches on are accepted — see
     * {@see TutorialContextKeys::NEXT_PREPARATIONS}. Empty values return null
     * so the save service can skip blank rows without erroring.
     *
     * @param mixed $value User input
     * @return string|null Validated key or null if empty
     * @throws InvalidArgumentException If key is not whitelisted
     */
    public function validatePreparationKey($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = trim((string)$value);
        if ($key === '') {
            return null;
        }

        $valid = TutorialContextKeys::nextPreparationKeys();
        if (!in_array($key, $valid, true)) {
            throw new InvalidArgumentException(
                'Clé de préparation inconnue. Valeurs possibles : ' . implode(', ', $valid)
            );
        }

        return $key;
    }
}
