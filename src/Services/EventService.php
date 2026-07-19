<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\EventModel;
use App\Models\PaymentModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use RuntimeException;

/**
 * Logique métier des annonces : création/édition, soumission
 * (règle de gratuité 1re annonce abonné vs paiement 10 €),
 * validation/rejet et transformation en GeoJSON pour la carte.
 */
final class EventService
{
    private EventModel $events;
    private PaymentModel $payments;
    private UserModel $users;
    private SettingModel $settings;
    private MailService $mail;

    public function __construct()
    {
        $this->events   = new EventModel();
        $this->payments = new PaymentModel();
        $this->users    = new UserModel();
        $this->settings = new SettingModel();
        $this->mail     = new MailService();
    }

    /**
     * Soumet une annonce : applique la règle métier de statut.
     *
     * - Abonné + 1re annonce → en_attente_validation, gratuite, paiement exonéré.
     * - Sinon → en_attente_paiement, paiement attendu + email d'instructions.
     *
     * @param array<string,mixed> $event  L'annonce (doit appartenir à $user).
     * @param array<string,mixed> $user
     * @return array{statut:string,est_gratuite:bool}
     */
    public function soumettre(array $event, array $user): array
    {
        $eventId = (int) $event['id'];
        $userId  = (int) $user['id'];

        // « 1re annonce » = aucune annonce déjà payée/gratuite auparavant.
        // On considère la 1re soumission gratuite si l'utilisateur est
        // abonné et n'a encore aucune annonce non-brouillon.
        $dejaEngagees = $this->countEngagees($userId, $eventId);
        $premiereGratuite = (bool) $user['est_abonne'] && $dejaEngagees === 0;

        if ($premiereGratuite) {
            $this->events->setStatut($eventId, 'en_attente_validation');
            $this->markGratuite($eventId);
            $this->payments->create($eventId, 0.0, 'exonere', '1re annonce abonné (gratuite)');
            return ['statut' => 'en_attente_validation', 'est_gratuite' => true];
        }

        // Sinon : paiement requis
        $montant = (float) ($this->settings->get('montant_annonce', '10') ?? '10');
        $this->events->setStatut($eventId, 'en_attente_paiement');
        $this->payments->create($eventId, $montant, 'attendu');

        // Email d'instructions de paiement
        $instructions = $this->settings->get('instructions_paiement', '');
        $this->mail->send(
            (string) $user['email'],
            'Instructions de paiement pour votre annonce',
            'instructions_paiement',
            [
                'prenom'       => $user['prenom'],
                'intitule'     => $event['intitule'],
                'montant'      => $montant,
                'instructions' => $instructions,
            ]
        );

        return ['statut' => 'en_attente_paiement', 'est_gratuite' => false];
    }

    /**
     * Compte les annonces « engagées » (hors brouillon, hors annonce courante).
     */
    private function countEngagees(int $userId, int $exceptEventId): int
    {
        $all = $this->events->findByOwner($userId);
        $n = 0;
        foreach ($all as $e) {
            if ((int) $e['id'] === $exceptEventId) {
                continue;
            }
            if ($e['statut'] !== 'brouillon') {
                $n++;
            }
        }
        return $n;
    }

    private function markGratuite(int $eventId): void
    {
        // Petit passage direct pour positionner est_gratuite.
        $pdo = \App\Core\Database::pdo();
        $pdo->prepare('UPDATE events SET est_gratuite = TRUE WHERE id = :id')
            ->execute([':id' => $eventId]);
    }

    /**
     * Admin : marque le paiement reçu → passe en attente de validation.
     */
    public function marquerPaiementRecu(int $eventId, ?string $note = null): void
    {
        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new RuntimeException('Annonce introuvable.');
        }
        $this->payments->markReceived($eventId, $note);
        $this->events->setStatut($eventId, 'en_attente_validation');
    }

    /**
     * Admin : valide et publie l'annonce (+ email).
     */
    public function valider(int $eventId): void
    {
        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new RuntimeException('Annonce introuvable.');
        }
        $this->events->setStatut($eventId, 'publie');

        $owner = $this->users->findById((int) $event['owner_id']);
        if ($owner) {
            $this->mail->send(
                (string) $owner['email'],
                'Votre annonce est publiée',
                'annonce_validee',
                [
                    'prenom'   => $owner['prenom'],
                    'intitule' => $event['intitule'],
                    'app_url'  => Env::get('APP_URL', ''),
                ]
            );
        }
    }

    /**
     * Admin : rejette l'annonce avec un motif (+ email).
     */
    public function rejeter(int $eventId, string $motif): void
    {
        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new RuntimeException('Annonce introuvable.');
        }
        $this->events->setStatut($eventId, 'rejete', $motif);

        $owner = $this->users->findById((int) $event['owner_id']);
        if ($owner) {
            $this->mail->send(
                (string) $owner['email'],
                'Votre annonce n\'a pas été retenue',
                'annonce_rejetee',
                [
                    'prenom'   => $owner['prenom'],
                    'intitule' => $event['intitule'],
                    'motif'    => $motif,
                ]
            );
        }
    }

    /**
     * Construit un FeatureCollection GeoJSON à partir de lignes d'events.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    public function toGeoJson(array $rows): array
    {
        $features = [];
        foreach ($rows as $e) {
            if ($e['lon'] === null || $e['lat'] === null) {
                continue;
            }
            $features[] = [
                'type'     => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [(float) $e['lon'], (float) $e['lat']],
                ],
                'properties' => $this->publicProperties($e),
            ];
        }
        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * Représentation publique d'une annonce (popup carte).
     *
     * @param array<string,mixed> $e
     * @return array<string,mixed>
     */
    public function publicProperties(array $e): array
    {
        return [
            'id'          => (int) $e['id'],
            'intitule'    => $e['intitule'],
            'edition_num' => $e['edition_num'],
            'date_debut'  => $e['date_debut'],
            'date_fin'    => $e['date_fin'],
            'mois'        => (int) date('n', strtotime((string) $e['date_debut'])),
            'type_echanges' => (bool) $e['type_echanges'],
            'type_vente'    => (bool) $e['type_vente'],
            'categories'  => array_values(array_filter([
                $e['cat_mineraux']   ? 'Minéraux'                 : null,
                $e['cat_fossiles']   ? 'Fossiles'                 : null,
                $e['cat_gemmes']     ? 'Gemmes/bijoux'            : null,
                $e['cat_esoterisme'] ? 'Ésotérisme/lithothérapie' : null,
            ])),
            'adresse'       => $e['adresse'],
            'tarif'         => $e['tarif'],
            'contact_email' => $e['contact_email'],
            'site_web'      => $e['site_web'],
            // URL relative : fonctionne quel que soit le domaine/port
            // (utile en iframe et en dev sur un port différent).
            'affiche'       => $e['affiche_path']
                ? '/api/affiche/' . rawurlencode((string) $e['affiche_path'])
                : null,
        ];
    }
}
