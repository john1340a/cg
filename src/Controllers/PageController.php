<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Sert les pages HTML statiques dont le chemin correspond à un
 * répertoire (index de répertoire), indépendamment de la
 * configuration DirectoryIndex de l'hébergeur.
 *
 * Nécessaire car, sur certains hébergements (ex. alwaysdata), Apache
 * exécute index.php à la racine « / » avant index.html : sans cette
 * route, le routeur ne reconnaîtrait pas « / » et renverrait 404.
 */
final class PageController
{
    /** Racine web (dossier public/). */
    private string $publicDir;

    public function __construct()
    {
        $this->publicDir = dirname(__DIR__, 2) . '/public';
    }

    /**
     * Sert le fichier index.html d'un répertoire donné.
     *
     * @param string $sousDossier  '' pour la racine, 'compte', 'admin'…
     */
    public function serveIndex(string $sousDossier = ''): void
    {
        $chemin = $this->publicDir
            . ($sousDossier !== '' ? '/' . trim($sousDossier, '/') : '')
            . '/index.html';

        if (!is_file($chemin)) {
            Response::error('Page introuvable.', 404);
        }

        header('Content-Type: text/html; charset=utf-8');
        readfile($chemin);
        exit;
    }

    /** GET / */
    public function accueil(Request $request): void
    {
        $this->serveIndex('');
    }

    /** GET /compte  et  /compte/ */
    public function compte(Request $request): void
    {
        $this->serveIndex('compte');
    }

    /** GET /admin  et  /admin/ */
    public function admin(Request $request): void
    {
        $this->serveIndex('admin');
    }
}
