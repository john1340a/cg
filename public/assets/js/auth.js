/* ============================================================
   auth.js — logique des pages connexion / inscription / oubli.
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
    const blocs = {
        connexion:   el('bloc-connexion'),
        inscription: el('bloc-inscription'),
        oubli:       el('bloc-oubli'),
    };
    const montrer = (nom) => {
        Object.entries(blocs).forEach(([k, b]) => b.classList.toggle('masque', k !== nom));
        el('alerte').innerHTML = '';
    };

    // Navigation entre blocs
    el('lien-inscription')?.addEventListener('click', (e) => { e.preventDefault(); montrer('inscription'); });
    el('lien-oubli')?.addEventListener('click', (e) => { e.preventDefault(); montrer('oubli'); });
    el('lien-retour-connexion')?.addEventListener('click', (e) => { e.preventDefault(); montrer('connexion'); });
    el('lien-retour-connexion2')?.addEventListener('click', (e) => { e.preventDefault(); montrer('connexion'); });

    // --- Connexion ---
    el('form-connexion').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await API.post('/api/auth/login', {
                email: el('c-email').value.trim(),
                password: el('c-mdp').value,
            });
            window.location.href = '/compte/';
        } catch (err) {
            afficherAlerte('alerte', err.message);
        }
    });

    // --- Inscription ---
    el('form-inscription').addEventListener('submit', async (e) => {
        e.preventDefault();
        const mdp = el('i-mdp').value;
        if (mdp.length < 8) { afficherAlerte('alerte', 'Le mot de passe doit contenir au moins 8 caractères.'); return; }
        try {
            const r = await API.post('/api/auth/register', {
                nom: el('i-nom').value.trim(),
                prenom: el('i-prenom').value.trim(),
                email: el('i-email').value.trim(),
                password: mdp,
            });
            afficherAlerte('alerte', r.message || 'Compte créé.', 'succes');
            setTimeout(() => { window.location.href = '/compte/'; }, 900);
        } catch (err) {
            const champs = err.data && err.data.champs;
            afficherAlerte('alerte', champs ? Object.values(champs)[0] : err.message);
        }
    });

    // --- Mot de passe oublié ---
    el('form-oubli').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const r = await API.post('/api/auth/forgot', { email: el('o-email').value.trim() });
            afficherAlerte('alerte', r.message, 'info');
        } catch (err) {
            afficherAlerte('alerte', err.message);
        }
    });
});
