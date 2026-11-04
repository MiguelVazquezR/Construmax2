/**
 * Stable identifier of the browser used by the kiosk devices.
 *
 * The kiosk stores its token in localStorage, but the browser can lose it:
 * iOS deletes script-written storage after a week without visits, "clear
 * browsing data" wipes it and a home-screen app does not share storage with
 * the browser. The server keeps this fingerprint of every registered device,
 * so the device can be recognized again without registering it twice.
 *
 * Only coarse traits are used (screen size, time zone, platform...): they
 * survive browser updates, which would change the user agent version.
 */
const BROWSER_FAMILIES = [
    [/Edg\//, 'edge'],
    [/OPR\//, 'opera'],
    [/FxiOS\//, 'firefox-ios'],
    [/CriOS\//, 'chrome-ios'],
    [/Firefox\//, 'firefox'],
    [/Chrome\//, 'chrome'],
    [/Safari\//, 'safari'],
];

const browserFamily = (userAgent) => {
    const match = BROWSER_FAMILIES.find(([pattern]) => pattern.test(userAgent));

    return match ? match[1] : 'unknown';
};

// The screen size is normalized so rotating the device keeps it stable.
const screenSize = (screen) => {
    const width = screen?.width || 0;
    const height = screen?.height || 0;

    return `${Math.min(width, height)}x${Math.max(width, height)}`;
};

let cachedKey = null;

export const readDeviceKey = () => {
    if (cachedKey) {
        return cachedKey;
    }

    try {
        const navigatorInfo = window.navigator ?? {};
        const screen = window.screen ?? {};

        cachedKey = [
            browserFamily(navigatorInfo.userAgent ?? ''),
            navigatorInfo.userAgentData?.platform || navigatorInfo.platform || '',
            navigatorInfo.language ?? '',
            screenSize(screen),
            window.devicePixelRatio ?? 1,
            screen.colorDepth ?? 0,
            Intl.DateTimeFormat().resolvedOptions().timeZone ?? '',
            navigatorInfo.hardwareConcurrency ?? '',
            navigatorInfo.maxTouchPoints ?? 0,
            navigatorInfo.deviceMemory ?? '',
        ].join('|');
    } catch {
        cachedKey = '';
    }

    return cachedKey;
};
