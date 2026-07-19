<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Amorçage de l'application : configuration, session, routage,
 * gestion centralisée des erreurs.
 */
final class App
{
    /**
     * Point d'entrée unique (appelé depuis public/index.php).
     */
    public static function run(): void
    {
        Env::load();

        // Affichage des erreurs seulement hors production
        $isProd = Env::get('APP_ENV') === 'production';
        error_reporting(E_ALL);
        ini_set('display_errors', $isProd ? '0' : '1');
        ini_set('log_errors', '1');
        ini_set('error_log', dirname(__DIR__, 2) . '/storage/logs/php_errors.log');

        Session::start();

        $request = new Request();

        // En-têtes CORS/frame gérés au cas par cas (embed). Ici, API JSON.
        try {
            $router = new Router();
            // Charge les définitions de routes
            (require dirname(__DIR__) . '/routes.php')($router);

            $matched = $router->dispatch($request);
            if (!$matched) {
                // Chemin inconnu : si c'est une route API → JSON 404,
                // sinon on laisse Apache servir les fichiers statiques
                // (ce cas n'arrive normalement pas car .htaccess ne
                //  réécrit que les non-fichiers).
                if (str_starts_with($request->path(), '/api/')) {
                    Response::error('Ressource introuvable.', 404);
                }
                http_response_code(404);
                header('Content-Type: text/plain; charset=utf-8');
                echo "404 — Page introuvable.";
            }
        } catch (Throwable $e) {
            error_log('[App] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            $payload = ['error' => 'Erreur interne du serveur.'];
            if (!$isProd) {
                $payload['debug'] = $e->getMessage();
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
    }
}
