<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\UserModel;

/**
 * Contexte d'authentification basé sur la session.
 *
 * Fournit l'utilisateur courant et des gardes (« require ») pour
 * protéger les routes authentifiées et les routes admin.
 */
final class Auth
{
    private const KEY = 'user_id';
    /** @var array<string,mixed>|null Cache de l'utilisateur courant */
    private static ?array $current = null;
    private static bool $resolved = false;

    /**
     * Connecte un utilisateur (après vérification du mot de passe).
     */
    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set(self::KEY, $userId);
        self::$current  = null;
        self::$resolved = false;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$current  = null;
        self::$resolved = false;
    }

    /**
     * Utilisateur courant (ou null si non connecté / désactivé).
     *
     * @return array<string,mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$current;
        }
        self::$resolved = true;

        $id = Session::get(self::KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return self::$current = null;
        }
        $user = (new UserModel())->findById((int) $id);
        // Un compte désactivé perd immédiatement l'accès.
        if ($user === null || !$user['est_actif']) {
            self::logout();
            return self::$current = null;
        }
        return self::$current = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] === 'admin';
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u !== null ? (int) $u['id'] : null;
    }

    /**
     * Garde : exige un utilisateur connecté (sinon 401).
     *
     * @return array<string,mixed>
     */
    public static function requireUser(): array
    {
        $u = self::user();
        if ($u === null) {
            Response::error('Authentification requise.', 401);
        }
        return $u;
    }

    /**
     * Garde : exige un administrateur (sinon 401/403).
     *
     * @return array<string,mixed>
     */
    public static function requireAdmin(): array
    {
        $u = self::requireUser();
        if ($u['role'] !== 'admin') {
            Response::error('Accès réservé à l\'administrateur.', 403);
        }
        return $u;
    }
}
