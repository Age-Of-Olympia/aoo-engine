<?php

namespace App\Service;

use RuntimeException;

/**
 * CSRF Protection Service
 *
 * Provides Cross-Site Request Forgery protection for forms.
 * Generates and validates CSRF tokens to prevent unauthorized form submissions.
 */
class CsrfProtectionService
{
    private const TOKEN_LENGTH = 32;
    private const TOKEN_KEY = 'csrf_token';

    /**
     * Generate a new CSRF token and store in session
     *
     * @return string The generated token
     * @throws RuntimeException If session not started
     */
    public function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session must be active to generate CSRF token');
        }

        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Get current CSRF token from session
     *
     * @return string|null Current token or null if not set
     */
    public function getToken(): ?string
    {
        return $_SESSION[self::TOKEN_KEY] ?? null;
    }

    /**
     * Validate CSRF token from user input
     *
     * @param string|null $userToken Token provided by user
     * @return bool True if token is valid
     */
    public function validateToken(?string $userToken): bool
    {
        if (empty($userToken)) {
            return false;
        }

        $sessionToken = $this->getToken();

        if (empty($sessionToken)) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $userToken);
    }

    /**
     * Validate token or throw exception
     *
     * @param string|null $userToken Token provided by user
     * @throws RuntimeException If token is invalid
     */
    public function validateTokenOrFail(?string $userToken): void
    {
        if (!$this->validateToken($userToken)) {
            throw new RuntimeException('CSRF token validation failed. Please refresh the page and try again.');
        }
    }

    /**
     * Render hidden input field with CSRF token
     *
     * @return string HTML input field
     */
    public function renderTokenField(): string
    {
        $token = $this->generateToken();
        $escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$escaped}\">";
    }

    /**
     * Regenerate token (call after successful form submission)
     */
    public function regenerateToken(): void
    {
        unset($_SESSION[self::TOKEN_KEY]);
        $this->generateToken();
    }
}
