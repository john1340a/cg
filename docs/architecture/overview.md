# Architecture — Vue d'ensemble

Application web cartographique de référencement des **bourses aux minéraux en
France**. Les organisateurs saisissent leurs annonces (avec géocodage) ;
l'administrateur de la revue *MINERALOGIQUE* valide manuellement ; le public
consulte les événements publiés sur une carte *MapLibre* intégrable en `iframe`
dans un site WordPress.

Application monolithique légère : **PHP 8 natif** côté serveur (aucun framework
lourd), **PostgreSQL/PostGIS** pour les données spatiales, **HTML/CSS/JS vanilla
+ MapLibre GL** côté client (aucune étape de build).

---

## Structure du projet

```
public/               DOCUMENT ROOT (seul dossier exposé au web)
  index.php           front controller (bootstrap + routage)
  .htaccess           réécriture Apache + règles de sécurité
  *.html              pages (accueil, carte, connexion, reset, confidentialité)
  compte/             espace organisateur (tableau de bord, formulaire annonce)
  admin/              back-office (modération, abonnés, utilisateurs, paramètres)
  assets/
    css/              style.css (DA), carte.css, fonts.css, icons.css
    js/               api.js, format.js, auth.js, compte.js, annonce.js, carte.js, admin.js
    fonts/            Montserrat, Roboto, Material Symbols (WOFF2 locaux)
    vendor/           maplibre-gl (js + css)
src/                  code PHP hors document root (autoload PSR-4 « App\ »)
  Core/               Router, Database, Session, Csrf, Auth, RateLimiter,
                      Validator, Request, Response, App, Env
  Controllers/        Auth, Event, Admin, Subscriber, Upload, Embed, Page
  Services/           Auth, Event, EventTextFormatter, Geocoding, Image, Mail
  Models/             User, Event, Subscriber, Payment, Setting
  routes.php          table des routes
db/                   migrations SQL (001..006) + migrate.php
scripts/              create_admin.php (compte admin initial)
storage/              uploads (affiches), logs, emails locaux — hors web
templates/emails/     gabarits HTML des emails transactionnels
docs/                 cette documentation
```

Détails par couche : voir [`docs/libs/core.md`](../libs/core.md),
[`docs/libs/backend.md`](../libs/backend.md),
[`docs/libs/frontend.md`](../libs/frontend.md).

---

## Patterns architecturaux

### MVC léger (sans framework)

Le flux d'une requête API suit une séparation stricte des responsabilités :

```
Requête HTTP
   │
   ▼
public/index.php ─► App::run()              amorçage (env, session, erreurs)
   │
   ▼
Core\Router                                  résout URL + méthode → handler
   │
   ▼
Controllers\*                                validation entrée, CSRF, HTTP
   │
   ▼
Services\*                                   logique métier (règles, emails)
   │
   ▼
Models\*                                     accès données (PDO préparé)
   │
   ▼
PostgreSQL / PostGIS
```

**Règle d'or** : jamais de logique métier dans les routes ni dans les modèles.
Les contrôleurs ne parlent pas directement à *PDO* — ils passent par les
services et les modèles.

**Routage des pages** : les pages d'index (`/`, `/compte`, `/admin`) sont
servies par le routeur via `PageController`, et non par le `DirectoryIndex`
d'Apache. Le `public/.htaccess` désactive l'index automatique et force la
racine vers `index.php` — comportement portable, indépendant de l'hébergeur
(alwaysdata exécute `index.php` avant `index.html` à la racine).

### Injection légère

Pas de conteneur d'injection de dépendances : chaque contrôleur instancie les
services dont il a besoin dans son constructeur. La connexion *PDO* est un
**singleton** (`Core\Database::pdo()`) partagé par tous les modèles.

### Frontend découplé

Le frontend est 100 % statique et consomme l'**API REST JSON**. Un client HTTP
partagé (`assets/js/api.js`) gère le jeton CSRF et uniformise les appels. La
carte publique et l'`embed` iframe partagent le même module `carte.js`.

---

## Machine à états d'une annonce

Le cœur métier est le cycle de vie d'une annonce (`events.statut`) :

```
brouillon
   │  soumission
   ├─ compte exempté        ─► en_attente_validation   (toutes annonces gratuites)
   ├─ abonné & 1re annonce  ─► en_attente_validation   (gratuite, paiement exonéré)
   └─ sinon                 ─► en_attente_paiement      (paiement 10 € attendu)

en_attente_paiement   ─ admin : « paiement reçu » ──► en_attente_validation
en_attente_validation ─ admin : « valider » ────────► publie   (+ email, sur la carte)
en_attente_validation ─ admin : « rejeter » ────────► rejete   (+ email, motif)
rejete                ─ organisateur : modifie ─────► (re-soumission possible)
```

**Invariant** : seules les annonces au statut `publie` sont exposées par
l'API publique — une annonce non validée n'apparaît **jamais** sur la carte.

**Règle de gratuité** (dans l'ordre de priorité) :
1. **Compte exempté** (`users.paiement_exempte`) : **toutes** les annonces de
   l'organisateur sont gratuites (ex. organisateur payant déjà une pub pleine
   page). Activable par l'admin dans Back-office → Utilisateurs.
2. **Abonné, 1re annonce** : gratuite (paiement exonéré).
3. **Sinon** : paiement de 10 € attendu.

**Paiement** : l'organisateur non exonéré est redirigé vers la **fiche produit
WooCommerce** (paramètre `lien_paiement`, avec son email pré-rempli dans l'URL)
pour régler en ligne. Le rapprochement reste **manuel** : l'admin retrouve la
commande par email et marque « paiement reçu ». Aucun webhook / passerelle
bancaire n'est intégré côté application. Un repli virement/chèque
(`instructions_paiement`) s'affiche si aucun lien n'est configuré.

---

## Flux de données principaux

### 1. Publication d'une annonce (organisateur)

```
1. Formulaire annonce  → POST /api/geocode (proxy BAN) → point lon/lat
2. Ajustement manuel du marqueur sur la mini-carte MapLibre
3. POST /api/mes-annonces (multipart : champs + affiche)
4. ImageService : contrôle MIME, redimensionnement GD, stockage hors web
5. EventModel : INSERT ... ST_SetSRID(ST_MakePoint(lon,lat),4326)
6. POST /api/mes-annonces/{id}/soumettre → EventService applique la règle d'état
```

### 2. Consultation publique (carte)

```
1. carte.js → GET /api/events?mois=…&categorie=…&type=…&passes=…
2. EventModel::findPublished() (WHERE statut='publie' + filtres + GIST geom)
3. EventService::toGeoJson() → FeatureCollection
4. MapLibre : source geojson, marqueurs colorés par mois, popups, liste
```

### 3. Intégration WordPress (iframe)

```
Page WordPress → <iframe src="…/embed.html">
   │
   ▼
EmbedController::page()   émet Content-Security-Policy: frame-ancestors <domaines>
   │                       (autorise uniquement le domaine du client)
   ▼
embed.html + carte.js     carte sans chrome, plein cadre, responsive
```

---

## Routes API (résumé)

Toutes les mutations exigent un jeton **CSRF** (`GET /api/csrf` →
en-tête `X-CSRF-Token`). Sessions en cookie `HttpOnly` + `SameSite=Lax`.

| Domaine | Route | Rôle |
|---------|-------|------|
| Public | `GET /api/events` | GeoJSON des annonces publiées (filtres) |
| Public | `GET /api/events/{id}` | détail public |
| Public | `GET /api/affiche/{fichier}` | sert une affiche (accès contrôlé) |
| Auth | `POST /api/auth/register\|login\|logout\|forgot\|reset` | authentification |
| Auth | `GET /api/auth/me` | utilisateur courant |
| Auth | `POST /api/geocode` | proxy BAN (authentifié) |
| Orga | `GET\|POST /api/mes-annonces` | liste / création |
| Orga | `PUT\|DELETE /api/mes-annonces/{id}` | édition / suppression |
| Orga | `POST /api/mes-annonces/{id}/soumettre` | soumission (règle d'état) |
| Admin | `GET /api/admin/events` | file de modération |
| Admin | `GET /api/admin/events/export` | export texte (.txt) de toutes les annonces |
| Admin | `POST /api/admin/events/{id}/paiement-recu\|valider\|rejeter` | modération |
| Admin | `POST /api/admin/events` | saisie déléguée |
| Admin | `GET\|PUT /api/admin/settings` | paramètres |
| Admin | `GET\|POST /api/admin/subscribers`, `.../import`, `DELETE .../{id}` | whitelist abonnés |
| Admin | `GET /api/admin/users`, `POST .../{id}/desactiver` | utilisateurs |
| Admin | `POST /api/admin/users/{id}/exemption` | (dés)active la gratuité illimitée d'un compte |
| Embed | `GET /embed.html` | carte iframe (en-tête CSP) |

Table complète : [`src/routes.php`](../../src/routes.php).

---

## Modèle de données (PostgreSQL / PostGIS)

| Table | Rôle |
|-------|------|
| `users` | organisateurs + admin (email `CITEXT`, `bcrypt`, rôle, `est_abonne`, `paiement_exempte`) |
| `subscribers_whitelist` | emails abonnés à la revue (1re annonce gratuite) |
| `events` | annonces (géométrie `Point`/4326, statut, catégories, dates) |
| `payments_log` | suivi paiements (`attendu` / `recu` / `exonere`) |
| `settings` | paramètres clé/valeur éditables par l'admin (dont `lien_paiement`) |
| `rate_limits` | limitation de débit (login / inscription) |

**Catégories** d'une annonce (colonnes booléennes de `events`) :
`cat_mineraux`, `cat_micromineraux`, `cat_fossiles`, `cat_gemmes`,
`cat_esoterisme`.

Migrations (appliquées dans l'ordre par [`db/migrate.php`](../../db/migrate.php)) :
[`001_init`](../../db/001_init.sql),
[`002_seed_settings`](../../db/002_seed_settings.sql),
[`003_rate_limits`](../../db/003_rate_limits.sql),
[`004_lien_paiement`](../../db/004_lien_paiement.sql) (lien WooCommerce),
[`005_cat_micromineraux`](../../db/005_cat_micromineraux.sql) (catégorie microminéraux),
[`006_paiement_exempte`](../../db/006_paiement_exempte.sql) (exemption de paiement par compte).

---

## Déploiement & recette

- Mise en production sur alwaysdata : [`docs/deploiement.md`](../deploiement.md)
- Checklist de recette du parcours complet : [`docs/recette.md`](../recette.md)
