(function() {
    const KEY = '_atmos_attribution';
    const urlParams = new URLSearchParams(window.location.search);
    const params = {
        src: 'utm_source',
        mdm: 'utm_medium',
        cmp: 'utm_campaign',
        gclid: 'gclid',
        fbclid: 'fbclid',
        referrer: 'referrer',
    };

    const setCookie = (name, value, days) => {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax; Secure';
    };

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


    const autoFillFields = () => {
        const stored = JSON.parse(localStorage.getItem(KEY));
        if (!stored) return;

        const mapping = {};

        for (const [key, param] of Object.entries(params)) {
            if (!param) continue;

            mapping[`first_${param}`] = stored.first?.[key];
            mapping[`last_${param}`] = stored.last?.[key];
        }

        // Find all forms on the page
        const forms = document.querySelectorAll('form');
        
        forms.length && forms.forEach(form => {
            // Avoid double-processing if the form is re-rendered
            if (form.dataset.attributionInjected) return;

            for (const [fieldName, value] of Object.entries(mapping)) {
                if (!value) continue;
                appendHiddenInput(form, fieldName, value);
            }
            form.dataset.attributionInjected = "true";
        });
    }

    // 1. Capture Signals
    const rawParams = {
        src: urlParams.get(params.src),
        mdm: urlParams.get(params.mdm),
        cmp: urlParams.get(params.cmp),
        gclid: urlParams.get(params.gclid),
        fbclid: urlParams.get(params.fbclid),
        referrer: document.referrer || null,
        ts: Math.floor(Date.now() / 1000)
    };
    // Only add the key to the 'current' object if it actually has a value
    const current = {};
    for (const [key, val] of Object.entries(rawParams)) {
        if (val && val !== 'null' && val !== 'undefined') {
            current[key] = val;
        }
    }

    if (Object.keys(current).length > 0) {
        current.ts = Math.floor(Date.now() / 1000);

        let data = localStorage.getItem(KEY);
        data = data ? JSON.parse(data) : { first: null, last: null };

        if (!data.first) data.first = current;
        data.last = current;

        localStorage.setItem(KEY, JSON.stringify(data));
        setCookie(KEY, JSON.stringify(data), 365);
    }

    setTimeout(() => {
        autoFillFields();
    }, 1000);
})();