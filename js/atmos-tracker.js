(function() {
    const KEY = '_atmos_attribution';
    // Stored data format version. Data without it (or older) is cleaned up by migrate().
    const VERSION = 2;
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
        lnd: 'landing',
    };

    // Keys that make a page view a touch. The landing page (lnd) is recorded with a
    // touch but never creates one: every page view has a path.
    const signalKeys = Object.keys(params).filter(key => key !== 'lnd');

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

    // Returns the URL (origin + path only) if it's an http(s) URL from another site.
    // Its query string and fragment belong to the referring site and are dropped.
    const cleanReferrer = (url) => {
        try {
            const ref = new URL(url);
            if (!/^https?:$/.test(ref.protocol)) return null;
            return stripWww(ref.hostname) === stripWww(window.location.hostname) ? null : ref.origin + ref.pathname;
        } catch (e) {
            return null;
        }
    };

    const getExternalReferrer = () => (document.referrer ? cleanReferrer(document.referrer) : null);

    const isValid = (val) => val && val !== 'null' && val !== 'undefined';

    const save = (data) => {
        try {
            localStorage.setItem(KEY, JSON.stringify(data));
        } catch (e) {
            // Storage unavailable (private mode, quota); the cookie still carries the data
        }
        setCookie(KEY, JSON.stringify(data), 365);
    };

    const clear = () => {
        try {
            localStorage.removeItem(KEY);
        } catch (e) {
            // Storage unavailable
        }
        document.cookie = KEY + '=; max-age=0; path=/';
    };

    // One-time cleanup of data saved before VERSION 2. v1.x recorded internal page views
    // as touches (own-site referrer, overwriting last and sometimes setting first) and
    // kept referrer query strings. Own-site referrers are removed, touches left without
    // a signal are dropped, and the rest is converted to the current format.
    // Returns null when nothing valid remains.
    const migrate = (data) => {
        if (!data || data.v >= VERSION) return data;

        const cleanTouch = (touch) => {
            if (!touch || typeof touch !== 'object') return null;
            const t = { ...touch };
            if (t.referrer) {
                const ref = cleanReferrer(t.referrer);
                if (ref) t.referrer = ref;
                else delete t.referrer;
            }
            return signalKeys.some(key => isValid(t[key])) ? t : null;
        };

        const first = cleanTouch(data.first);
        const last = cleanTouch(data.last);
        if (!first && !last) return null;

        // A single remaining touch (or two identical ones) is stored as `last` only
        const same = first && last && JSON.stringify(first) === JSON.stringify(last);
        if (!last || same) return { v: VERSION, last: last || first };
        return first ? { v: VERSION, first, last } : { v: VERSION, last };
    };

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

    // Stored value => form value. The landing page is stored as a path to keep the
    // cookie small and submitted as a full URL.
    const formValue = (key, val) => (key === 'lnd' && isValid(val) ? window.location.origin + val : val);

    const fillForm = (form, stored) => {
        // Until a second touch arrives only `last` is stored; it is also the first touch
        const first = stored.first || stored.last;
        for (const [key, param] of Object.entries(params)) {
            setHiddenInput(form, `first_${param}`, formValue(key, first?.[key]));
            setHiddenInput(form, param, formValue(key, stored.last?.[key]));
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
    for (const key of signalKeys) {
        const val = key === 'referrer' ? getExternalReferrer() : urlParams.get(params[key]);
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
    const stored = getStored();
    const data = migrate(stored) || {};
    const needsMigration = !!stored && data !== stored;
    const referrerOnly = Object.keys(current).length === 1 && current.referrer;
    const protectedPaid = referrerOnly && isPaid(data.last) && now - (data.last.ts || 0) < PAID_PROTECTION_SECONDS;

    if (Object.keys(current).length > 0 && !protectedPaid) {
        // Landing page path only; its query string is already captured as UTMs/click IDs
        current.lnd = window.location.pathname;
        current.ts = now;

        // Only `last` is stored until a second touch arrives, then it moves to `first`
        // (once; `first` is never replaced) to keep the cookie small
        if (!data.first && data.last) data.first = data.last;
        data.last = current;
        data.v = VERSION;
        save(data);
    } else if (needsMigration) {
        data.last ? save(data) : clear();
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
