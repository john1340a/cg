<?php
declare(strict_types=1);

/**
 * Table des routes de l'application.
 *
 * Retourne une closure qui reçoit le Router et y enregistre toutes
 * les routes. Les routes sont ajoutées module par module.
 */

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\EventController;
use App\Controllers\UploadController;
use App\Controllers\AdminController;
use App\Controllers\SubscriberController;
use App\Controllers\EmbedController;
use App\Controllers\PageController;

return static function (Router $r): void {

    // ------------------------------------------------------------
    //  Pages HTML servies par le routeur (indépendant du
    //  DirectoryIndex de l'hébergeur : « / », « /compte », « /admin »)
    // ------------------------------------------------------------
    $r->get('/',         [new PageController(), 'accueil']);
    $r->get('/compte',   [new PageController(), 'compte']);
    $r->get('/admin',    [new PageController(), 'admin']);

    // ------------------------------------------------------------
    //  Utilitaire : jeton CSRF pour le frontend
    // ------------------------------------------------------------
    $r->get('/api/csrf', static function (): void {
        Response::ok(['token' => Csrf::token()]);
    });

    // ------------------------------------------------------------
    //  Module 2 — Authentification
    // ------------------------------------------------------------
    $r->post('/api/auth/register', [new AuthController(), 'register']);
    $r->post('/api/auth/login',    [new AuthController(), 'login']);
    $r->post('/api/auth/logout',   [new AuthController(), 'logout']);
    $r->get('/api/auth/me',        [new AuthController(), 'me']);
    $r->post('/api/auth/forgot',   [new AuthController(), 'forgot']);
    $r->post('/api/auth/reset',    [new AuthController(), 'reset']);

    // ------------------------------------------------------------
    //  Module 3 — Annonces (organisateur), géocodage, upload
    // ------------------------------------------------------------
    // Public (carte)
    $r->get('/api/events',        [new EventController(), 'publicList']);
    $r->get('/api/events/{id}',   [new EventController(), 'publicShow']);
    $r->get('/api/affiche/{fichier}', [new UploadController(), 'serve']);

    // Organisateur (authentifié)
    $r->get('/api/mes-annonces',                  [new EventController(), 'mine']);
    $r->post('/api/mes-annonces',                 [new EventController(), 'create']);
    $r->put('/api/mes-annonces/{id}',             [new EventController(), 'update']);
    $r->delete('/api/mes-annonces/{id}',          [new EventController(), 'delete']);
    $r->post('/api/mes-annonces/{id}/soumettre',  [new EventController(), 'submit']);

    // Géocodage BAN
    $r->post('/api/geocode', [new EventController(), 'geocode']);

    // ------------------------------------------------------------
    //  Module 5 — Back-office admin
    // ------------------------------------------------------------
    // Modération des annonces
    $r->get('/api/admin/events',                    [new AdminController(), 'listEvents']);
    $r->get('/api/admin/events/export',             [new AdminController(), 'exportEvents']);
    $r->post('/api/admin/events',                   [new AdminController(), 'createFor']);
    $r->post('/api/admin/events/{id}/paiement-recu',[new AdminController(), 'paiementRecu']);
    $r->post('/api/admin/events/{id}/valider',      [new AdminController(), 'valider']);
    $r->post('/api/admin/events/{id}/rejeter',      [new AdminController(), 'rejeter']);

    // Utilisateurs
    $r->get('/api/admin/users',                     [new AdminController(), 'listUsers']);
    $r->post('/api/admin/users/{id}/desactiver',    [new AdminController(), 'toggleUser']);

    // Paramètres
    $r->get('/api/admin/settings',                  [new AdminController(), 'getSettings']);
    $r->put('/api/admin/settings',                  [new AdminController(), 'updateSettings']);

    // Abonnés (whitelist)
    $r->get('/api/admin/subscribers',               [new SubscriberController(), 'list']);
    $r->post('/api/admin/subscribers',              [new SubscriberController(), 'add']);
    $r->post('/api/admin/subscribers/import',       [new SubscriberController(), 'import']);
    $r->delete('/api/admin/subscribers/{id}',       [new SubscriberController(), 'remove']);

    // ------------------------------------------------------------
    //  Module 7 — Embed iframe (carte avec en-tête CSP frame-ancestors)
    // ------------------------------------------------------------
    $r->get('/embed',      [new EmbedController(), 'page']);
    $r->get('/embed.html', [new EmbedController(), 'page']);
};
