# Déploiement sur alwaysdata

Ce guide décrit la mise en production de l'application sur un hébergement mutualisé
**alwaysdata** (PHP + PostgreSQL/PostGIS + SMTP).

---

## 1. Base de données PostgreSQL + PostGIS

1. Dans l'admin alwaysdata → **Bases de données → PostgreSQL → Ajouter une base**.
   - Nom : ex. `moncompte_mineralogique`
   - Créer un utilisateur dédié + mot de passe.
2. **Activer PostGIS** : alwaysdata fournit PostGIS. La migration exécute
   `CREATE EXTENSION IF NOT EXISTS postgis;` (ainsi que `citext` et `pgcrypto`).
   Si l'extension nécessite un privilège particulier, l'activer depuis l'interface
   alwaysdata ou via un ticket support, puis relancer la migration.

---

## 2. Dépôt des fichiers

1. Envoyer le projet dans un dossier privé, par ex. `~/bourses-mineraux/`
   (via SSH/Git ou SFTP).
2. Installer les dépendances :
   ```bash
   cd ~/bourses-mineraux
   composer install --no-dev --optimize-autoloader
   ```
3. Droits d'écriture sur le stockage :
   ```bash
   chmod -R 775 storage
   ```

---

## 3. Configuration (.env)

Copier `.env.example` en `.env` (jamais versionné) et renseigner :

```ini
APP_ENV=production
APP_URL=https://bourses.mineralogique.fr     # URL publique réelle
APP_SECRET=<chaîne aléatoire longue>

DB_HOST=postgresql-moncompte.alwaysdata.net
DB_PORT=5432
DB_NAME=moncompte_mineralogique
DB_USER=moncompte_user
DB_PASS=<mot de passe DB>

MAIL_ENABLED=true
SMTP_HOST=smtp-moncompte.alwaysdata.net
SMTP_PORT=587
SMTP_SECURITY=tls
SMTP_USER=contact@mineralogique.fr
SMTP_PASS=<mot de passe boîte mail alwaysdata>
MAIL_FROM=contact@mineralogique.fr
MAIL_FROM_NAME=Bourses aux Minéraux

# Domaine WordPress autorisé à intégrer l'iframe :
IFRAME_ALLOWED_ORIGINS=https://www.mineralogique.fr https://mineralogique.fr
```

> Le domaine iframe peut aussi être défini plus tard dans le **back-office → Paramètres**
> (il a priorité sur la variable d'environnement).

---

## 4. Site (document root)

Dans l'admin alwaysdata → **Sites → Ajouter un site** :

- Type : **PHP**
- Version PHP : 8.x
- **Racine (document root)** : `~/bourses-mineraux/public` ← important, pointer sur `public/`
- Associer le nom de domaine (ex. `bourses.mineralogique.fr`) + activer le **certificat SSL**
  (HTTPS obligatoire : les cookies de session sont `Secure` en production).

Le fichier `public/.htaccess` gère la réécriture vers `index.php` et force le passage
de `embed.html` par PHP (pour l'en-tête CSP).

---

## 5. Initialisation

Depuis un shell SSH alwaysdata :

```bash
cd ~/bourses-mineraux
php db/migrate.php                                   # crée tables + PostGIS + index
php scripts/create_admin.php admin@mineralogique.fr <MotDePasseFort> Admin MINERALOGIQUE
```

---

## 6. Emails (SMTP)

1. Créer une **boîte email** alwaysdata (ex. `contact@mineralogique.fr`).
2. Renseigner `SMTP_*` dans `.env` avec ces identifiants.
3. Vérifier l'envoi : déclencher un « mot de passe oublié » et confirmer la réception.

En cas de souci, `MAIL_ENABLED=false` bascule en mode fichier (`storage/logs/mails/`)
pour diagnostiquer sans SMTP.

---

## 7. Intégration dans WordPress

Dans une page WordPress, insérer un **bloc HTML personnalisé** :

```html
<div style="position:relative;width:100%;height:640px;">
  <iframe src="https://bourses.mineralogique.fr/embed.html"
          title="Carte des bourses aux minéraux"
          style="width:100%;height:100%;border:0;border-radius:8px;"
          loading="lazy"></iframe>
</div>
```

L'en-tête `Content-Security-Policy: frame-ancestors …` autorise **uniquement** le(s)
domaine(s) configuré(s) (`IFRAME_ALLOWED_ORIGINS` ou Paramètres admin). Vérifier que
le domaine WordPress exact (avec/sans `www`, en `https`) y figure, sinon l'iframe
sera bloquée par le navigateur.

---

## 8. Cron (optionnel)

Aucun cron n'est indispensable. Pour purger périodiquement la table de limitation
de débit (`rate_limits`), on peut planifier :

```bash
php -r "require 'vendor/autoload.php'; App\Core\Env::load(); App\Core\RateLimiter::purge(86400);"
```
(alwaysdata → **Tâches planifiées**, ex. une fois par jour.)

---

## 9. Vérifications post-déploiement

- [ ] `https://<domaine>/` s'affiche (accueil).
- [ ] `https://<domaine>/carte.html` charge la carte et les événements publiés.
- [ ] Inscription + email de confirmation reçu.
- [ ] `https://<domaine>/embed.html` renvoie l'en-tête `Content-Security-Policy: frame-ancestors …`
      (vérifiable avec les outils réseau du navigateur).
- [ ] L'iframe s'affiche dans la page WordPress de test (desktop + mobile).
- [ ] Le back-office `/admin/` est accessible avec le compte admin.

Voir aussi la **checklist de recette** : `RECETTE.md`.
