<?php

namespace App\Service;

use Classes\Db;

/**
 * Authentification des endpoints Tiled (api/admin/map/*).
 *
 * L'extension Tiled se connecte avec un compte du jeu (nom ou matricule +
 * mot de passe). Le compte doit posséder l'option isAdmin — lui-même ou via
 * l'un de ses PNJ (players_pnjs). Le serveur délivre un jeton signé HMAC,
 * sans état : « v1.<playerId>.<expiration>.<signature> ».
 *
 * Le secret de signature vit dans config/tiled_constants.php
 * (TILED_HMAC_SECRET, gitignoré) ; secret vide = endpoints désactivés.
 * Les droits admin sont revérifiés à chaque requête : retirer l'option
 * isAdmin d'un joueur invalide immédiatement ses jetons.
 */
class TiledAuthService
{
    private const TOKEN_VERSION = 'v1';
    private const TOKEN_TTL_SECONDS = 30 * 24 * 3600;

    public static function isEnabled(): bool
    {
        return defined('TILED_HMAC_SECRET') && TILED_HMAC_SECRET !== '';
    }

    /**
     * Vérifie identifiants + droits admin. Même logique de résolution que
     * login.php : nom (mots capitalisés) ou matricule numérique.
     */
    public static function authenticate(string $name, string $password): ?int
    {
        $db = new Db();

        if (is_numeric($name)) {
            $res = $db->exe('SELECT id, psw FROM players WHERE id = ?', array($name));
        } else {
            $name = implode(' ', array_map('ucfirst', explode(' ', $name)));
            $res = $db->exe('SELECT id, psw FROM players WHERE name = ?', array($name));
        }

        $row = $res->fetch_assoc();

        if (!$row || !password_verify($password, $row['psw'])) {
            return null;
        }

        $playerId = (int) $row['id'];

        return self::isAdmin($playerId) ? $playerId : null;
    }

    /** @return array{token: string, expiresAt: int} */
    public static function issueToken(int $playerId): array
    {
        $expiresAt = time() + self::TOKEN_TTL_SECONDS;
        $payload = self::TOKEN_VERSION . '.' . $playerId . '.' . $expiresAt;

        return [
            'token' => $payload . '.' . self::sign($payload),
            'expiresAt' => $expiresAt,
        ];
    }

    /** Retourne l'id du joueur si le jeton est valide ET toujours admin. */
    public static function validateToken(?string $token): ?int
    {
        if (!self::isEnabled() || !$token) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 4 || $parts[0] !== self::TOKEN_VERSION) {
            return null;
        }

        $payload = $parts[0] . '.' . $parts[1] . '.' . $parts[2];

        if (!hash_equals(self::sign($payload), $parts[3])) {
            return null;
        }

        if ((int) $parts[2] < time()) {
            return null;
        }

        $playerId = (int) $parts[1];

        return self::isAdmin($playerId) ? $playerId : null;
    }

    /**
     * Le compte lui-même a l'option isAdmin, ou bien l'un des PNJ qu'il
     * possède (players_pnjs) l'a — un MJ joue souvent via ses PNJ sans que
     * son compte principal soit admin.
     *
     * Requête directe plutôt que PlayerOptionsService + PlayerPnjService :
     * ce contrôle tourne à chaque requête de l'éditeur, une seule requête
     * indexée remplace 1 + N allers-retours.
     */
    private static function isAdmin(int $playerId): bool
    {
        $db = new Db();

        $res = $db->exe(
            'SELECT 1
             FROM players_options
             WHERE name = "isAdmin"
               AND (player_id = ?
                    OR player_id IN (SELECT pnj_id FROM players_pnjs WHERE player_id = ?))
             LIMIT 1',
            array($playerId, $playerId)
        );

        return $res->num_rows > 0;
    }

    private static function sign(string $payload): string
    {
        // constant() : TILED_HMAC_SECRET vit dans un fichier gitignoré,
        // PHPStan ne peut pas le découvrir (même pattern que OneSignal)
        return hash_hmac('sha256', $payload, (string) constant('TILED_HMAC_SECRET'));
    }
}
