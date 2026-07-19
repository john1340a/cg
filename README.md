# Bourses aux Minéraux — Calendrier cartographique

Application web cartographique de référencement et de consultation des **bourses aux
minéraux en France**. Les organisateurs saisissent leurs annonces via un formulaire
(avec géocodage) ; l'administrateur (la revue MINERALOGIQUE) valide manuellement ;
le public consulte les événements publiés sur une **carte MapLibre** intégrable en
**iframe** dans un site WordPress.

---

## Pile technique

| Couche | Choix |
|--------|-------|
| Backend | **PHP 8.x natif** (PDO, MVC léger maison, API REST JSON) — 1 seule dépendance : PHPMailer |
| Base de données | **PostgreSQL + PostGIS** (géométries Point/4326, index GIST) |
| Frontend | **HTML/CSS/JS vanilla + MapLibre GL** (servi en local, aucun build step) |
| Géocodage | **API adresse.data.gouv.fr** (BAN), via un proxy backend |
| Emails | **SMTP alwaysdata** via PHPMailer (mode fichier en local) |

Aucun paiement en ligne : le règlement (10 €) se fait **hors application** (virement/chèque),
l'admin marque le paiement reçu puis valide.

---

## Arborescence

```
public/            ← document root (à pointer sur alwaysdata)
  index.php        ← front controller
  .htaccess        ← réécriture + sécurité
  *.html           ← pages (accueil, carte, connexion, reset, confidentialité)
  compte/          ← espace organisateur (tableau de bord, formulaire)
  admin/           ← back-office (modération, abonnés, utilisateurs, paramètres)
  assets/          ← css, js, vendor (maplibre)
src/               ← code PHP (hors document root)
  Core/            ← Router, Database, Session, Csrf, Auth, RateLimiter, Validator, App, Env
  Controllers/     ← Auth, Event, Admin, Subscriber, Upload, Embed
  Services/        ← Auth, Event, Geocoding, Image, Mail
  Models/          ← User, Event, Subscriber, Payment, Setting
  routes.php       ← table des routes
db/                ← migrations SQL (001..003) + migrate.php
scripts/           ← create_admin.php
storage/           ← uploads (affiches), logs, emails locaux (hors document root)
templates/emails/  ← gabarits HTML des emails transactionnels
```

---

## Installation locale

Prérequis : PHP 8.1+ (extensions `pdo_pgsql`, `gd`, `fileinfo`), PostgreSQL 13+ avec PostGIS,
Composer.

```bash
# 1. Dépendances
composer install

# 2. Configuration
cp .env.example .env         # puis renseigner DB_*, APP_URL, SMTP_* si besoin

# 3. Base de données (créer la base au préalable, ex. « mineralogique »)
#    createdb mineralogique   (ou via psql : CREATE DATABASE mineralogique;)
php db/migrate.php           # applique les migrations (PostGIS, tables, index)

# 4. Compte administrateur initial
php scripts/create_admin.php admin@mineralogique.fr MonMotDePasse Admin MINERALOGIQUE

# 5. Lancer en local
php -S 127.0.0.1:8000 -t public public/index.php
```

Ouvrir <http://127.0.0.1:8000>.

En local, `MAIL_ENABLED=false` : les emails ne sont pas expédiés mais **écrits dans
`storage/logs/mails/`** (pratique pour vérifier le contenu sans SMTP).

---

## API REST (résumé)

Toutes les mutations exigent un jeton **CSRF** (`GET /api/csrf` → header `X-CSRF-Token`).
Sessions en cookie `HttpOnly` + `SameSite=Lax`.

**Public**
- `GET /api/events` — GeoJSON des annonces **publiées** (filtres : `mois`, `debut`, `fin`, `categorie`, `type`, `passes`)
- `GET /api/events/{id}` — détail public
- `GET /api/affiche/{fichier}` — sert une affiche (accès contrôlé)

**Auth**
- `POST /api/auth/register|login|logout|forgot|reset`, `GET /api/auth/me`
- `POST /api/geocode` — proxy BAN (authentifié)

**Organisateur**
- `GET/POST /api/mes-annonces`, `PUT/DELETE /api/mes-annonces/{id}`, `POST /api/mes-annonces/{id}/soumettre`

**Admin**
- `GET /api/admin/events`, `POST /api/admin/events` (saisie déléguée)
- `POST /api/admin/events/{id}/paiement-recu|valider|rejeter`
- `GET /api/admin/users`, `POST /api/admin/users/{id}/desactiver`
- `GET/PUT /api/admin/settings`
- `GET/POST /api/admin/subscribers`, `POST /api/admin/subscribers/import`, `DELETE /api/admin/subscribers/{id}`

**Embed**
- `GET /embed.html` — carte pour iframe (émet l'en-tête CSP `frame-ancestors`)

---

## Cycle de vie d'une annonce

```
brouillon
  └─ soumettre ─▶ abonné & 1re annonce ─▶ en_attente_validation  (gratuite, paiement exonéré)
                 sinon                  ─▶ en_attente_paiement    (+ email instructions)
en_attente_paiement ─ admin: paiement reçu ─▶ en_attente_validation
en_attente_validation ─ admin: valider ─▶ publie   (+ email, visible sur la carte)
en_attente_validation ─ admin: rejeter ─▶ rejete   (+ email avec motif)
```

Une annonce non `publie` **n'apparaît jamais** sur la carte publique.

---

## Sécurité

- Requêtes **PDO préparées** partout ; validation/échappement systématique.
- **CSRF** sur toutes les mutations ; sessions durcies (HttpOnly, SameSite, Secure en prod).
- **Rate limiting** sur login / inscription / mot de passe oublié.
- Uploads : contrôle **MIME réel** (finfo), renommage aléatoire, **redimensionnement GD**,
  stockage **hors document root**, service via endpoint contrôlé.
- Emails : contenu dynamique **échappé** (anti-injection).
- Iframe : **CSP `frame-ancestors`** limitée au(x) domaine(s) du client (configurable).

Voir `DEPLOIEMENT.md` pour la mise en production sur alwaysdata.
