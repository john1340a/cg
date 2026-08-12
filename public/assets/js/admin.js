/* ============================================================
   admin.js — back-office administrateur.
   Pilote 4 pages : modération, abonnés, utilisateurs, paramètres.
   Chaque page appelle sa fonction init*() au chargement.
   Contenu dynamique inséré via le DOM (aucun innerHTML de données).
   ============================================================ */

const LIBELLE = {
    brouillon: 'Brouillon',
    en_attente_paiement: 'En attente de paiement',
    en_attente_validation: 'En attente de validation',
    publie: 'Publiée',
    rejete: 'Rejetée',
};

/** Déconnexion partagée + garde admin. */
async function gardeAdmin() {
    const user = await exigerAuth('admin');
    if (!user) return null;
    const lien = el('lien-deconnexion');
    if (lien) lien.addEventListener('click', async (e) => {
        e.preventDefault();
        try { await API.post('/api/auth/logout'); } catch (_) {}
        API.resetCsrf();
        window.location.href = '/connexion.html';
    });
    return user;
}

/* ============================================================
   1. MODÉRATION
   ============================================================ */
async function initModeration() {
    if (!(await gardeAdmin())) return;
    el('f-statut').addEventListener('change', chargerModeration);
    const exp = el('btn-export');
    if (exp) exp.addEventListener('click', exporterAnnonces);
    await chargerModeration();
}

/**
 * Télécharge l'export texte de toutes les annonces.
 * Passe par fetch (cookie de session envoyé) pour gérer proprement
 * une éventuelle erreur au lieu de télécharger un JSON d'erreur.
 */
async function exporterAnnonces() {
    const btn = el('btn-export');
    if (btn) btn.disabled = true;
    try {
        const res = await fetch('/api/admin/events/export', {
            headers: { Accept: 'text/plain' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Export impossible (' + res.status + ').');

        const blob = await res.blob();
        // Nom de fichier depuis Content-Disposition, sinon repli daté.
        const cd = res.headers.get('Content-Disposition') || '';
        const m = cd.match(/filename="?([^"]+)"?/);
        const nom = m ? m[1] : 'bourses-mineraux.txt';

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nom;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch (err) {
        afficherAlerte('alerte', err.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function chargerModeration() {
    const conteneur = el('liste-moderation');
    conteneur.textContent = 'Chargement…';
    const statut = el('f-statut').value;
    try {
        const url = '/api/admin/events' + (statut ? '?statut=' + statut : '');
        const { events } = await API.get(url);
        conteneur.textContent = '';
        if (!events.length) {
            const p = document.createElement('p'); p.className = 'muet';
            p.textContent = 'Aucune annonce pour ce statut.';
            conteneur.appendChild(p); return;
        }
        events.forEach((ev) => conteneur.appendChild(carteModeration(ev)));
    } catch (err) {
        afficherAlerte('alerte', err.message);
    }
}

function carteModeration(ev) {
    const bloc = document.createElement('div');
    bloc.className = 'carte-panneau';
    bloc.style.marginBottom = '.75rem';

    const h = document.createElement('h2');
    // Titre façon revue : « 53ème Vente de pierre (ventes-échanges) »
    h.textContent = titreAvecType(ev);
    bloc.appendChild(h);

    const badge = document.createElement('span');
    badge.className = 'badge ' + ev.statut;
    badge.textContent = LIBELLE[ev.statut] || ev.statut;
    bloc.appendChild(badge);

    // En-tête « 17-19 juillet 2026, Millau (12) »
    const entete = document.createElement('p');
    entete.className = 'muet';
    entete.style.marginTop = '.5rem';
    entete.textContent = enteteDatesLieu(ev);
    bloc.appendChild(entete);

    // Adresse complète (salle / lieu)
    const meta = document.createElement('p');
    meta.className = 'muet';
    meta.textContent = ev.adresse;
    bloc.appendChild(meta);

    const org = document.createElement('p');
    org.className = 'muet';
    org.textContent = `Organisateur : ${ev.owner_prenom} ${ev.owner_nom} (${ev.owner_email})`
        + ` — contact public : ${ev.contact_email}`;
    bloc.appendChild(org);

    if (ev.est_gratuite) {
        const g = document.createElement('span'); g.className = 'badge publie';
        g.textContent = '1re annonce gratuite (abonné)'; bloc.appendChild(g);
    }

    const actions = document.createElement('div');
    actions.className = 'espace';
    actions.style.display = 'flex'; actions.style.gap = '.5rem'; actions.style.flexWrap = 'wrap';

    if (ev.statut === 'en_attente_paiement') {
        actions.appendChild(btn('Marquer paiement reçu', 'btn', () => paiementRecu(ev.id)));
    }
    if (ev.statut === 'en_attente_validation') {
        actions.appendChild(btn('Valider et publier', 'btn', () => valider(ev.id)));
    }
    if (['en_attente_validation', 'en_attente_paiement', 'publie'].includes(ev.statut)) {
        actions.appendChild(btn('Rejeter', 'btn danger petit', () => rejeter(ev.id)));
    }
    bloc.appendChild(actions);
    return bloc;
}

async function paiementRecu(id) {
    try {
        const r = await API.post(`/api/admin/events/${id}/paiement-recu`, {});
        afficherAlerte('alerte', r.message, 'succes');
        await chargerModeration();
    } catch (err) { afficherAlerte('alerte', err.message); }
}
async function valider(id) {
    try {
        const r = await API.post(`/api/admin/events/${id}/valider`, {});
        afficherAlerte('alerte', r.message, 'succes');
        await chargerModeration();
    } catch (err) { afficherAlerte('alerte', err.message); }
}
async function rejeter(id) {
    const motif = prompt('Motif du rejet (transmis à l\'organisateur) :');
    if (motif === null) return;
    if (!motif.trim()) { afficherAlerte('alerte', 'Le motif est obligatoire.'); return; }
    try {
        const r = await API.post(`/api/admin/events/${id}/rejeter`, { motif: motif.trim() });
        afficherAlerte('alerte', r.message, 'succes');
        await chargerModeration();
    } catch (err) { afficherAlerte('alerte', err.message); }
}

/* ============================================================
   2. ABONNÉS
   ============================================================ */
async function initAbonnes() {
    if (!(await gardeAdmin())) return;

    el('form-import').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = el('fichier').files[0];
        if (!f) { afficherAlerte('alerte', 'Choisissez un fichier CSV.'); return; }
        const fd = new FormData();
        fd.append('fichier', f);
        try {
            const r = await API.postForm('/api/admin/subscribers/import', fd);
            afficherAlerte('alerte', r.message, 'succes');
            el('fichier').value = '';
            await chargerAbonnes();
        } catch (err) { afficherAlerte('alerte', err.message); }
    });

    el('form-ajout').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await API.post('/api/admin/subscribers', { email: el('email-ajout').value.trim() });
            el('email-ajout').value = '';
            afficherAlerte('alerte', 'Abonné ajouté.', 'succes');
            await chargerAbonnes();
        } catch (err) { afficherAlerte('alerte', err.message); }
    });

    await chargerAbonnes();
}

async function chargerAbonnes() {
    const conteneur = el('liste-abonnes');
    try {
        const { subscribers } = await API.get('/api/admin/subscribers');
        el('compteur-abo').textContent = `(${subscribers.length})`;
        conteneur.textContent = '';
        if (!subscribers.length) {
            const p = document.createElement('p'); p.className = 'muet';
            p.textContent = 'Aucun abonné.'; conteneur.appendChild(p); return;
        }
        const ul = document.createElement('div');
        subscribers.forEach((s) => {
            const ligne = document.createElement('div');
            ligne.style.display = 'flex'; ligne.style.justifyContent = 'space-between';
            ligne.style.alignItems = 'center'; ligne.style.padding = '.4rem 0';
            ligne.style.borderBottom = '1px solid var(--gris-bord)';
            const span = document.createElement('span'); span.textContent = s.email;
            ligne.appendChild(span);
            ligne.appendChild(btn('Retirer', 'btn danger petit', () => retirerAbonne(s.id, s.email)));
            ul.appendChild(ligne);
        });
        conteneur.appendChild(ul);
    } catch (err) { afficherAlerte('alerte', err.message); }
}

async function retirerAbonne(id, email) {
    if (!confirm(`Retirer ${email} de la liste des abonnés ?`)) return;
    try {
        await API.del(`/api/admin/subscribers/${id}`);
        afficherAlerte('alerte', 'Abonné retiré.', 'succes');
        await chargerAbonnes();
    } catch (err) { afficherAlerte('alerte', err.message); }
}

/* ============================================================
   3. UTILISATEURS
   ============================================================ */
async function initUtilisateurs() {
    const admin = await gardeAdmin();
    if (!admin) return;
    await chargerUtilisateurs(admin.id);
}

async function chargerUtilisateurs(adminId) {
    const conteneur = el('liste-users');
    try {
        const { users } = await API.get('/api/admin/users');
        conteneur.textContent = '';
        const table = document.createElement('table');
        const thead = document.createElement('thead');
        thead.appendChild(rangeeTh(['Nom', 'Email', 'Rôle', 'Abonné',
            'Gratuité illimitée', 'Actif', 'Actions']));
        table.appendChild(thead);
        const tbody = document.createElement('tbody');
        users.forEach((u) => {
            const tr = document.createElement('tr');
            tr.appendChild(td(`${u.prenom} ${u.nom}`));
            tr.appendChild(td(u.email));
            tr.appendChild(td(u.role));
            tr.appendChild(td(u.est_abonne ? 'Oui' : '—'));
            tr.appendChild(td(u.paiement_exempte ? 'Oui' : '—'));
            tr.appendChild(td(u.est_actif ? 'Oui' : 'Désactivé'));

            const tdAction = document.createElement('td');
            tdAction.style.display = 'flex';
            tdAction.style.gap = '.4rem';
            tdAction.style.flexWrap = 'wrap';

            // Exemption de paiement (toutes annonces gratuites)
            const exempte = !!u.paiement_exempte;
            tdAction.appendChild(btn(
                exempte ? 'Retirer gratuité' : 'Gratuité illimitée',
                exempte ? 'btn secondaire petit' : 'btn petit',
                () => basculerExemption(u.id, !exempte, adminId)));

            if (u.id !== adminId) {
                const actif = !!u.est_actif;
                tdAction.appendChild(btn(actif ? 'Désactiver' : 'Réactiver',
                    actif ? 'btn danger petit' : 'btn secondaire petit',
                    () => basculerUser(u.id, !actif, adminId)));
            } else {
                tdAction.appendChild(td('(vous)'));
            }
            tr.appendChild(tdAction);
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        conteneur.appendChild(table);
    } catch (err) { afficherAlerte('alerte', err.message); }
}

async function basculerUser(id, actif, adminId) {
    try {
        await API.post(`/api/admin/users/${id}/desactiver`, { actif });
        afficherAlerte('alerte', 'Utilisateur mis à jour.', 'succes');
        await chargerUtilisateurs(adminId);
    } catch (err) { afficherAlerte('alerte', err.message); }
}

async function basculerExemption(id, exempte, adminId) {
    try {
        await API.post(`/api/admin/users/${id}/exemption`, { exempte });
        afficherAlerte('alerte', exempte
            ? 'Gratuité illimitée activée : cet organisateur ne paiera plus ses annonces.'
            : 'Gratuité illimitée retirée.', 'succes');
        await chargerUtilisateurs(adminId);
    } catch (err) { afficherAlerte('alerte', err.message); }
}

/* ============================================================
   4. PARAMÈTRES
   ============================================================ */
async function initParametres() {
    if (!(await gardeAdmin())) return;
    try {
        const { settings } = await API.get('/api/admin/settings');
        el('p-instructions').value = settings.instructions_paiement || '';
        el('p-montant').value = settings.montant_annonce || '';
        el('p-expediteur').value = settings.email_expediteur || '';
        el('p-nom-expediteur').value = settings.nom_expediteur || '';
        el('p-iframe').value = settings.iframe_domain || '';
        el('p-lien-paiement').value = settings.lien_paiement || '';
    } catch (err) { afficherAlerte('alerte', err.message); }

    el('form-params').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await API.put('/api/admin/settings', {
                instructions_paiement: el('p-instructions').value,
                montant_annonce: el('p-montant').value.trim(),
                email_expediteur: el('p-expediteur').value.trim(),
                nom_expediteur: el('p-nom-expediteur').value.trim(),
                iframe_domain: el('p-iframe').value.trim(),
                lien_paiement: el('p-lien-paiement').value.trim(),
            });
            afficherAlerte('alerte', 'Paramètres enregistrés.', 'succes');
        } catch (err) { afficherAlerte('alerte', err.message); }
    });
}

/* ---------- Helpers DOM ---------- */
function btn(texte, classes, onClick) {
    const b = document.createElement('button');
    b.className = classes; b.textContent = texte;
    b.addEventListener('click', onClick);
    return b;
}
function td(texte) { const t = document.createElement('td'); t.textContent = texte; return t; }
function rangeeTh(labels) {
    const tr = document.createElement('tr');
    labels.forEach((l) => { const th = document.createElement('th'); th.textContent = l; tr.appendChild(th); });
    return tr;
}
