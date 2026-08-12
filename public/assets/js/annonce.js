/* ============================================================
   annonce.js — formulaire de création / édition d'annonce.
   Géocodage BAN + mini-carte MapLibre avec marqueur déplaçable.
   ============================================================ */

// Style raster OSM minimal (aucune dépendance externe hors tuiles).
const STYLE_OSM = {
    version: 8,
    sources: {
        osm: {
            type: 'raster',
            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            tileSize: 256,
            attribution: '© OpenStreetMap',
        },
    },
    layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
};

let carte, marqueur, editId = null;

document.addEventListener('DOMContentLoaded', async () => {
    const user = await exigerAuth();
    if (!user) return;

    // Pré-remplir l'email de contact avec celui du compte
    el('contact_email').value = user.email;

    el('lien-deconnexion').addEventListener('click', async (e) => {
        e.preventDefault();
        try { await API.post('/api/auth/logout'); } catch (_) {}
        API.resetCsrf();
        window.location.href = '/connexion.html';
    });

    initCarte();

    // Mode édition ?
    const params = new URLSearchParams(window.location.search);
    editId = params.get('id');
    if (editId) {
        el('titre-page').textContent = 'Modifier l\'annonce';
        await chargerAnnonce(editId);
    }

    el('btn-geocoder').addEventListener('click', geocoder);
    el('adresse').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); geocoder(); }
    });
    el('affiche').addEventListener('change', apercuAffiche);
    el('form-annonce').addEventListener('submit', enregistrer);
});

/** Initialise la mini-carte centrée sur la France. */
function initCarte() {
    carte = new maplibregl.Map({
        container: 'mini-carte',
        style: STYLE_OSM,
        center: [2.4, 46.6],
        zoom: 4.3,
    });
    carte.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    // Cliquer sur la carte place / déplace le marqueur.
    carte.on('click', (e) => placerMarqueur(e.lngLat.lng, e.lngLat.lat));
}

/** Place le marqueur (déplaçable) et mémorise les coordonnées. */
function placerMarqueur(lon, lat) {
    if (marqueur) marqueur.remove();
    marqueur = new maplibregl.Marker({ draggable: true, color: '#1b1b1b' })
        .setLngLat([lon, lat])
        .addTo(carte);
    marqueur.on('dragend', () => {
        const p = marqueur.getLngLat();
        el('lon').value = p.lng;
        el('lat').value = p.lat;
    });
    el('lon').value = lon;
    el('lat').value = lat;
}

/** Appelle le géocodage BAN via le backend. */
async function geocoder() {
    const adresse = el('adresse').value.trim();
    if (!adresse) { el('geo-statut').textContent = 'Saisissez une adresse.'; return; }

    el('geo-statut').textContent = 'Recherche…';
    try {
        const r = await API.post('/api/geocode', { adresse });
        placerMarqueur(r.lon, r.lat);
        carte.flyTo({ center: [r.lon, r.lat], zoom: 14 });
        const fiab = Math.round((r.score || 0) * 100);
        el('geo-statut').textContent = `Localisé : ${r.label} (fiabilité ${fiab}%). `
            + 'Ajustez le marqueur si nécessaire.';
    } catch (err) {
        el('geo-statut').textContent = err.message
            + ' Vous pouvez cliquer sur la carte pour placer le point manuellement.';
    }
}

/** Aperçu local de l'affiche choisie. */
function apercuAffiche() {
    const f = el('affiche').files[0];
    const zone = el('apercu-affiche');
    zone.textContent = '';
    if (!f) return;
    if (f.size > 5 * 1024 * 1024) {
        afficherAlerte('alerte', 'Image trop volumineuse (5 Mo maximum).');
        el('affiche').value = '';
        return;
    }
    const img = document.createElement('img');
    img.style.maxWidth = '220px';
    img.style.borderRadius = '8px';
    img.alt = 'Aperçu de l\'affiche';
    img.src = URL.createObjectURL(f);
    zone.appendChild(img);
}

/** Charge une annonce existante dans le formulaire (édition). */
async function chargerAnnonce(id) {
    try {
        const { events } = await API.get('/api/mes-annonces');
        const ev = events.find((e) => String(e.id) === String(id));
        if (!ev) { afficherAlerte('alerte', 'Annonce introuvable.'); return; }

        el('intitule').value = ev.intitule || '';
        el('edition_num').value = ev.edition_num || '';
        el('date_debut').value = ev.date_debut || '';
        el('date_fin').value = ev.date_fin || '';
        el('type_echanges').checked = !!ev.type_echanges;
        el('type_vente').checked = !!ev.type_vente;
        el('cat_mineraux').checked = !!ev.cat_mineraux;
        el('cat_micromineraux').checked = !!ev.cat_micromineraux;
        el('cat_fossiles').checked = !!ev.cat_fossiles;
        el('cat_gemmes').checked = !!ev.cat_gemmes;
        el('cat_esoterisme').checked = !!ev.cat_esoterisme;
        el('adresse').value = ev.adresse || '';
        el('tarif').value = ev.tarif || '';
        el('contact_email').value = ev.contact_email || '';
        el('site_web').value = ev.site_web || '';

        if (ev.lon && ev.lat) {
            const lon = parseFloat(ev.lon), lat = parseFloat(ev.lat);
            placerMarqueur(lon, lat);
            carte.jumpTo({ center: [lon, lat], zoom: 13 });
        }
        if (ev.affiche_path) {
            const zone = el('apercu-affiche');
            const img = document.createElement('img');
            img.style.maxWidth = '220px';
            img.style.borderRadius = '8px';
            img.alt = 'Affiche actuelle';
            img.src = '/api/affiche/' + encodeURIComponent(ev.affiche_path);
            zone.appendChild(img);
            const p = document.createElement('p');
            p.className = 'aide';
            p.textContent = 'Affiche actuelle. Choisissez un fichier pour la remplacer.';
            zone.appendChild(p);
        }
    } catch (err) {
        afficherAlerte('alerte', err.message);
    }
}

/** Construit le FormData depuis le formulaire. */
function construireFormData() {
    const fd = new FormData();
    const champs = ['intitule', 'edition_num', 'date_debut', 'date_fin',
                    'adresse', 'tarif', 'contact_email', 'site_web', 'lon', 'lat'];
    champs.forEach((c) => fd.append(c, el(c).value));
    ['type_echanges', 'type_vente', 'cat_mineraux', 'cat_micromineraux',
     'cat_fossiles', 'cat_gemmes', 'cat_esoterisme'].forEach((c) => {
        fd.append(c, el(c).checked ? '1' : '0');
    });
    const f = el('affiche').files[0];
    if (f) fd.append('affiche', f);
    return fd;
}

/** Validation légère côté client avant envoi. */
function validerClient() {
    if (!el('intitule').value.trim()) return 'L\'intitulé est obligatoire.';
    if (!el('date_debut').value || !el('date_fin').value) return 'Les dates sont obligatoires.';
    if (el('date_fin').value < el('date_debut').value) return 'La date de fin doit suivre la date de début.';
    if (!el('type_echanges').checked && !el('type_vente').checked) return 'Choisissez au moins un type.';
    if (!el('cat_mineraux').checked && !el('cat_micromineraux').checked && !el('cat_fossiles').checked
        && !el('cat_gemmes').checked && !el('cat_esoterisme').checked) return 'Choisissez au moins une catégorie.';
    if (!el('adresse').value.trim()) return 'L\'adresse est obligatoire.';
    if (!el('lon').value || !el('lat').value) return 'Localisez l\'adresse (bouton « Localiser » ou clic sur la carte).';
    if (!el('contact_email').value.trim()) return 'L\'email de contact est obligatoire.';
    return null;
}

async function enregistrer(e) {
    e.preventDefault();
    const erreur = validerClient();
    if (erreur) { afficherAlerte('alerte', erreur); return; }

    const btn = el('btn-enregistrer');
    btn.disabled = true;
    try {
        const fd = construireFormData();
        if (editId) {
            // Surcharge de méthode : POST multipart traité comme PUT côté serveur
            // (PHP ne peuple pas $_FILES pour les vraies requêtes PUT).
            fd.append('_method', 'PUT');
            await API.postForm(`/api/mes-annonces/${editId}`, fd);
        } else {
            await API.postForm('/api/mes-annonces', fd);
        }
        window.location.href = '/compte/?enregistre=1';
    } catch (err) {
        const champs = err.data && err.data.champs;
        afficherAlerte('alerte', champs ? Object.values(champs)[0] : err.message);
        btn.disabled = false;
    }
}
