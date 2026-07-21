/* ============================================================
   compte.js — tableau de bord de l'organisateur.
   Liste des annonces, statuts, actions (soumettre, modifier,
   supprimer). Le contenu dynamique est inséré via textContent
   pour éviter toute injection (XSS).
   ============================================================ */

const LIBELLE_STATUT = {
    brouillon: 'Brouillon',
    en_attente_paiement: 'En attente de paiement',
    en_attente_validation: 'En attente de validation',
    publie: 'Publiée',
    rejete: 'Rejetée',
};

document.addEventListener('DOMContentLoaded', async () => {
    const user = await exigerAuth();
    if (!user) return;

    el('bienvenue').textContent = `Bonjour ${user.prenom}. `
        + (user.est_abonne ? 'Vous êtes abonné : votre première annonce est gratuite.'
                           : 'Chaque annonce coûte 10 € (paiement hors ligne).');

    // Un admin qui atterrit ici garde un accès direct au back-office.
    if (user.role === 'admin') {
        const nav = document.querySelector('.entete nav');
        if (nav && !document.getElementById('lien-admin')) {
            const a = document.createElement('a');
            a.id = 'lien-admin';
            a.href = '/admin/';
            a.textContent = 'Administration';
            nav.insertBefore(a, nav.firstChild);
        }
    }

    el('lien-deconnexion').addEventListener('click', async (e) => {
        e.preventDefault();
        try { await API.post('/api/auth/logout'); } catch (_) {}
        API.resetCsrf();
        window.location.href = '/connexion.html';
    });

    await chargerAnnonces();
});

async function chargerAnnonces() {
    const conteneur = el('liste-annonces');
    try {
        const { events } = await API.get('/api/mes-annonces');
        conteneur.textContent = '';

        if (!events.length) {
            const p = document.createElement('p');
            p.className = 'muet';
            p.textContent = "Vous n'avez pas encore d'annonce.";
            conteneur.appendChild(p);
            return;
        }

        events.forEach((ev) => conteneur.appendChild(carteAnnonce(ev)));
    } catch (err) {
        afficherAlerte('alerte', err.message);
    }
}

/** Construit le bloc d'une annonce (DOM sûr, sans innerHTML). */
function carteAnnonce(ev) {
    const bloc = document.createElement('div');
    bloc.className = 'carte-panneau';
    bloc.style.marginBottom = '.75rem';

    const titre = document.createElement('h2');
    titre.textContent = ev.intitule;
    if (ev.edition_num) {
        const ed = document.createElement('span');
        ed.className = 'muet';
        ed.style.fontWeight = '400';
        ed.textContent = `  (n°${ev.edition_num})`;
        titre.appendChild(ed);
    }
    bloc.appendChild(titre);

    const badge = document.createElement('span');
    badge.className = 'badge ' + ev.statut;
    badge.textContent = LIBELLE_STATUT[ev.statut] || ev.statut;
    bloc.appendChild(badge);

    const infos = document.createElement('p');
    infos.className = 'muet';
    infos.style.marginTop = '.5rem';
    infos.textContent = `Du ${ev.date_debut} au ${ev.date_fin} — ${ev.adresse}`;
    bloc.appendChild(infos);

    // Aperçu de l'affiche uploadée (visible dès le brouillon, avant publication)
    if (ev.affiche_path) {
        const img = document.createElement('img');
        img.src = '/api/affiche/' + encodeURIComponent(ev.affiche_path);
        img.alt = 'Affiche : ' + ev.intitule;
        img.loading = 'lazy';
        img.style.cssText = 'max-width:160px;max-height:200px;border-radius:6px;'
            + 'border:1px solid var(--gris-bord);margin:.5rem 0;display:block;';
        bloc.appendChild(img);
    }

    // Rappel : une annonce non publiée n'apparaît pas sur la carte publique.
    if (ev.statut === 'brouillon') {
        const info = document.createElement('p');
        info.className = 'alerte info';
        info.textContent = 'Cette annonce est un brouillon : elle n\'apparaît pas encore sur '
            + 'la carte. Cliquez sur « Soumettre » pour lancer sa publication.';
        bloc.appendChild(info);
    }
    if (ev.statut === 'en_attente_validation') {
        const info = document.createElement('p');
        info.className = 'alerte info';
        info.textContent = 'Annonce soumise : en attente de validation par l\'équipe. '
            + 'Elle apparaîtra sur la carte une fois validée.';
        bloc.appendChild(info);
    }

    if (ev.statut === 'rejete' && ev.motif_rejet) {
        const motif = document.createElement('p');
        motif.className = 'alerte erreur';
        motif.textContent = 'Motif du refus : ' + ev.motif_rejet;
        bloc.appendChild(motif);
    }
    if (ev.statut === 'en_attente_paiement') {
        const info = document.createElement('p');
        info.className = 'alerte info';
        info.textContent = 'Un email d\'instructions de paiement vous a été envoyé. '
            + 'L\'annonce sera publiée après réception du paiement et validation.';
        bloc.appendChild(info);
    }

    // Actions
    const actions = document.createElement('div');
    actions.className = 'espace';
    actions.style.display = 'flex';
    actions.style.gap = '.5rem';
    actions.style.flexWrap = 'wrap';

    const modifiable = ['brouillon', 'rejete', 'en_attente_paiement'].includes(ev.statut);
    const soumettable = ['brouillon', 'rejete'].includes(ev.statut);

    if (soumettable) {
        actions.appendChild(bouton('Soumettre', 'btn', () => soumettre(ev.id)));
    }
    if (modifiable) {
        const a = document.createElement('a');
        a.className = 'btn secondaire petit';
        a.href = '/compte/annonce.html?id=' + ev.id;
        a.textContent = 'Modifier';
        actions.appendChild(a);
    }
    actions.appendChild(bouton('Supprimer', 'btn danger petit', () => supprimer(ev.id, ev.intitule)));

    bloc.appendChild(actions);
    return bloc;
}

function bouton(texte, classes, onClick) {
    const b = document.createElement('button');
    b.className = classes;
    b.textContent = texte;
    b.addEventListener('click', onClick);
    return b;
}

async function soumettre(id) {
    try {
        const r = await API.post(`/api/mes-annonces/${id}/soumettre`);
        afficherAlerte('alerte', r.message, 'succes');
        await chargerAnnonces();
    } catch (err) {
        afficherAlerte('alerte', err.message);
    }
}

async function supprimer(id, intitule) {
    if (!confirm(`Supprimer l'annonce « ${intitule} » ? Cette action est irréversible.`)) return;
    try {
        await API.del(`/api/mes-annonces/${id}`);
        afficherAlerte('alerte', 'Annonce supprimée.', 'succes');
        await chargerAnnonces();
    } catch (err) {
        afficherAlerte('alerte', err.message);
    }
}
