/* ============================================================
   format.js — formatage partagé des annonces (popup carte,
   file de modération, tableau de bord). Un seul point de vérité
   pour : titre « Nème Intitulé », dates lisibles, « Ville (dépt) ».
   Chargé avant carte.js / admin.js / compte.js.
   ============================================================ */

const MOIS_FR = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/**
 * Suffixe ordinal français d'une édition.
 *  1 -> « 1er », 2 -> « 2ème », 53 -> « 53ème ».
 * Si edition_num n'est pas purement numérique (texte libre déjà
 * formaté, ex. « 12bis »), on le renvoie tel quel.
 * @param {string|number|null} edition
 * @returns {string} ex. « 53ème » ou '' si absent
 */
function ordinalEdition(edition) {
    if (edition === null || edition === undefined) return '';
    const s = String(edition).trim();
    if (s === '') return '';
    if (!/^\d+$/.test(s)) return s;            // texte libre : ne pas toucher
    const n = parseInt(s, 10);
    return n === 1 ? '1er' : `${n}ème`;
}

/**
 * Titre d'affichage d'une annonce :
 *   « 53ème Vente de pierre »  (édition en préfixe)
 *   « Vente de pierre »        (sans édition)
 * @param {{intitule:string, edition_num?:string|number|null}} ev
 * @returns {string}
 */
function titreAnnonce(ev) {
    const ord = ordinalEdition(ev.edition_num);
    const intitule = (ev.intitule || '').trim();
    return ord ? `${ord} ${intitule}` : intitule;
}

/**
 * Type d'événement en clair, entre parenthèses façon « (ventes-échanges) ».
 * @param {{type_vente?:boolean, type_echanges?:boolean}} ev
 * @returns {string} ex. « (ventes-échanges) » ou '' si aucun.
 */
function typeAnnonce(ev) {
    const t = [];
    if (ev.type_vente) t.push('ventes');
    if (ev.type_echanges) t.push('échanges');
    return t.length ? `(${t.join('-')})` : '';
}

/**
 * Titre complet façon revue : « 53ème Vente de pierre (ventes-échanges) ».
 */
function titreAvecType(ev) {
    const type = typeAnnonce(ev);
    return type ? `${titreAnnonce(ev)} ${type}` : titreAnnonce(ev);
}

/**
 * Plage de dates lisible :
 *   même jour        -> « 17 juillet 2026 »
 *   même mois/année  -> « 17-19 juillet 2026 »
 *   sinon            -> « 30 juillet - 2 août 2026 »
 * Entrées au format ISO « AAAA-MM-JJ ».
 * @param {string} debut
 * @param {string} fin
 * @returns {string}
 */
function formatDates(debut, fin) {
    const p = (d) => {
        const [a, m, j] = String(d).split('-').map(Number);
        return { a, m, j };
    };
    const d = p(debut), f = p(fin);
    if (!d.a || !f.a) return `${debut} → ${fin}`;

    if (d.a === f.a && d.m === f.m && d.j === f.j) {
        return `${d.j} ${MOIS_FR[d.m]} ${d.a}`;
    }
    if (d.a === f.a && d.m === f.m) {
        return `${d.j}-${f.j} ${MOIS_FR[d.m]} ${d.a}`;
    }
    if (d.a === f.a) {
        return `${d.j} ${MOIS_FR[d.m]} - ${f.j} ${MOIS_FR[f.m]} ${d.a}`;
    }
    return `${d.j} ${MOIS_FR[d.m]} ${d.a} - ${f.j} ${MOIS_FR[f.m]} ${f.a}`;
}

/**
 * Déduit « Ville (dépt) » à partir d'une adresse française libre.
 * Cherche un code postal (5 chiffres) et prend le texte qui suit
 * comme ville ; le département = 2 premiers chiffres (avec cas Corse
 * 2A/2B pour 20xxx et DOM 97x/98x sur 3 chiffres).
 * Si aucun code postal n'est trouvé, renvoie l'adresse d'origine.
 * @param {string} adresse
 * @returns {string} ex. « Millau (12) » ou l'adresse brute en repli.
 */
function villeDepartement(adresse) {
    const a = (adresse || '').trim();
    // Code postal + ville : « … 12100 Millau » (ville = mots après le CP)
    const m = a.match(/\b(\d{5})\b[,\s]*([^,]*)$/);
    if (!m) return a;

    const cp = m[1];
    let ville = (m[2] || '').trim().replace(/^[-–\s]+/, '');
    // Si rien après le CP, tenter le token juste avant le CP
    if (ville === '') {
        const avant = a.slice(0, m.index).trim().split(',').pop();
        ville = (avant || '').trim();
    }

    return ville ? `${ville} (${departement(cp)})` : `(${departement(cp)})`;
}

/**
 * Numéro de département à partir d'un code postal (métropole + Corse + DOM).
 * @param {string} cp  code postal à 5 chiffres
 * @returns {string}
 */
function departement(cp) {
    if (/^20/.test(cp)) {
        // Corse : 20000–20190 = 2A (Corse-du-Sud), au-delà = 2B (Haute-Corse)
        return parseInt(cp, 10) <= 20190 ? '2A' : '2B';
    }
    if (/^9[78]/.test(cp)) return cp.slice(0, 3);   // DOM/COM : 971…, 987…
    return cp.slice(0, 2);
}

/**
 * Ligne d'en-tête « Dates, Ville (dépt) ».
 * @param {{date_debut:string,date_fin:string,adresse:string}} ev
 * @returns {string}
 */
function enteteDatesLieu(ev) {
    return `${formatDates(ev.date_debut, ev.date_fin)}, ${villeDepartement(ev.adresse)}`;
}

/**
 * Partie « lieu » de l'adresse (salle, rue, lieu-dit) SANS le code postal
 * ni la ville, déjà portés par l'en-tête « Ville (dépt) ». Sert à éviter
 * d'afficher deux fois l'adresse dans la popup.
 * Renvoie '' si l'adresse n'a pas de code postal (dans ce cas l'en-tête
 * contient déjà l'adresse entière) ou si rien ne précède le code postal.
 * @param {string} adresse
 * @returns {string}
 */
function lieuSansVille(adresse) {
    const a = (adresse || '').trim();
    const m = a.match(/\b\d{5}\b/);
    if (!m) return '';                         // pas de CP : tout est déjà dans l'en-tête
    return a.slice(0, m.index).replace(/[,\s]+$/, '').trim();
}
