/**
 * Device token storage shared by the kiosk, the public registration page and
 * the devices module.
 *
 * iOS does not always keep the token available: Safari can block web storage
 * (private mode or "block all cookies"), and the in-app browser of an app does
 * not share storage with Safari or with the home-screen app. Every access is
 * therefore guarded and the registration page also hands the token to the
 * kiosk through the link (?device_token=...), which works on any browser.
 */
const TOKEN_KEY = 'attendance_device_token';
const TOKEN_QUERY_PARAM = 'device_token';

let memoryToken = null;

export const readDeviceToken = () => {
    if (memoryToken) {
        return memoryToken;
    }

    try {
        memoryToken = window.localStorage.getItem(TOKEN_KEY);
    } catch {
        // Storage blocked: keep working with the in-memory token only.
        memoryToken = null;
    }

    return memoryToken;
};

export const saveDeviceToken = (token) => {
    memoryToken = token;

    try {
        window.localStorage.setItem(TOKEN_KEY, token);
    } catch {
        // Storage blocked: the token still lives in memory for this page.
    }
};

export const forgetDeviceToken = () => {
    memoryToken = null;

    try {
        window.localStorage.removeItem(TOKEN_KEY);
    } catch {
        // Nothing to clear.
    }
};

/**
 * Remove the token from the address bar so it does not stay in the browser
 * history. Inertia restores the original URL while it boots, so the cleanup is
 * also scheduled for the load event.
 */
const cleanTokenFromUrl = () => {
    const url = new URL(window.location.href);

    if (!url.searchParams.has(TOKEN_QUERY_PARAM)) {
        return;
    }

    url.searchParams.delete(TOKEN_QUERY_PARAM);
    window.history.replaceState(window.history.state, '', url.toString());
};

/**
 * Read the token sent by the registration page.
 */
export const readDeviceTokenFromUrl = () => {
    const token = new URL(window.location.href).searchParams.get(TOKEN_QUERY_PARAM);

    if (!token) {
        return null;
    }

    // Inertia restores the original URL while it boots, so the cleanup runs
    // right away and again once the router has settled.
    cleanTokenFromUrl();
    window.setTimeout(cleanTokenFromUrl, 500);

    return token;
};

/**
 * Add the token to a kiosk link so another browsing context can open the
 * kiosk already authorized.
 */
export const kioskUrlWithToken = (kioskUrl, token) => {
    const url = new URL(kioskUrl, window.location.origin);

    if (token) {
        url.searchParams.set(TOKEN_QUERY_PARAM, token);
    }

    return url.toString();
};
