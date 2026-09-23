// Shared helper for talking to the backend API and keeping each page's
// navbar/auth state in sync with the real logged-in user.

const API_ROOT = new URL('../backend/api/', document.baseURI).toString();

async function apiFetch(path, options = {}) {
    const res = await fetch(API_ROOT + path, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options,
    });

    let data = null;
    try {
        data = await res.json();
    } catch (e) {
        // no JSON body
    }

    if (!res.ok) {
        const message = (data && data.error) || `Request failed (${res.status})`;
        const err = new Error(message);
        err.status = res.status;
        err.fields = data && data.fields;
        throw err;
    }

    return data;
}

async function getCurrentUser() {
    try {
        const data = await apiFetch('me.php');
        return data.user;
    } catch (e) {
        return null;
    }
}

async function logoutUser() {
    try {
        await apiFetch('logout.php', { method: 'POST' });
    } catch (e) {
        // ignore — we're logging out either way
    }
}

// Fills in the "User / Admin" style badge with the real username, and wires
// up any element with [data-logout] to actually log the user out.
function renderUserBadge(user) {
    document.querySelectorAll('[data-user-name]').forEach((el) => {
        el.textContent = user.username;
    });
    document.querySelectorAll('[data-user-initial]').forEach((el) => {
        el.textContent = user.username.charAt(0).toUpperCase();
    });
    document.querySelectorAll('[data-logout]').forEach((el) => {
        el.addEventListener('click', async (e) => {
            e.preventDefault();
            await logoutUser();
            window.location.href = 'home.html';
        });
    });
}

// Call on a page meant for logged-in users only. Redirects to
// loggedOutPage if nobody is signed in. Returns the user when signed in.
async function guardLoggedIn(loggedOutPage) {
    const user = await getCurrentUser();
    if (!user) {
        window.location.href = loggedOutPage;
        return null;
    }
    renderUserBadge(user);
    return user;
}

// Call on a page meant for signed-out visitors. Redirects to
// loggedInPage if someone is already signed in.
async function guardLoggedOut(loggedInPage) {
    const user = await getCurrentUser();
    if (user) {
        window.location.href = loggedInPage;
    }
    return user;
}
