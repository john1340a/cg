<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Chargeur de configuration minimaliste (fichier .env).
 *
 * Lit une seule fois le fichier .env à la racine du projet et expose
 * ses clés via Env::get(). Volontairement sans dépendance externe.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $data = [];
    private static bool $loaded = false;

    /**
     * Charge le fichier .env (idempotent).
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        $path ??= dirname(__DIR__, 2) . '/.env';

        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // Ignorer commentaires et lignes sans « = »
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value);
                // Retirer d'éventuels guillemets englobants
                if (strlen($value) >= 2
                    && (($value[0] === '"' && str_ends_with($value, '"'))
                     || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    $value = substr($value, 1, -1);
                }
                self::$data[$key] = $value;
            }
        }
        self::$loaded = true;
    }

    /**
     * Récupère une valeur de configuration (avec valeur par défaut).
     *
     * Ordre de priorité :
     *   1. variables d'environnement réelles (getenv / $_SERVER / $_ENV),
     *      ex. définies dans l'interface alwaysdata ;
     *   2. fichier .env (repli pratique en développement local).
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        // 1. Variable d'environnement système (prioritaire).
        $env = getenv($key);
        if ($env === false && array_key_exists($key, $_SERVER)) {
            $env = $_SERVER[$key];
        }
        if ($env === false && array_key_exists($key, $_ENV)) {
            $env = $_ENV[$key];
        }
        if ($env !== false && $env !== null) {
            return (string) $env;
        }

        // 2. Repli : fichier .env
        self::load();
        return self::$data[$key] ?? $default;
    }

    /**
     * Récupère une valeur booléenne (« true », « 1 », « yes », « on »).
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Récupère une valeur entière.
     */
    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v !== null && $v !== '' ? (int) $v : $default;
    }
}
