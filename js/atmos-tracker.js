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
        gbraid: 'gbraid',
        wbraid: 'wbraid',
        msclkid: 'msclkid',
        fbclid: 'fbclid',
        referrer: 'referrer',
    };

    // Click ID url params => inferred source/medium, applied only when the URL has
    // neither utm_source nor utm_medium. First match wins. gad_source isn't stored,
    // it only marks a Google Ads click. fbclid is added to all outbound Meta links
    // (organic too), so it's 'social'; paid Meta traffic is identified by its own UTMs.
    const clickIdSources = [
        { ids: ['gclid', 'gbraid', 'wbraid', 'gad_source'], src: 'google', mdm: 'cpc' },
        { ids: ['msclkid'], src: 'bing', mdm: 'cpc' },
        { ids: ['fbclid'], src: 'meta', mdm: 'social' },
    ];

    // A referrer-only visit (e.g. organic search) within this window of a paid last
    // touch doesn't replace it, so a quick return via search keeps the ad credit.
    const PAID_PROTECTION_SECONDS = 24 * 60 * 60;

    const isPaid = (touch) =>
        /^(cpc|ppc|paid.*)$/i.test(touch?.mdm || '') ||
        ['gclid', 'gbraid', 'wbraid', 'msclkid'].some(key => touch?.[key]);

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

    // Sets the field's value, creating a hidden input if needed. With no value, removes
    // inputs this script created so stale values from an earlier touch aren't submitted;
    // fields defined in the form builder are left alone.
    const setHiddenInput = (form, name, value) => {
        let input = form.querySelector(`input[name="${name}"]`);
        if (!isValid(value)) {
            if (input && input.dataset.atmos) input.remove();
            return;
        }
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.dataset.atmos = '1';
            form.appendChild(input);
        }
        input.value = value;
    };

    const fillForm = (form, stored) => {
        // Until a second touch arrives only `last` is stored; it is also the first touch
        const first = stored.first || stored.last;
        for (const [key, param] of Object.entries(params)) {
            setHiddenInput(form, `first_${param}`, first?.[key]);
            setHiddenInput(form, param, stored.last?.[key]);
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

    if (!current.src && !current.mdm) {
        const match = clickIdSources.find(s => s.ids.some(id => isValid(urlParams.get(id))));
        if (match) {
            current.src = match.src;
            current.mdm = match.mdm;
        }
    }

    const now = Math.floor(Date.now() / 1000);
    const data = getStored() || {};
    const referrerOnly = Object.keys(current).length === 1 && current.referrer;
    const protectedPaid = referrerOnly && isPaid(data.last) && now - (data.last.ts || 0) < PAID_PROTECTION_SECONDS;

    if (Object.keys(current).length > 0 && !protectedPaid) {
        current.ts = now;

        // Only `last` is stored until a second touch arrives, then it moves to `first`
        // (once; `first` is never replaced) to keep the cookie small
        if (!data.first && data.last) data.first = data.last;
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
