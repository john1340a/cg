/* ============================================================
   api.js — petit client HTTP partagé.
   Gère le jeton CSRF (récupéré une fois puis mémorisé) et
   uniformise les appels JSON / multipart vers l'API.
   ============================================================ */

const API = (() => {
    let csrfToken = null;

    /** Récupère (et met en cache) le jeton CSRF courant. */
    async function csrf() {
        if (csrfToken) return csrfToken;
        const res = await fetch('/api/csrf', { credentials: 'same-origin' });
        const data = await res.json();
        csrfToken = data.token;
        return csrfToken;
    }

    /** Force le renouvellement du jeton (après logout par ex.). */
    function resetCsrf() { csrfToken = null; }

    /** Requête GET JSON. */
    async function get(url) {
        const res = await fetch(url, { credentials: 'same-origin' });
        return handle(res);
    }

    /** Requête JSON (POST/PUT/DELETE) avec jeton CSRF. */
    async function send(method, url, body) {
        const token = await csrf();
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token,
            },
            body: body != null ? JSON.stringify(body) : undefined,
        });
        return handle(res);
    }

    /** Envoi multipart (uploads) avec jeton CSRF. */
    async function sendForm(method, url, formData) {
        const token = await csrf();
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': token },
            body: formData,
        });
        return handle(res);
    }

    /** Normalise la réponse : rejette avec {status, data} si erreur. */
    async function handle(res) {
        let data = null;
        try { data = await res.json(); } catch (_) { /* pas de corps JSON */ }
        if (!res.ok) {
            const err = new Error((data && data.error) || 'Erreur réseau');
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    return {
        csrf, resetCsrf, get,
        post: (u, b) => send('POST', u, b),
        put:  (u, b) => send('PUT', u, b),
        del:  (u)    => send('DELETE', u),
        postForm: (u, f) => sendForm('POST', u, f),
        putForm:  (u, f) => sendForm('PUT', u, f),
    };
})();

/* --- Petits utilitaires DOM partagés --- */
function el(id) { return document.getElementById(id); }

function afficherAlerte(conteneurId, message, type = 'erreur') {
    const c = el(conteneurId);
    if (!c) return;
    c.innerHTML = `<div class="alerte ${type}">${escapeHtml(message)}</div>`;
    c.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

/** Redirige vers la connexion si non authentifié ; renvoie l'utilisateur sinon. */
async function exigerAuth(role = null) {
    try {
        const me = await API.get('/api/auth/me');
        if (!me.authenticated) { window.location.href = '/connexion.html'; return null; }
        if (role && me.user.role !== role) { window.location.href = '/'; return null; }
        return me.user;
    } catch (_) {
        window.location.href = '/connexion.html';
        return null;
    }
}
