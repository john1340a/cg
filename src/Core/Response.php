<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Aides à l'émission de réponses HTTP (principalement JSON).
 */
final class Response
{
    /**
     * Émet une réponse JSON et termine le script.
     *
     * @param mixed $data
     */
    public static function json($data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Réponse d'erreur normalisée : { "error": "message" }.
     */
    public static function error(string $message, int $status = 400, array $extra = []): never
    {
        self::json(array_merge(['error' => $message], $extra), $status);
    }

    /**
     * Réponse de succès normalisée.
     *
     * @param mixed $data
     */
    public static function ok($data = null, int $status = 200): never
    {
        self::json($data ?? ['ok' => true], $status);
    }

    /**
     * Redirection HTTP.
     */
    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }
}
