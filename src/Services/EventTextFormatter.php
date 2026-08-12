<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Formatage texte des annonces pour l'export administrateur.
 *
 * Reproduit côté serveur la présentation « façon revue » utilisée à
 * l'écran (cf. public/assets/js/format.js) :
 *
 *   53ème Salon (ventes-échanges)
 *   17-19 juillet 2026, Millau (12)
 *   Salle des Fêtes, Parc de la Victoire, 12100 Millau
 *   contact@exemple.fr — https://exemple.fr
 */
final class EventTextFormatter
{
    private const MOIS = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    private const LIBELLE_STATUT = [
        'brouillon'             => 'Brouillon',
        'en_attente_paiement'   => 'En attente de paiement',
        'en_attente_validation' => 'En attente de validation',
        'publie'                => 'Publiée',
        'rejete'                => 'Rejetée',
    ];

    /**
     * Construit le fichier texte complet à partir de toutes les annonces.
     *
     * @param array<int,array<string,mixed>> $events lignes de findForAdmin()
     */
    public function build(array $events): string
    {
        $date = date('d/m/Y à H:i');
        $lignes = [];
        $lignes[] = 'BOURSES AUX MINÉRAUX — Export des annonces';
        $lignes[] = 'Généré le ' . $date;
        $lignes[] = 'Total : ' . count($events) . ' annonce' . (count($events) > 1 ? 's' : '');
        $lignes[] = str_repeat('=', 60);
        $lignes[] = '';

        if ($events === []) {
            $lignes[] = 'Aucune annonce.';
            return implode("\n", $lignes) . "\n";
        }

        foreach ($events as $e) {
            $lignes[] = $this->bloc($e);
            $lignes[] = str_repeat('-', 60);
            $lignes[] = '';
        }

        return implode("\n", $lignes) . "\n";
    }

    /**
     * Bloc texte d'une annonce.
     *
     * @param array<string,mixed> $e
     */
    private function bloc(array $e): string
    {
        $l = [];
        // Ligne 1 : titre « Nème Intitulé (ventes-échanges) »
        $l[] = $this->titreAvecType($e);
        // Ligne 2 : dates, Ville (dépt)
        $l[] = $this->enteteDatesLieu($e);
        // Ligne 3 : adresse complète
        $l[] = (string) ($e['adresse'] ?? '');

        // Ligne 4 : contact + site
        $contact = [];
        if (!empty($e['contact_email'])) {
            $contact[] = (string) $e['contact_email'];
        }
        if (!empty($e['site_web'])) {
            $contact[] = (string) $e['site_web'];
        }
        if ($contact !== []) {
            $l[] = implode(' — ', $contact);
        }

        // Ligne 5 : tarif éventuel
        if (!empty($e['tarif'])) {
            $l[] = 'Entrée : ' . $e['tarif'];
        }

        // Ligne 6 : catégories
        $cats = $this->categories($e);
        if ($cats !== '') {
            $l[] = 'Catégories : ' . $cats;
        }

        // Ligne 7 : statut + organisateur (utile en interne)
        $statut = self::LIBELLE_STATUT[$e['statut'] ?? ''] ?? (string) ($e['statut'] ?? '');
        $orga = trim(((string) ($e['owner_prenom'] ?? '')) . ' ' . ((string) ($e['owner_nom'] ?? '')));
        $meta = '[' . $statut . ']';
        if ($orga !== '') {
            $meta .= ' Organisateur : ' . $orga;
            if (!empty($e['owner_email'])) {
                $meta .= ' (' . $e['owner_email'] . ')';
            }
        }
        $l[] = $meta;

        return implode("\n", $l);
    }

    /** Titre « 53ème Vente de pierre (ventes-échanges) ». */
    private function titreAvecType(array $e): string
    {
        $titre = $this->titre($e);
        $type = $this->type($e);
        return $type !== '' ? "$titre $type" : $titre;
    }

    /** Titre « 53ème Vente de pierre » (édition en préfixe). */
    private function titre(array $e): string
    {
        $intitule = trim((string) ($e['intitule'] ?? ''));
        $ord = $this->ordinal($e['edition_num'] ?? null);
        return $ord !== '' ? "$ord $intitule" : $intitule;
    }

    /** Ordinal français : 1 -> « 1er », 53 -> « 53ème », texte libre inchangé. */
    private function ordinal(mixed $edition): string
    {
        if ($edition === null) {
            return '';
        }
        $s = trim((string) $edition);
        if ($s === '') {
            return '';
        }
        if (!ctype_digit($s)) {
            return $s;
        }
        $n = (int) $s;
        return $n === 1 ? '1er' : $n . 'ème';
    }

    /** Type « (ventes-échanges) » / « (ventes) » / '' */
    private function type(array $e): string
    {
        $t = [];
        if (!empty($e['type_vente'])) {
            $t[] = 'ventes';
        }
        if (!empty($e['type_echanges'])) {
            $t[] = 'échanges';
        }
        return $t !== [] ? '(' . implode('-', $t) . ')' : '';
    }

    /** « 17-19 juillet 2026, Millau (12) » */
    private function enteteDatesLieu(array $e): string
    {
        return $this->dates((string) $e['date_debut'], (string) $e['date_fin'])
            . ', ' . $this->villeDepartement((string) ($e['adresse'] ?? ''));
    }

    /** Plage de dates lisible en français. */
    private function dates(string $debut, string $fin): string
    {
        $d = $this->parse($debut);
        $f = $this->parse($fin);
        if ($d === null || $f === null) {
            return "$debut → $fin";
        }
        [$da, $dm, $dj] = $d;
        [$fa, $fm, $fj] = $f;

        if ($da === $fa && $dm === $fm && $dj === $fj) {
            return "$dj " . self::MOIS[$dm] . " $da";
        }
        if ($da === $fa && $dm === $fm) {
            return "$dj-$fj " . self::MOIS[$dm] . " $da";
        }
        if ($da === $fa) {
            return "$dj " . self::MOIS[$dm] . " - $fj " . self::MOIS[$fm] . " $da";
        }
        return "$dj " . self::MOIS[$dm] . " $da - $fj " . self::MOIS[$fm] . " $fa";
    }

    /** @return array{0:int,1:int,2:int}|null [année, mois, jour] */
    private function parse(string $iso): ?array
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            return null;
        }
        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }

    /** « Millau (12) » déduit du code postal, ou l'adresse brute en repli. */
    private function villeDepartement(string $adresse): string
    {
        $a = trim($adresse);
        if (!preg_match('/\b(\d{5})\b[,\s]*([^,]*)$/u', $a, $m)) {
            return $a;
        }
        $cp = $m[1];
        $ville = trim(preg_replace('/^[-–\s]+/u', '', $m[2] ?? '') ?? '');
        if ($ville === '') {
            $morceaux = explode(',', substr($a, 0, (int) strpos($a, $cp)));
            $ville = trim((string) end($morceaux));
        }
        $dep = $this->departement($cp);
        return $ville !== '' ? "$ville ($dep)" : "($dep)";
    }

    private function departement(string $cp): string
    {
        if (str_starts_with($cp, '20')) {
            return (int) $cp <= 20190 ? '2A' : '2B';
        }
        if (preg_match('/^9[78]/', $cp)) {
            return substr($cp, 0, 3);
        }
        return substr($cp, 0, 2);
    }

    /** Liste des catégories cochées, séparées par des virgules. */
    private function categories(array $e): string
    {
        $c = [];
        if (!empty($e['cat_mineraux']))      $c[] = 'Minéraux';
        if (!empty($e['cat_micromineraux'])) $c[] = 'Microminéraux';
        if (!empty($e['cat_fossiles']))      $c[] = 'Fossiles';
        if (!empty($e['cat_gemmes']))        $c[] = 'Gemmes/bijoux';
        if (!empty($e['cat_esoterisme']))    $c[] = 'Ésotérisme/lithothérapie';
        return implode(', ', $c);
    }
}
