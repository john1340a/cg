<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\EventModel;
use App\Services\EventService;
use App\Services\GeocodingService;
use App\Services\ImageService;
use RuntimeException;

/**
 * Gestion des annonces côté organisateur + endpoints publics carte.
 */
final class EventController
{
    private EventModel $events;
    private EventService $service;

    public function __construct()
    {
        $this->events  = new EventModel();
        $this->service = new EventService();
    }

    // ============================================================
    //  PUBLIC — carte
    // ============================================================

    /**
     * GET /api/events — GeoJSON des annonces publiées (avec filtres).
     */
    public function publicList(Request $request): void
    {
        $filters = [
            'debut'          => $request->query('debut'),
            'fin'            => $request->query('fin'),
            'mois'           => $request->query('mois'),
            'categorie'      => $request->query('categorie'),
            'type'           => $request->query('type'),
            'inclure_passes' => $request->query('passes') === '1',
        ];
        $rows = $this->events->findPublished($filters);
        Response::json($this->service->toGeoJson($rows));
    }

    /**
     * GET /api/events/{id} — détail public (si publié).
     */
    public function publicShow(Request $request, array $params): void
    {
        $event = $this->events->findById((int) $params['id']);
        if ($event === null || $event['statut'] !== 'publie') {
            Response::error('Annonce introuvable.', 404);
        }
        Response::ok($this->service->publicProperties($event));
    }

    // ============================================================
    //  ORGANISATEUR — CRUD de ses annonces
    // ============================================================

    /**
     * GET /api/mes-annonces
     */
    public function mine(Request $request): void
    {
        $user = Auth::requireUser();
        $rows = $this->events->findByOwner((int) $user['id']);
        $settings = new \App\Models\SettingModel();
        Response::ok([
            'events'  => $rows,
            // Infos de paiement en ligne pour les annonces en_attente_paiement.
            'paiement' => [
                'lien'    => $settings->get('lien_paiement', ''),
                'montant' => $settings->get('montant_annonce', '10'),
            ],
        ]);
    }

    /**
     * POST /api/mes-annonces — création (multipart si affiche).
     */
    public function create(Request $request): void
    {
        $user = Auth::requireUser();
        Csrf::requireValid($request);

        $data = $this->collectInput($request);
        $this->validate($data);

        // Upload affiche (facultatif)
        $affiche = $this->handleUpload($request);
        if ($affiche !== null) {
            $data['affiche_path'] = $affiche;
        }

        $id = $this->events->create((int) $user['id'], $data);
        Response::ok(['ok' => true, 'id' => $id], 201);
    }

    /**
     * PUT /api/mes-annonces/{id}
     */
    public function update(Request $request, array $params): void
    {
        $user  = Auth::requireUser();
        Csrf::requireValid($request);

        $id    = (int) $params['id'];
        $event = $this->ownedOr404($id, (int) $user['id']);

        // Modifiable seulement tant que non publiée (ou brouillon/paiement/attente)
        if (in_array($event['statut'], ['publie'], true)) {
            Response::error('Une annonce publiée ne peut plus être modifiée.', 409);
        }

        $data = $this->collectInput($request);
        $this->validate($data);

        // Gestion de l'affiche : nouvelle image ou conservation
        $affiche = $this->handleUpload($request);
        $data['affiche_path'] = $affiche ?? $event['affiche_path'];
        if ($affiche !== null && $event['affiche_path']) {
            (new ImageService())->delete($event['affiche_path']); // remplace l'ancienne
        }

        $this->events->update($id, (int) $user['id'], $data);
        Response::ok(['ok' => true]);
    }

    /**
     * DELETE /api/mes-annonces/{id}
     */
    public function delete(Request $request, array $params): void
    {
        $user  = Auth::requireUser();
        Csrf::requireValid($request);

        $id    = (int) $params['id'];
        $event = $this->ownedOr404($id, (int) $user['id']);

        if ($event['affiche_path']) {
            (new ImageService())->delete($event['affiche_path']);
        }
        $this->events->delete($id);
        Response::ok(['ok' => true]);
    }

    /**
     * POST /api/mes-annonces/{id}/soumettre
     */
    public function submit(Request $request, array $params): void
    {
        $user  = Auth::requireUser();
        Csrf::requireValid($request);

        $id    = (int) $params['id'];
        $event = $this->ownedOr404($id, (int) $user['id']);

        if (!in_array($event['statut'], ['brouillon', 'rejete'], true)) {
            Response::error('Cette annonce a déjà été soumise.', 409);
        }

        try {
            $result = $this->service->soumettre($event, $user);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 400);
        }

        Response::ok([
            'ok'      => true,
            'statut'  => $result['statut'],
            'gratuite'=> $result['est_gratuite'],
            'message' => $result['est_gratuite']
                ? 'Annonce soumise gratuitement (abonné). Elle sera publiée après validation.'
                : 'Annonce enregistrée. Un email d\'instructions de paiement vous a été envoyé.',
        ]);
    }

    // ============================================================
    //  GÉOCODAGE
    // ============================================================

    /**
     * POST /api/geocode — proxy BAN (adresse → point).
     */
    public function geocode(Request $request): void
    {
        Auth::requireUser();
        Csrf::requireValid($request);

        $adresse = trim((string) ($request->json()['adresse'] ?? ''));
        if ($adresse === '') {
            Response::error('Adresse manquante.', 422);
        }

        $result = (new GeocodingService())->geocode($adresse);
        if ($result === null) {
            Response::error('Adresse introuvable. Vérifiez la saisie ou placez le point manuellement.', 404);
        }
        Response::ok($result);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    /**
     * Rassemble les champs de l'annonce depuis JSON ou multipart.
     *
     * @return array<string,mixed>
     */
    private function collectInput(Request $request): array
    {
        $bool = static fn($v) => in_array($v, [true, 1, '1', 'true', 'on'], true);

        return [
            'intitule'      => trim((string) $request->input('intitule', '')),
            'edition_num'   => self::nullIfEmpty($request->input('edition_num')),
            'date_debut'    => (string) $request->input('date_debut', ''),
            'date_fin'      => (string) $request->input('date_fin', ''),
            'type_echanges' => $bool($request->input('type_echanges')),
            'type_vente'    => $bool($request->input('type_vente')),
            'cat_mineraux'  => $bool($request->input('cat_mineraux')),
            'cat_micromineraux' => $bool($request->input('cat_micromineraux')),
            'cat_fossiles'  => $bool($request->input('cat_fossiles')),
            'cat_gemmes'    => $bool($request->input('cat_gemmes')),
            'cat_esoterisme'=> $bool($request->input('cat_esoterisme')),
            'adresse'       => trim((string) $request->input('adresse', '')),
            'lon'           => (float) $request->input('lon', 0),
            'lat'           => (float) $request->input('lat', 0),
            'tarif'         => self::nullIfEmpty($request->input('tarif')),
            'contact_email' => trim((string) $request->input('contact_email', '')),
            'site_web'      => self::nullIfEmpty($request->input('site_web')),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function validate(array $data): void
    {
        $v = new Validator($data);
        $v->required('intitule')->maxLength('intitule', 200)
          ->required('date_debut')->date('date_debut')
          ->required('date_fin')->date('date_fin')
          ->required('adresse')->maxLength('adresse', 300)
          ->required('contact_email')->email('contact_email');

        if (!empty($data['site_web'])) {
            $v->url('site_web');
        }
        // Au moins un type et une catégorie
        if (!$data['type_echanges'] && !$data['type_vente']) {
            $v->required('type_echanges', 'Sélectionnez au moins un type (échanges ou vente).');
        }
        if (!$data['cat_mineraux'] && !$data['cat_micromineraux'] && !$data['cat_fossiles']
            && !$data['cat_gemmes'] && !$data['cat_esoterisme']) {
            $v->required('cat_mineraux', 'Sélectionnez au moins une catégorie.');
        }
        // Coordonnées présentes (géocodage effectué)
        if (empty($data['lon']) || empty($data['lat'])) {
            $v->required('adresse', 'Adresse non géolocalisée. Lancez le géocodage.');
        }
        // Cohérence des dates
        if (!$v->fails() && $data['date_fin'] < $data['date_debut']) {
            Response::error('La date de fin doit être postérieure ou égale à la date de début.', 422);
        }

        if ($v->fails()) {
            Response::error('Formulaire incomplet ou invalide.', 422, ['champs' => $v->errors()]);
        }
    }

    /**
     * Traite un éventuel upload « affiche ». Retourne le nom stocké ou null.
     */
    private function handleUpload(Request $request): ?string
    {
        $file = $request->file('affiche');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        try {
            return (new ImageService())->store($file);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    /**
     * Vérifie que l'annonce existe et appartient à l'utilisateur.
     *
     * @return array<string,mixed>
     */
    private function ownedOr404(int $id, int $userId): array
    {
        $event = $this->events->findById($id);
        if ($event === null || (int) $event['owner_id'] !== $userId) {
            Response::error('Annonce introuvable.', 404);
        }
        return $event;
    }

    private static function nullIfEmpty(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === null || $v === '') ? null : (string) $v;
    }
}
