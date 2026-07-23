<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\EventModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\EventService;
use RuntimeException;

/**
 * Back-office administrateur : modération des annonces, paiements,
 * gestion des utilisateurs, paramètres, saisie déléguée.
 * Toutes les méthodes exigent le rôle admin + CSRF sur les mutations.
 */
final class AdminController
{
    private EventModel $events;
    private EventService $service;
    private UserModel $users;
    private SettingModel $settings;

    public function __construct()
    {
        $this->events   = new EventModel();
        $this->service  = new EventService();
        $this->users    = new UserModel();
        $this->settings = new SettingModel();
    }

    // ============================================================
    //  Modération des annonces
    // ============================================================

    /**
     * GET /api/admin/events?statut=...
     */
    public function listEvents(Request $request): void
    {
        Auth::requireAdmin();
        $statut = $request->query('statut');
        $valides = ['brouillon', 'en_attente_paiement', 'en_attente_validation', 'publie', 'rejete'];
        if ($statut !== null && !in_array($statut, $valides, true)) {
            $statut = null;
        }
        Response::ok(['events' => $this->events->findForAdmin($statut)]);
    }

    /**
     * GET /api/admin/events/export
     * Exporte toutes les annonces (tous statuts) en fichier texte téléchargeable.
     */
    public function exportEvents(Request $request): void
    {
        Auth::requireAdmin();
        $events = $this->events->findForAdmin(null);
        $contenu = (new \App\Services\EventTextFormatter())->build($events);
        $nom = 'bourses-mineraux_' . date('Y-m-d') . '.txt';
        Response::download($contenu, $nom);
    }

    /**
     * POST /api/admin/events/{id}/paiement-recu
     */
    public function paiementRecu(Request $request, array $params): void
    {
        Auth::requireAdmin();
        Csrf::requireValid($request);
        $note = self::str($request->json()['note'] ?? null);
        try {
            $this->service->marquerPaiementRecu((int) $params['id'], $note);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
        Response::ok(['ok' => true, 'message' => 'Paiement marqué reçu. Annonce en attente de validation.']);
    }

    /**
     * POST /api/admin/events/{id}/valider
     */
    public function valider(Request $request, array $params): void
    {
        Auth::requireAdmin();
        Csrf::requireValid($request);
        try {
            $this->service->valider((int) $params['id']);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
        Response::ok(['ok' => true, 'message' => 'Annonce publiée. L\'organisateur a été prévenu.']);
    }

    /**
     * POST /api/admin/events/{id}/rejeter  { motif }
     */
    public function rejeter(Request $request, array $params): void
    {
        Auth::requireAdmin();
        Csrf::requireValid($request);
        $motif = trim(self::str($request->json()['motif'] ?? '') ?? '');
        if ($motif === '') {
            Response::error('Un motif de rejet est requis.', 422);
        }
        try {
            $this->service->rejeter((int) $params['id'], $motif);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
        Response::ok(['ok' => true, 'message' => 'Annonce rejetée. L\'organisateur a été prévenu.']);
    }

    /**
     * POST /api/admin/events — saisie déléguée (créer au nom d'un user).
     */
    public function createFor(Request $request): void
    {
        Auth::requireAdmin();
        Csrf::requireValid($request);

        $data = $request->json();
        $ownerId = (int) ($data['owner_id'] ?? 0);
        $owner = $this->users->findById($ownerId);
        if ($owner === null) {
            Response::error('Utilisateur cible introuvable.', 404);
        }

        // Réutilise la validation métier via EventModel (données déjà géocodées attendues)
        $champs = [
            'intitule'      => trim((string) ($data['intitule'] ?? '')),
            'edition_num'   => self::str($data['edition_num'] ?? null),
            'date_debut'    => (string) ($data['date_debut'] ?? ''),
            'date_fin'      => (string) ($data['date_fin'] ?? ''),
            'type_echanges' => !empty($data['type_echanges']),
            'type_vente'    => !empty($data['type_vente']),
            'cat_mineraux'  => !empty($data['cat_mineraux']),
            'cat_fossiles'  => !empty($data['cat_fossiles']),
            'cat_gemmes'    => !empty($data['cat_gemmes']),
            'cat_esoterisme'=> !empty($data['cat_esoterisme']),
            'adresse'       => trim((string) ($data['adresse'] ?? '')),
            'lon'           => (float) ($data['lon'] ?? 0),
            'lat'           => (float) ($data['lat'] ?? 0),
            'tarif'         => self::str($data['tarif'] ?? null),
            'contact_email' => trim((string) ($data['contact_email'] ?? '')),
            'site_web'      => self::str($data['site_web'] ?? null),
            'statut'        => 'en_attente_validation', // saisie admin → directement en validation
        ];

        if ($champs['intitule'] === '' || $champs['adresse'] === ''
            || $champs['contact_email'] === '' || !$champs['lon'] || !$champs['lat']) {
            Response::error('Champs obligatoires manquants (intitulé, adresse, contact, coordonnées).', 422);
        }

        $id = $this->events->create($ownerId, $champs);
        Response::ok(['ok' => true, 'id' => $id], 201);
    }

    // ============================================================
    //  Utilisateurs
    // ============================================================

    /**
     * GET /api/admin/users
     */
    public function listUsers(Request $request): void
    {
        Auth::requireAdmin();
        Response::ok(['users' => $this->users->all()]);
    }

    /**
     * POST /api/admin/users/{id}/desactiver  { actif: bool }
     */
    public function toggleUser(Request $request, array $params): void
    {
        $admin = Auth::requireAdmin();
        Csrf::requireValid($request);
        $id = (int) $params['id'];
        if ($id === (int) $admin['id']) {
            Response::error('Vous ne pouvez pas désactiver votre propre compte.', 400);
        }
        $actif = (bool) ($request->json()['actif'] ?? false);
        $this->users->setActif($id, $actif);
        Response::ok(['ok' => true]);
    }

    // ============================================================
    //  Paramètres
    // ============================================================

    /**
     * GET /api/admin/settings
     */
    public function getSettings(Request $request): void
    {
        Auth::requireAdmin();
        Response::ok(['settings' => $this->settings->all()]);
    }

    /**
     * PUT /api/admin/settings — met à jour les clés fournies.
     */
    public function updateSettings(Request $request): void
    {
        Auth::requireAdmin();
        Csrf::requireValid($request);

        $data = $request->json();
        // Clés éditables autorisées (liste blanche)
        $autorisees = ['instructions_paiement', 'email_expediteur', 'nom_expediteur',
                       'montant_annonce', 'iframe_domain', 'lien_paiement'];
        foreach ($autorisees as $cle) {
            if (array_key_exists($cle, $data)) {
                $this->settings->set($cle, (string) $data[$cle]);
            }
        }
        Response::ok(['ok' => true, 'settings' => $this->settings->all()]);
    }

    private static function str(mixed $v): ?string
    {
        if ($v === null) return null;
        $v = is_string($v) ? trim($v) : (string) $v;
        return $v === '' ? null : $v;
    }
}
