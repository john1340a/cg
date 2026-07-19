/* ============================================================
   carte.js — carte publique MapLibre (partagée carte + embed).
   - 3 fonds commutables (OSM, clair, satellite)
   - marqueurs colorés par mois de date_debut + légende
   - filtres dynamiques (mois, période, catégorie, type, passés)
   - popups détaillées, liste latérale, agrandissement d'affiche
   Tout contenu dynamique est inséré via le DOM (pas d'innerHTML brut).
   ============================================================ */

/* --- Palette : une couleur par mois (1 = janvier … 12 = décembre) --- */
const COULEURS_MOIS = {
    1: '#4e79a7', 2: '#59a14f', 3: '#9c755f', 4: '#f28e2b',
    5: '#edc948', 6: '#76b7b2', 7: '#e15759', 8: '#b07aa1',
    9: '#ff9da7', 10: '#8cd17d', 11: '#bab0ac', 12: '#b8860b',
};
const NOMS_MOIS = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

/* --- Définitions des fonds de carte (styles MapLibre raster) --- */
function styleFond(nom) {
    const fonds = {
        osm: {
            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            attribution: '© OpenStreetMap',
        },
        clair: {
            tiles: [
                'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
                'https://b.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
            ],
            attribution: '© OpenStreetMap, © CARTO',
        },
        satellite: {
            tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
            attribution: 'Imagerie © Esri',
        },
    };
    const f = fonds[nom] || fonds.osm;
    return {
        version: 8,
        sources: { fond: { type: 'raster', tiles: f.tiles, tileSize: 256, attribution: f.attribution } },
        layers: [{ id: 'fond', type: 'raster', source: 'fond' }],
    };
}

let carte, popupCourante = null;
let dernieresFeatures = [];

/**
 * Initialise la carte. options = { embed: bool }.
 * En mode embed, on masque une partie du chrome (géré côté HTML/CSS).
 */
function initCarte(options = {}) {
    remplirSelecteurMois();
    construireLegende();

    carte = new maplibregl.Map({
        container: 'carte',
        style: styleFond('osm'),
        center: [2.4, 46.6],
        zoom: 5,
    });
    carte.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');
    carte.addControl(new maplibregl.AttributionControl({ compact: true }));

    carte.on('load', () => {
        ajouterSourceEtCouches();
        chargerEvenements();
    });

    brancherFiltres();
    brancherSelecteurFond();
    brancherModale();
    brancherBasculePanneau();
}

/* ---------- Source et couches de marqueurs ---------- */
function ajouterSourceEtCouches() {
    carte.addSource('events', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] },
    });

    // Expression MapLibre : couleur selon la propriété "mois"
    const couleurParMois = ['match', ['get', 'mois']];
    for (let m = 1; m <= 12; m++) { couleurParMois.push(m, COULEURS_MOIS[m]); }
    couleurParMois.push('#666'); // défaut

    carte.addLayer({
        id: 'points',
        type: 'circle',
        source: 'events',
        paint: {
            'circle-radius': ['interpolate', ['linear'], ['zoom'], 4, 5, 10, 9],
            'circle-color': couleurParMois,
            'circle-stroke-width': 2,
            'circle-stroke-color': '#fff',
        },
    });

    carte.on('click', 'points', (e) => ouvrirPopup(e.features[0]));
    carte.on('mouseenter', 'points', () => { carte.getCanvas().style.cursor = 'pointer'; });
    carte.on('mouseleave', 'points', () => { carte.getCanvas().style.cursor = ''; });
}

/* ---------- Chargement des événements (avec filtres) ---------- */
async function chargerEvenements() {
    const params = new URLSearchParams();
    const mois = el('f-mois')?.value;
    const debut = el('f-debut')?.value;
    const fin = el('f-fin')?.value;
    const categorie = el('f-categorie')?.value;
    const type = el('f-type')?.value;
    const passes = el('f-passes')?.checked;
    if (mois) params.set('mois', mois);
    if (debut) params.set('debut', debut);
    if (fin) params.set('fin', fin);
    if (categorie) params.set('categorie', categorie);
    if (type) params.set('type', type);
    if (passes) params.set('passes', '1');

    try {
        const geojson = await API.get('/api/events?' + params.toString());
        dernieresFeatures = geojson.features || [];
        const src = carte.getSource('events');
        if (src) src.setData(geojson);
        majListe(dernieresFeatures);
        majCompteur(dernieresFeatures.length);
    } catch (err) {
        majCompteur(0);
        const liste = el('liste-events');
        if (liste) { liste.textContent = ''; const p = document.createElement('p');
            p.className = 'alerte erreur'; p.textContent = 'Erreur de chargement des événements.';
            liste.appendChild(p); }
    }
}

/* ---------- Popup détaillée ---------- */
function ouvrirPopup(feature) {
    const p = feature.properties;
    // Les catégories arrivent en JSON string via GeoJSON → parser au besoin
    const categories = typeof p.categories === 'string' ? safeParse(p.categories) : (p.categories || []);

    const div = document.createElement('div');
    div.className = 'popup-event';

    const h3 = document.createElement('h3');
    h3.textContent = p.intitule + (p.edition_num ? ` (n°${p.edition_num})` : '');
    div.appendChild(h3);

    const lignes = document.createElement('div');
    lignes.className = 'lignes';
    lignes.appendChild(ligne('📅', formatPeriode(p.date_debut, p.date_fin)));
    lignes.appendChild(ligne('📍', p.adresse));
    if (p.tarif) lignes.appendChild(ligne('🎫', 'Entrée : ' + p.tarif));
    const types = [];
    if (p.type_echanges) types.push('Échanges');
    if (p.type_vente) types.push('Vente');
    if (types.length) lignes.appendChild(ligne('🔖', types.join(' + ')));
    div.appendChild(lignes);

    // Étiquettes de catégories
    if (categories.length) {
        const etq = document.createElement('div');
        etq.className = 'etiquettes';
        categories.forEach((c) => {
            const s = document.createElement('span');
            s.className = 'etiquette';
            s.textContent = c;
            etq.appendChild(s);
        });
        div.appendChild(etq);
    }

    // Contact + site
    const contact = document.createElement('div');
    contact.className = 'lignes';
    if (p.contact_email) {
        const a = document.createElement('a');
        a.href = 'mailto:' + p.contact_email;
        a.textContent = p.contact_email;
        const d = document.createElement('div'); d.textContent = '✉️ ';
        d.appendChild(a); contact.appendChild(d);
    }
    if (p.site_web) {
        const a = document.createElement('a');
        a.href = p.site_web; a.target = '_blank'; a.rel = 'noopener noreferrer';
        a.textContent = 'Site web';
        const d = document.createElement('div'); d.textContent = '🌐 ';
        d.appendChild(a); contact.appendChild(d);
    }
    div.appendChild(contact);

    // Affiche cliquable
    if (p.affiche) {
        const img = document.createElement('img');
        img.className = 'affiche';
        img.src = p.affiche;
        img.alt = 'Affiche : ' + p.intitule;
        img.addEventListener('click', () => ouvrirModale(p.affiche));
        div.appendChild(img);
    }

    if (popupCourante) popupCourante.remove();
    popupCourante = new maplibregl.Popup({ maxWidth: '260px' })
        .setLngLat(feature.geometry.coordinates)
        .setDOMContent(div)
        .addTo(carte);
}

function ligne(icone, texte) {
    const d = document.createElement('div');
    d.textContent = `${icone} ${texte}`;
    return d;
}

/* ---------- Liste latérale ---------- */
function majListe(features) {
    const liste = el('liste-events');
    if (!liste) return;
    liste.textContent = '';

    if (!features.length) {
        const p = document.createElement('p');
        p.className = 'muet';
        p.textContent = 'Aucun événement pour ces critères.';
        liste.appendChild(p);
        return;
    }

    features.forEach((f) => {
        const p = f.properties;
        const item = document.createElement('div');
        item.className = 'event-item';
        item.style.borderLeftColor = COULEURS_MOIS[p.mois] || '#666';

        const t = document.createElement('div');
        t.className = 'titre';
        t.textContent = p.intitule;
        item.appendChild(t);

        const m = document.createElement('div');
        m.className = 'meta';
        m.textContent = `${formatPeriode(p.date_debut, p.date_fin)} — ${p.adresse}`;
        item.appendChild(m);

        item.addEventListener('click', () => {
            carte.flyTo({ center: f.geometry.coordinates, zoom: 12 });
            ouvrirPopup(f);
        });
        liste.appendChild(item);
    });
}

function majCompteur(n) {
    const c = el('compteur');
    if (c) c.textContent = `(${n})`;
}

/* ---------- Légende ---------- */
function construireLegende() {
    const leg = el('legende');
    if (!leg) return;
    leg.textContent = '';
    const h = document.createElement('h3');
    h.textContent = 'Mois de l\'événement';
    leg.appendChild(h);
    for (let m = 1; m <= 12; m++) {
        const r = document.createElement('div');
        r.className = 'rangee';
        const pastille = document.createElement('span');
        pastille.className = 'pastille';
        pastille.style.background = COULEURS_MOIS[m];
        const lbl = document.createElement('span');
        lbl.textContent = NOMS_MOIS[m];
        r.appendChild(pastille); r.appendChild(lbl);
        leg.appendChild(r);
    }
}

function remplirSelecteurMois() {
    const sel = el('f-mois');
    if (!sel) return;
    for (let m = 1; m <= 12; m++) {
        const o = document.createElement('option');
        o.value = String(m);
        o.textContent = NOMS_MOIS[m];
        sel.appendChild(o);
    }
}

/* ---------- Branchements UI ---------- */
function brancherFiltres() {
    ['f-mois', 'f-debut', 'f-fin', 'f-categorie', 'f-type', 'f-passes'].forEach((id) => {
        const e = el(id);
        if (e) e.addEventListener('change', chargerEvenements);
    });
    const reset = el('btn-reset');
    if (reset) reset.addEventListener('click', () => {
        ['f-mois', 'f-debut', 'f-fin', 'f-categorie', 'f-type'].forEach((id) => {
            const e = el(id); if (e) e.value = '';
        });
        const passe = el('f-passes'); if (passe) passe.checked = false;
        chargerEvenements();
    });
}

function brancherSelecteurFond() {
    const sel = el('fond');
    if (!sel) return;
    sel.addEventListener('change', () => {
        carte.setStyle(styleFond(sel.value));
        // Le changement de style supprime les couches → les recréer.
        carte.once('styledata', () => {
            if (!carte.getSource('events')) {
                ajouterSourceEtCouches();
                const src = carte.getSource('events');
                if (src) src.setData({ type: 'FeatureCollection', features: dernieresFeatures });
            }
        });
    });
}

function brancherModale() {
    const modale = el('modale-affiche');
    const fond = el('modale-fond');
    if (fond) fond.addEventListener('click', fermerModale);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') fermerModale(); });
}
function ouvrirModale(src) {
    const modale = el('modale-affiche');
    const img = el('modale-img');
    if (!modale || !img) return;
    img.src = src;
    modale.classList.remove('masque');
}
function fermerModale() {
    const modale = el('modale-affiche');
    if (modale) modale.classList.add('masque');
}

function brancherBasculePanneau() {
    const btn = el('bascule-panneau');
    const contenu = el('panneau-contenu');
    if (!btn || !contenu) return;
    btn.addEventListener('click', () => {
        const replie = contenu.classList.toggle('replie');
        btn.setAttribute('aria-expanded', String(!replie));
        btn.textContent = replie ? 'Filtres ▸' : 'Filtres ▾';
    });
}

/* ---------- Utilitaires ---------- */
function formatPeriode(debut, fin) {
    const f = (d) => {
        const [a, m, j] = d.split('-');
        return `${j}/${m}/${a}`;
    };
    return debut === fin ? f(debut) : `${f(debut)} → ${f(fin)}`;
}
function safeParse(s) { try { return JSON.parse(s); } catch (_) { return []; } }
