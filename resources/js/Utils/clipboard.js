/**
 * Copy text to the clipboard with a fallback for browsers where the async
 * clipboard API is unavailable, for example Safari on iOS without HTTPS.
 *
 * @returns {Promise<boolean>} whether the text was copied
 */
export const copyText = async (text) => {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Fall through to the legacy selection method.
        }
    }

    try {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '0';
        field.style.opacity = '0';

        document.body.appendChild(field);
        field.select();
        field.setSelectionRange(0, text.length);

        const copied = document.execCommand('copy');
        document.body.removeChild(field);

        return copied;
    } catch {
        return false;
    }
};
