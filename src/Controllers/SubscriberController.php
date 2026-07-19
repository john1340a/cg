<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\SubscriberModel;
use App\Models\UserModel;

/**
 * Gestion de la liste des abonnés (whitelist) par l'admin :
 * import CSV, ajout / suppression manuels. À chaque ajout, le flag
 * est_abonne des comptes existants correspondants est mis à jour.
 */
final class SubscriberController
{
    private SubscriberModel $subs;
    private UserModel $users;

    public function __construct()
    {
        $this->subs  = new SubscriberModel();
        $this->users = new UserModel();
    }

    /**
     * GET /api/admin/subscribers
     */
    public function list(Request $request): void
    {
        Auth::requireAdmin();
        Response::ok(['subscribers' => $this->subs->all()]);
    }

    /**
     * POST /api/admin/subscribers  { email }
     */
    public function add(Request $request): void
    {
        $admin = Auth::requireAdmin();
        Csrf::requireValid($request);

        $email = strtolower(trim((string) ($request->json()['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Adresse email invalide.', 422);
        }
        $this->subs->add($email, (int) $admin['id']);
        $this->users->setAbonne($email, true); // maj compte existant éventuel

        Response::ok(['ok' => true]);
    }

    /**
     * DELETE /api/admin/subscribers/{id}
     */
    public function remove(Request $request, array $params): void
    {
        Auth::requireAdmin();
        Csrf::requireValid($request);

        $id = (int) $params['id'];
        $sub = $this->subs->findById($id);
        if ($sub !== null) {
            $this->subs->remove($id);
            // Retirer le statut abonné du compte correspondant (s'il existe)
            $this->users->setAbonne((string) $sub['email'], false);
        }
        Response::ok(['ok' => true]);
    }

    /**
     * POST /api/admin/subscribers/import — import CSV (multipart « fichier »
     * ou corps texte « csv »). Une colonne email, séparateurs , ; ou saut de ligne.
     */
    public function import(Request $request): void
    {
        $admin = Auth::requireAdmin();
        Csrf::requireValid($request);

        // Source : fichier uploadé ou champ texte
        $contenu = '';
        $file = $request->file('fichier');
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $contenu = (string) file_get_contents($file['tmp_name']);
        } else {
            $contenu = (string) ($request->input('csv', ''));
        }

        if (trim($contenu) === '') {
            Response::error('Aucune donnée CSV fournie.', 422);
        }

        // Extraire tous les emails valides du contenu
        $emails = $this->extraireEmails($contenu);
        $ajoutes = 0;
        $ignores = 0;
        foreach ($emails as $email) {
            if ($this->subs->add($email, (int) $admin['id'])) {
                $ajoutes++;
            } else {
                $ignores++;
            }
            // Met à jour le flag des comptes existants
            $this->users->setAbonne($email, true);
        }

        Response::ok([
            'ok'       => true,
            'total'    => count($emails),
            'ajoutes'  => $ajoutes,
            'doublons' => $ignores,
            'message'  => "$ajoutes ajouté(s), $ignores doublon(s) ignoré(s).",
        ]);
    }

    /**
     * Extrait et normalise les emails valides d'un contenu CSV/texte.
     *
     * @return array<int,string>  emails uniques, en minuscules
     */
    private function extraireEmails(string $contenu): array
    {
        // Découpage sur virgules, points-virgules, retours ligne, tabulations, espaces
        $morceaux = preg_split('/[\s,;]+/', $contenu) ?: [];
        $emails = [];
        foreach ($morceaux as $m) {
            $m = strtolower(trim($m, " \t\n\r\"'"));
            if ($m !== '' && filter_var($m, FILTER_VALIDATE_EMAIL)) {
                $emails[$m] = true; // clé = déduplication
            }
        }
        return array_keys($emails);
    }
}
