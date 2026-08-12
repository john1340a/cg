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
| `format.js` | **formatage partagé** des annonces (titre ordinal, dates lisibles, `Ville (dépt)`) — utilisé par carte / admin / compte |
| `auth.js` | pages connexion / inscription / mot de passe oublié ; redirige admin → `/admin/` |
| `compte.js` | tableau de bord organisateur (liste, soumettre, supprimer, **bouton « Payer » WooCommerce**) |
| `annonce.js` | formulaire annonce : géocodage BAN, mini-carte, marqueur déplaçable, upload |
| `carte.js` | **carte publique + embed** : GeoJSON, fonds, pins par mois, filtres, popups |
| `admin.js` | back-office : modération (+ **export .txt**), abonnés (import CSV), utilisateurs (+ **exemption**), paramètres |

> **`format.js`** est chargé **avant** `carte.js` / `admin.js` / `compte.js`
> sur les pages qui affichent des annonces. Il centralise : titre
> « 53ème Salon (ventes-échanges) », dates (« 17-19 juillet 2026 »), et
> `Ville (dépt)` déduite du code postal (gère Corse `2A/2B` et DOM `97x/98x`),
> avec repli sur l'adresse brute si aucun code postal.

### `carte.js` — points clés

- **Cadrage initial France** : au `load`, `fitBounds` sur les frontières de la
  France métropolitaine (Corse incluse) — la vue s'ouvre **toujours** sur la
  France quels que soient les événements (même à l'étranger). Navigation libre
  ensuite ; le clic sur un événement y vole (`flyTo`).
- **Marqueurs « pins »** : épingles goutte colorées par mois (couche `symbol`,
  images générées sur canvas via `addImage`, une par mois `pin-1`…`pin-12`),
  avec un losange minéral blanc. Réenregistrées au changement de fond
  (`setStyle` purge images + couches).
- **Symbologie par mois** : 12 couleurs + légende. Data-viz : la palette reste
  distincte de la DA.
- **Filtres dynamiques** : mois, période, **catégorie** (minéraux,
  microminéraux, fossiles, gemmes, ésotérisme), type, passés. Chaque changement
  relance `GET /api/events` et met à jour carte + liste **sans rechargement**.
- **Popup** : titre + en-tête « dates, Ville (dépt) » (via `format.js`) ; la
  ligne adresse n'affiche que le lieu précis, **sans répéter la ville**.
- **Attribution** : `attributionControl: false` à la création puis un seul
  contrôle `compact` ajouté manuellement (évite le doublon d'attribution).
- **Partage carte / embed** : `initCarte({ embed: bool })` sert les deux pages.
  L'embed ajoute un bouton **« Publier ma bourse »** (nouvel onglet).

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
  sous-ensemblée par **codepoints exacts** aux seules icônes utilisées
  (~4 Ko pour ~21 icônes, au lieu de ~4 Mo). Régénération : subset des
  codepoints des icônes référencées, avec `--no-layout-closure` pour ne pas
  réintégrer toutes les ligatures.
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
