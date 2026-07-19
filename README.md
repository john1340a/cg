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

## Documentation

La documentation technique détaillée se trouve dans [`docs/`](docs/) :

| Document | Contenu |
|----------|---------|
| [`docs/architecture/overview.md`](docs/architecture/overview.md) | vue d'ensemble, patterns MVC, machine à états, flux, routes, modèle de données |
| [`docs/libs/core.md`](docs/libs/core.md) | socle technique (PHP, PostGIS, PHPMailer) et couche `src/Core/` |
| [`docs/libs/backend.md`](docs/libs/backend.md) | contrôleurs, services, modèles, sécurité |
| [`docs/libs/frontend.md`](docs/libs/frontend.md) | JS vanilla, MapLibre, direction artistique, embed iframe |
| [`docs/deploiement.md`](docs/deploiement.md) | mise en production sur alwaysdata + intégration WordPress |
| [`docs/recette.md`](docs/recette.md) | checklist de recette du parcours complet |

---

## En bref

- **API REST JSON** ; toute mutation exige un jeton CSRF (`GET /api/csrf` →
  en-tête `X-CSRF-Token`). Voir les routes dans
  [`docs/architecture/overview.md`](docs/architecture/overview.md#routes-api-résumé).
- **Cycle de vie d'une annonce** : `brouillon → en_attente_paiement /
  en_attente_validation → publie / rejete`. Une annonce non `publie`
  n'apparaît **jamais** sur la carte. Paiement (10 €) hors application,
  marqué reçu manuellement par l'admin.
- **Sécurité** : PDO préparé, CSRF, sessions durcies, rate limiting, uploads
  contrôlés (MIME réel + resize GD, hors racine web), CSP `frame-ancestors`
  pour l'iframe. Détails dans [`docs/libs/backend.md`](docs/libs/backend.md#sécurité).
