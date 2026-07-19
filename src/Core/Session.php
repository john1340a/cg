<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestion de la session PHP avec cookie durci
 * (HttpOnly, SameSite, Secure en production).
 */
final class Session
{
    /**
     * Démarre la session avec des paramètres de cookie sécurisés.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = Env::get('APP_ENV') === 'production';

        session_set_cookie_params([
            'lifetime' => 0,          // cookie de session
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,    // HTTPS uniquement en prod
            'httponly' => true,       // inaccessible au JS
            'samesite' => 'Lax',      // protège des CSRF cross-site basiques
        ]);
        session_name('bm_session');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Régénère l'identifiant de session (à faire après login).
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Détruit intégralement la session (déconnexion).
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
