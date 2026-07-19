<?php
declare(strict_types=1);

/**
 * Front controller — point d'entrée unique de l'application.
 * Toutes les requêtes non-fichier sont réécrites ici (voir .htaccess).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

/*
 * Serveur PHP intégré (développement) : si l'URL correspond à un
 * fichier réel du dossier public, on laisse le serveur le servir
 * directement (Apache s'en charge via .htaccess en production).
 */
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    // embed.html doit passer par PHP (émission de l'en-tête CSP frame-ancestors),
    // on ne le sert donc pas comme simple fichier statique.
    if (is_file($file) && $path !== '/embed.html') {
        return false; // fichier réel → servi tel quel par le serveur intégré
    }
    // Index de répertoire (équivalent DirectoryIndex d'Apache)
    if (is_dir($file) && is_file(rtrim($file, '/') . '/index.html')) {
        header('Content-Type: text/html; charset=utf-8');
        readfile(rtrim($file, '/') . '/index.html');
        exit;
    }
}

App\Core\App::run();
