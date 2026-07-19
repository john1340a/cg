<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimaliste à segments dynamiques ({id}).
 *
 * Les handlers sont des callables (souvent [Controller::class, 'methode'])
 * recevant la Request et un tableau de paramètres d'URL.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,params:string[],handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        // Transforme « /api/events/{id} » en regex + liste de paramètres
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_]+)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'regex'   => $regex,
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    public function get(string $p, callable $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST', $p, $h); }
    public function put(string $p, callable $h): void    { $this->add('PUT', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    /**
     * Résout et exécute la route correspondant à la requête.
     * Renvoie false si aucune route ne correspond au chemin.
     */
    public function dispatch(Request $request): bool
    {
        $path         = $request->path();
        $method       = $request->method();
        $pathMatched  = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue; // même chemin, mauvaise méthode → 405 plus bas
            }

            // Associer les valeurs capturées aux noms de paramètres
            $params = [];
            foreach ($route['params'] as $i => $name) {
                $params[$name] = $matches[$i + 1] ?? null;
            }

            ($route['handler'])($request, $params);
            return true;
        }

        if ($pathMatched) {
            Response::error('Méthode non autorisée.', 405);
        }
        return false;
    }
}
