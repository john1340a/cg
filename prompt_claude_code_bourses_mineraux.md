## CONTEXTE

Tu vas développer une application web cartographique complète pour un client (revue de minéralogie trimestrielle). Objectif : un calendrier cartographique des bourses aux minéraux en France, où des organisateurs d'événements saisissent leurs annonces via un formulaire, et où le public consulte les événements validés sur une carte interactive. L'application sera intégrée dans un site WordPress existant via iframe responsive.

Je suis développeur SIG expérimenté (MapLibre, PostGIS, PHP). Tu produis du code propre, commenté en français, sans framework lourd côté PHP (pas de Laravel/Symfony — PHP natif structuré ou micro-framework léger type Slim si tu le justifies).

## STACK IMPOSÉE (hébergement alwaysdata)

- **Backend : PHP 8.x** (PDO, architecture MVC légère, API REST JSON)
- **Base de données : PostgreSQL + PostGIS** (alwaysdata le supporte)
- **Frontend : HTML/CSS/JS vanilla + MapLibre** (pas de React, pas de build step complexe ; un bundler simple type Vite est acceptable si tu le justifies, sinon fichiers statiques)
- **Géocodage : API adresse.data.gouv.fr** (Base Adresse Nationale, gratuite, adaptée aux adresses françaises)
- **Envoi d'emails : SMTP alwaysdata** (PHPMailer)
- Pas de paiement en ligne intégré : le paiement se fait **hors application** (virement/chèque), l'admin valide manuellement.

## MODÈLE DE DONNÉES (PostgreSQL/PostGIS)

Conçois et fournis les migrations SQL pour au minimum :

- **users** : id, nom, prénom, email (unique), mot de passe hashé (password_hash/bcrypt), rôle (`user` / `admin`), est_abonne (bool), token_reset + expiration, timestamps.
- **subscribers_whitelist** : liste d'emails abonnés à la revue, importée par l'admin (CSV). Sert à déterminer automatiquement `est_abonne` à l'inscription et à accorder la 1ère annonce gratuite.
- **events** (les annonces de bourses) :
  - intitulé de l'événement, numéro d'édition
  - date_debut, date_fin
  - type d'événement : `bourse_echanges` et/ou `bourse_vente` (les deux possibles)
  - catégories (multi) : `mineraux`, `fossiles`, `gemmes_bijoux`, `esoterisme_lithotherapie`
  - adresse complète (texte) + **geom (Point, EPSG:4326)** géocodée
  - tarif d'entrée (texte libre)
  - email de contact affiché publiquement
  - site web (optionnel)
  - image d'affiche (upload, chemin fichier)
  - owner_id (FK users)
  - statut : `brouillon` → `en_attente_paiement` → `en_attente_validation` → `publie` / `rejete`
  - est_gratuite (bool : 1ère annonce d'un abonné)
  - timestamps
- **payments_log** (simple) : event_id, montant (10 €), statut (`attendu`, `recu`, `exonere`), note admin, date.

## MODULES FONCTIONNELS

### 1. Carte publique (intégrable en iframe)
- Carte MapLibre plein cadre, responsive mobile/desktop.
- **Plusieurs fonds de carte** commutables (OSM standard, un fond clair type CartoDB Positron, éventuellement satellite).
- Marqueurs des événements **publiés** uniquement, avec **symbologie différenciée par mois** (couleur du marqueur selon le mois de date_debut, légende visible).
- **Popups détaillées** : intitulé, dates, adresse, tarif, type (échanges/vente), catégories, email de contact, lien site web, miniature de l'affiche (cliquable pour agrandir).
- **Filtres** : par période (date de début / fin), par mois, par catégorie, par type. Affichage dynamique des résultats (carte + liste latérale ou sous la carte sur mobile).
- Événements passés masqués par défaut (option pour les afficher).
- Page/route dédiée `embed` sans chrome superflu, headers compatibles iframe (pas de X-Frame-Options bloquant pour le domaine WordPress du client — le rendre configurable).

### 2. Espace utilisateur (organisateurs)
- Inscription (nom, prénom, email, mot de passe) + connexion + déconnexion.
- À l'inscription : si l'email figure dans `subscribers_whitelist` → `est_abonne = true`.
- **Réinitialisation de mot de passe par email** (token à expiration) — servira aussi à activer rétroactivement des comptes créés en masse pour les abonnés existants.
- Tableau de bord : mes annonces + statuts, création / modification / suppression de ses propres annonces.
- **Formulaire de saisie d'annonce** reprenant exactement les champs du modèle papier de la revue : intitulé, n° d'édition, dates du/au, type (bourse d'échanges / bourse de vente), catégories (minéraux, fossiles, gemmes/bijoux, ésotérisme-lithothérapie), adresse de l'événement, tarif d'entrée, email de contact visible, site web, upload d'image d'affiche (jpg/png/webp, max ~5 Mo, redimensionnement serveur).
- **Géocodage automatique** de l'adresse à la saisie (appel adresse.data.gouv.fr, aperçu du point sur mini-carte, possibilité d'ajuster le marqueur manuellement avant envoi).
- À la soumission :
  - Si abonné **et** 1ère annonce → statut `en_attente_validation`, `est_gratuite = true`, payment `exonere`.
  - Sinon → statut `en_attente_paiement` + écran/email d'instructions de paiement (10 €, coordonnées de paiement configurables par l'admin).

### 3. Back-office admin
- Connexion admin sécurisée.
- File de modération : liste des annonces par statut, aperçu complet.
- Actions : **marquer paiement reçu** (→ passe en `en_attente_validation`), **valider/publier**, **rejeter avec motif** (email automatique à l'organisateur dans chaque cas).
- **Import CSV de la liste des abonnés** (emails) + gestion manuelle (ajout/suppression), avec mise à jour du flag `est_abonne` des comptes existants.
- Gestion des utilisateurs (liste, désactivation).
- Possibilité de créer/éditer une annonce au nom d'un utilisateur (saisie déléguée).
- Paramètres : texte des instructions de paiement, email expéditeur, domaine autorisé pour l'iframe.

### 4. API REST (JSON)
- `GET /api/events` (publiés, avec filtres query string : dates, mois, catégorie, type) — GeoJSON pour la carte.
- Endpoints authentifiés pour CRUD des annonces de l'utilisateur.
- Endpoints admin (modération, paiements, import abonnés).
- Sessions PHP sécurisées (cookies HttpOnly, SameSite) ; CSRF token sur toutes les mutations.

## SÉCURITÉ & QUALITÉ

- Requêtes préparées PDO partout, validation/échappement systématique des entrées.
- Upload d'images : contrôle MIME réel, renommage aléatoire, stockage hors racine si possible, redimensionnement (GD ou Imagick).
- Rate limiting simple sur login et inscription.
- RGPD de base : mention d'information sur le formulaire, seuls les champs « contact visible » sont publics, page de politique de confidentialité placeholder.
- Emails transactionnels : confirmation d'inscription, instructions de paiement, annonce validée/rejetée, reset mot de passe.

## CONTRAINTES DE DÉPLOIEMENT (alwaysdata)

- Arborescence compatible mutualisé alwaysdata : un dossier `public/` comme document root, config par variables d'environnement (`.env` non versionné + `.env.example`).
- Script SQL d'initialisation + script de création du compte admin initial.
- Fournis un `DEPLOIEMENT.md` : étapes précises pour alwaysdata (création base PostgreSQL, activation PostGIS, config PHP, SMTP, cron éventuel), et un `README.md` technique.

## MÉTHODE DE TRAVAIL ATTENDUE

1. Commence par me présenter **un plan d'architecture** (arborescence, schéma SQL, routes) et attends ma validation avant de coder.
2. Développe ensuite module par module dans cet ordre : base de données → auth → formulaire annonce + géocodage → carte publique → back-office → emails → embed iframe.
3. À chaque module : code + brève note de test manuel (comment vérifier que ça marche).
4. Signale explicitement tout choix technique qui s'écarte de ce cahier des charges et justifie-le en une phrase.
5. Termine par une checklist de recette couvrant le parcours complet : inscription → saisie annonce → paiement marqué reçu → validation admin → affichage sur la carte → intégration iframe.

## CRITÈRES D'ACCEPTATION

- Une annonce non validée n'apparaît jamais sur la carte publique.
- Un abonné (email en whitelist) obtient sa première annonce sans étape de paiement.
- La carte filtrée par mois/catégorie se met à jour sans rechargement de page.
- L'iframe s'affiche correctement sur mobile dans une page WordPress de test.
- Toute l'interface est en français.
