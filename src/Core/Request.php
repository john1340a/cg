<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Représentation de la requête HTTP courante.
 *
 * Fournit un accès pratique à la méthode, au chemin, aux paramètres
 * de requête, au corps JSON et aux fichiers uploadés.
 */
final class Request
{
    private string $method;
    private string $path;
    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,mixed>|null */
    private ?array $jsonCache = null;

    public function __construct()
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Surcharge de méthode (uploads multipart en PUT/DELETE) :
        // un POST portant _method=PUT|DELETE est traité comme tel.
        // PHP ne peuple $_POST/$_FILES que pour les vrais POST, d'où
        // ce contournement standard pour les formulaires multipart.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $method = $override;
            }
        }
        $this->method = $method;
        // Chemin sans query string ni slash final superflu
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        $this->path  = $path === '' ? '/' : $path;
        $this->query = $_GET;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Paramètre de query string.
     */
    public function query(string $key, ?string $default = null): ?string
    {
        $v = $this->query[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    /**
     * Corps de requête décodé depuis le JSON (mis en cache).
     *
     * @return array<string,mixed>
     */
    public function json(): array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        $this->jsonCache = is_array($data) ? $data : [];
        return $this->jsonCache;
    }

    /**
     * Valeur issue soit du corps JSON, soit d'un POST multipart.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        // multipart/form-data (uploads) → $_POST
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        $json = $this->json();
        return $json[$key] ?? $default;
    }

    /**
     * True si la requête transporte un corps JSON.
     */
    public function isJson(): bool
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains(strtolower($ct), 'application/json');
    }

    /**
     * Fichier uploadé (structure $_FILES) ou null.
     *
     * @return array<string,mixed>|null
     */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * En-tête HTTP (insensible à la casse).
     */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    /**
     * Adresse IP du client (best effort, hébergement mutualisé).
     */
    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
