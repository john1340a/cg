# Lib — Frontend (vanilla + MapLibre)

Interface client sans framework ni build : HTML/CSS/JS servis en fichiers
statiques, consommant l'[API REST](backend.md). Cartographie *MapLibre GL*,
direction artistique alignée sur `mineralogique.com`.

---

## Technologies

### MapLibre GL 4.7.1

- **Rôle** : rendu cartographique (carte publique, mini-carte de géocodage,
  embed iframe).
- **Pourquoi** : bibliothèque open source (fork de Mapbox GL), sans clé ni
  quota, gère les fonds raster, les sources GeoJSON, les popups et le
  responsive. Servie **en local** (`assets/vendor/`) pour la robustesse et la
  compatibilité CSP de l'iframe.
- Doc : <https://maplibre.org/maplibre-gl-js/docs/>

### JavaScript vanilla (ES2020)

- **Rôle** : logique client (formulaires, filtres, appels API).
- **Pourquoi** : aucune étape de build ; le code reste lisible et directement
  déployable. Le contenu dynamique est inséré via le DOM (`textContent`),
  jamais en `innerHTML` brut — protection XSS.

---

## Modules JS (`assets/js/`)

| Fichier | Rôle |
|---------|------|
| `api.js` | client HTTP partagé : cache du jeton CSRF, `get/post/put/del`, multipart, helpers DOM |
| `auth.js` | pages connexion / inscription / mot de passe oublié ; redirige admin → `/admin/` |
| `compte.js` | tableau de bord organisateur (liste, soumettre, supprimer) |
| `annonce.js` | formulaire annonce : géocodage BAN, mini-carte, marqueur déplaçable, upload |
| `carte.js` | **carte publique + embed** : GeoJSON, fonds, couleur par mois, filtres, popups |
| `admin.js` | back-office : modération, abonnés (import CSV), utilisateurs, paramètres |

### `carte.js` — points clés

- **Fonds commutables** : OSM, CartoDB Positron (clair), Esri World Imagery
  (satellite), déclarés comme styles raster MapLibre.
- **Symbologie par mois** : expression `match` sur la propriété `mois`
  (12 couleurs) + légende. Data-viz : la palette reste distincte de la DA.
- **Filtres dynamiques** : chaque changement relance `GET /api/events` et
  met à jour la carte + la liste latérale **sans rechargement de page**.
- **Attribution** : `attributionControl: false` à la création puis un seul
  contrôle `compact` ajouté manuellement (évite le doublon d'attribution).
- **Partage carte / embed** : `initCarte({ embed: bool })` sert les deux pages.

---

## Direction artistique

Alignée sur **mineralogique.com** (relevé sur les styles calculés réels du site) :

| Élément | Valeur |
|---------|--------|
| Fond | blanc `#ffffff` |
| Texte | anthracite `#1b1b1b` |
| En-tête | noir `#000000` |
| Accent | jaune doré `#ffc800` |
| Boutons | fond noir + texte jaune, angles 3 px |
| Titres | **Montserrat** (léger, lettres espacées) |
| Texte courant | **Roboto** |

Feuilles : `assets/css/style.css` (charte), `carte.css` (carte/embed),
`fonts.css` (polices), `icons.css` (icônes).

### Polices & icônes (locales)

- **Rôle** : Montserrat + Roboto (typo), Material Symbols Outlined (icônes).
- **Pourquoi** : servies **en local** en WOFF2 — aucun CDN, condition
  nécessaire à la **CSP stricte** de l'embed iframe. Material Symbols est
  sous-ensemblée aux seules icônes utilisées (~137 Ko au lieu de ~4 Mo).
- **Icônes** : remplacent les emojis via ligatures (`<span class="msi">event</span>`
  → icône calendrier). Décoratives → `aria-hidden="true"`.
- Fichiers : `assets/fonts/montserrat.woff2`, `roboto.woff2`,
  `material-symbols.woff2`.

> Exception : dans les **emails**, le losange `◆` reste un caractère Unicode
> (les clients mail ne chargent pas les polices d'icônes).

---

## Responsive

Mobile-first. La carte passe le panneau de filtres **sous** la carte en
dessous de 720 px, avec un bouton repliable (`expand_more` / `chevron_right`).
Grilles en `minmax(0, 1fr)` pour éviter tout débordement horizontal des
champs `date` dans le panneau. Vérifié en desktop et à 375 px.

---

## Pages (`public/`)

| Page | Rôle |
|------|------|
| `index.html` | accueil (présentation, liens carte / espace organisateur) |
| `carte.html` | carte publique complète (chrome + filtres + liste) |
| `embed.html` | carte pour iframe (sans chrome, plein cadre) |
| `connexion.html` | connexion / inscription / mot de passe oublié |
| `reset.html` | définition d'un nouveau mot de passe (via jeton) |
| `confidentialite.html` | politique de confidentialité (RGPD) |
| `compte/index.html` | tableau de bord organisateur |
| `compte/annonce.html` | formulaire création / édition d'annonce |
| `admin/*.html` | back-office (modération, abonnés, utilisateurs, paramètres) |

---

## Intégration WordPress (iframe)

```html
<div style="position:relative;width:100%;height:640px;">
  <iframe src="https://bourses.mineralogique.fr/embed.html"
          title="Carte des bourses aux minéraux"
          style="width:100%;height:100%;border:0;border-radius:8px;"
          loading="lazy"></iframe>
</div>
```

Le domaine WordPress doit figurer dans `IFRAME_ALLOWED_ORIGINS` (ou dans
**Admin → Paramètres**), sinon la CSP `frame-ancestors` bloque l'affichage.
