(function() {
    const KEY = '_atmos_attribution';
    const urlParams = new URLSearchParams(window.location.search);

    // Storage key => url param name. Form fields: last touch uses the plain
    // param name (utm_source), first touch is prefixed (first_utm_source).
    // Must match atmos_get_field_name() in plugin.php.
    const params = {
        src: 'utm_source',
        mdm: 'utm_medium',
        cmp: 'utm_campaign',
        trm: 'utm_term',
        cnt: 'utm_content',
        gclid: 'gclid',
        fbclid: 'fbclid',
        referrer: 'referrer',
    };

    const setCookie = (name, value, days) => {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax' + secure;
    };

    const getCookie = (name) => {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    };

    // localStorage first, cookie as fallback
    const getStored = () => {
        for (const read of [() => localStorage.getItem(KEY), () => getCookie(KEY)]) {
            try {
                const data = JSON.parse(read());
                if (data) return data;
            } catch (e) {
                // Unavailable or malformed; try the next source
            }
        }
        return null;
    };

    const stripWww = (host) => host.replace(/^www\./i, '');

    // Returns the referrer (origin + path only) if it comes from another site.
    // Its query string and fragment belong to the referring site and are dropped.
    const getExternalReferrer = () => {
        if (!document.referrer) return null;
        try {
            const ref = new URL(document.referrer);
            if (!/^https?:$/.test(ref.protocol)) return null;
            return stripWww(ref.hostname) === stripWww(window.location.hostname) ? null : ref.origin + ref.pathname;
        } catch (e) {
            return null;
        }
    };

    const isValid = (val) => val && val !== 'null' && val !== 'undefined';

    const appendHiddenInput = (form, name, value) => {
        let input = form.querySelector(`input[name="${name}"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    };

    const fillForm = (form, stored) => {
        for (const [key, param] of Object.entries(params)) {
            const first = stored.first?.[key];
            const last = stored.last?.[key];
            if (isValid(first)) appendHiddenInput(form, `first_${param}`, first);
            if (isValid(last)) appendHiddenInput(form, param, last);
        }
    };

    const fillAllForms = () => {
        const stored = getStored();
        if (!stored) return;
        document.querySelectorAll('form').forEach(form => fillForm(form, stored));
    };

    // 1. Capture signals. Internal navigation (same-site referrer, no campaign params)
    // is not a new touch, so it never overwrites stored attribution.
    const current = {};
    for (const [key, param] of Object.entries(params)) {
        const val = key === 'referrer' ? getExternalReferrer() : urlParams.get(param);
        if (isValid(val)) current[key] = val;
    }

    if (Object.keys(current).length > 0) {
        current.ts = Math.floor(Date.now() / 1000);

        const data = getStored() || { first: null, last: null };
        if (!data.first) data.first = current;
        data.last = current;

        try {
            localStorage.setItem(KEY, JSON.stringify(data));
        } catch (e) {
            // Storage unavailable (private mode, quota); the cookie still carries the data
        }
        setCookie(KEY, JSON.stringify(data), 365);
    }

    // 2. Fill forms present on load, and again at submit time so forms rendered
    // later (popups, AJAX) are covered. Capture phase runs before form plugins'
    // own submit handlers serialize the data.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fillAllForms);
    } else {
        fillAllForms();
    }

    document.addEventListener('submit', (e) => {
        const stored = getStored();
        if (stored && e.target instanceof HTMLFormElement) fillForm(e.target, stored);
    }, true);
})();
