# Atmos Cookie Tracker

A WordPress plugin that captures first-touch and last-touch attribution data from UTM parameters, ad IDs, and referrer information, storing them in browser localStorage and cookies for later form submission tracking.

## Description

Atmos Cookie Tracker automatically tracks visitor attribution data across your WordPress site and seamlessly integrates with Fluent Forms to capture marketing attribution alongside form submissions. This enables you to understand which marketing channels and campaigns are driving conversions.

## Features

- **Automatic Attribution Tracking**: Captures UTM parameters, Google Click IDs (gclid), Facebook Click IDs (fbclid), and referrer information
- **First & Last Touch Attribution**: Tracks both the initial visitor source and the most recent interaction
- **Client-Side Storage**: Stores data in localStorage and secure cookies to persist across page views
- **Cache-Friendly**: Uses JavaScript to populate form fields, avoiding cached server-side values
- **Fluent Forms Integration**: Automatically captures attribution data with form submissions
- **No Configuration Required**: Works out of the box once activated

## Tracked Parameters

The plugin tracks the following attribution data:

- `utm_source` - Marketing source (e.g., "google", "facebook", "newsletter")
- `utm_medium` - Marketing medium (e.g., "cpc", "email", "social")
- `utm_campaign` - Campaign name (e.g., "summer-sale", "product-launch")
- `utm_term` - Paid search keyword (optional)
- `utm_content` - Ad/creative variant (optional)
- `gclid` - Google Click ID for Google Ads tracking
- `fbclid` - Facebook Click ID for Facebook Ads tracking
- `referrer` - Referring URL, external sites only, reduced to origin + path (the referring site's query string and fragment are dropped)

Both **first-touch** (initial visit) and **last-touch** (most recent visit) values are stored. Only parameters that are present and non-empty are stored; missing ones are omitted rather than saved as empty values.

### What counts as a touch

A page view updates attribution only when it carries a new signal: a UTM parameter, a click ID, or a referrer from another site. Navigating between pages on your own site (including `www.` vs. bare domain) never overwrites stored attribution.

## Installation

1. Upload the `atmos-cookie-tracker` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin starts tracking immediately - no configuration needed

## How It Works

### JavaScript Tracking

When a visitor lands on your site with UTM parameters or ad IDs in the URL:

```
https://yoursite.com/?utm_source=facebook&utm_medium=social&utm_campaign=spring-sale
```

The plugin JavaScript (`atmos-tracker.js`) automatically:

1. Captures the parameters from the URL
2. Stores them in localStorage under the key `_atmos_attribution`
3. Creates a secure cookie with the same data
4. Updates both first-touch (if first visit) and last-touch attribution

### Form Integration (Fluent Forms)

1. JavaScript adds hidden fields to forms on page load, and again at submit time (so popups and AJAX-loaded forms are covered). Fields are only added for parameters that have a value.
2. On submission, the data is captured and stored with the entry. If the hidden fields are missing, the values are read from the cookie on the server instead.
3. Attribution data is saved in the form response JSON

## Data Structure

### Stored Cookie/LocalStorage Format

```json
{
  "first": {
    "src": "facebook",
    "mdm": "social",
    "cmp": "spring-sale",
    "fbclid": "abc123",
    "referrer": "https://facebook.com",
    "ts": 1707696000
  },
  "last": {
    "src": "google",
    "mdm": "cpc",
    "cmp": "summer-sale",
    "gclid": "xyz789",
    "referrer": "https://google.com",
    "ts": 1707782400
  }
}
```

### Field Names in Form Submissions

Last touch uses the plain parameter names, since most CRMs accept a single set and expect these names. First touch is prefixed with `first_`.

| Last touch | First touch |
|---|---|
| `utm_source` | `first_utm_source` |
| `utm_medium` | `first_utm_medium` |
| `utm_campaign` | `first_utm_campaign` |
| `utm_term` | `first_utm_term` |
| `utm_content` | `first_utm_content` |
| `gclid` | `first_gclid` |
| `fbclid` | `first_fbclid` |
| `referrer` | `first_referrer` |

Only parameters that were captured are submitted.

## Using Attribution Data in Fluent Forms

### Viewing in Entry Details

Attribution data is automatically stored in the form entry's response JSON. You can access it:

1. View any form entry in Fluent Forms
2. Check the raw JSON data to see attribution fields
3. Attribution values are included in form exports

### Displaying Fields in Entry View

To display attribution fields in the Fluent Forms entry view table:

1. Edit your form in Fluent Forms
2. Add hidden input fields with the exact field names listed above
3. The values will automatically appear in the entry details

Hidden fields are also what make the values available in Fluent Forms integration feeds (CRM field mapping only lists fields defined in the form). For most CRMs, add the last-touch fields (`utm_source`, `utm_medium`, `utm_campaign`, ...) and map those.

### Reading Attribution in Other Plugins

Use `atmos_get_attribution()` to read the cookie server-side. It returns only captured parameters, keyed by public name:

```php
$attribution = function_exists( 'atmos_get_attribution' ) ? atmos_get_attribution() : array();
// array( 'first' => array( 'utm_source' => 'google', 'referrer' => '...' ), 'last' => array( ... ) )
```

### Using in SmartCodes/Merge Tags

Once hidden fields are added to your form, you can use attribution data in:

- **Email notifications**: `{inputs.utm_source}`, `{inputs.first_utm_campaign}`, etc.
- **Confirmations**: Display the source that brought them to your site
- **Integrations**: Pass attribution data to CRM systems, email marketing platforms, etc.

#### Example Email Template

```
New Form Submission

Name: {inputs.name}
Email: {inputs.email}

Attribution:
First Touch Source: {inputs.first_utm_source}
First Touch Campaign: {inputs.first_utm_campaign}
Last Touch Source: {inputs.utm_source}
Last Touch Campaign: {inputs.utm_campaign}
Google Click ID: {inputs.gclid}
```

## Technical Details

### Cookie Specifications

- **Name**: `_atmos_attribution`
- **Duration**: 365 days
- **Attributes**: `SameSite=Lax; Path=/`, plus `Secure` on HTTPS pages
- **Storage**: Also duplicated in localStorage for redundancy

### Browser Compatibility

- Modern browsers with localStorage and cookie support
- JavaScript must be enabled
- Works on HTTPS and plain HTTP (e.g. local development)

### Performance

- Lightweight JavaScript (~2KB)
- Runs after page load (minimal performance impact)
- Form fields populated on page load and at submit time

## Debugging

The plugin includes comprehensive logging. To view debug logs:

1. Check `/wp-content/plugins/atmos-cookie-tracker/atmos-debug.log`
2. Look for entries prefixed with timestamp
3. Logs track form rendering, field injection, and data capture

## Frequently Asked Questions

### Q: Does this work with page caching?

Yes! The plugin uses client-side JavaScript to populate form fields, which bypasses server-side caching.

### Q: What if a visitor has ad blockers?

The tracking relies on cookies and localStorage. Most ad blockers don't prevent UTM parameters from being captured, but some may block the cookie. The plugin uses both localStorage and cookies for redundancy.

### Q: Can I customize which parameters are tracked?

Currently, the tracked parameters are hardcoded in the plugin. To add one, update both the `params` map in `js/atmos-tracker.js` and `atmos_get_param_map()` in `plugin.php`.

### Q: Does this work with other form plugins?

Currently, the plugin only has built-in integration with Fluent Forms. Integration with other form plugins would require custom development.

### Q: How long is attribution data stored?

- **Cookies**: 365 days
- **LocalStorage**: Persistent until manually cleared by the user or browser

## Changelog

### Version 1.2
- Last-touch fields renamed to plain parameter names (`utm_source`, `gclid`, ...); first touch keeps the `first_` prefix
- Referrer stored as origin + path (query string and fragment removed)
- Internal navigation no longer overwrites last-touch attribution; only external referrers are recorded
- Added `utm_term` and `utm_content` (stored only when present)
- Forms are populated on load and at submit time, replacing the 1-second delay
- Server-side cookie fallback when no attribution fields were posted (posted and cookie values are never mixed)
- Hidden inputs added by the script are removed when their value is no longer stored
- Added `atmos_get_attribution()` for other plugins
- Empty hidden fields are no longer printed into Fluent Forms markup
- `Secure` cookie flag only set on HTTPS

### Version 1.1
- Initial release with Fluent Forms integration
- Tracks UTM parameters, gclid, fbclid, and referrer
- First-touch and last-touch attribution
- Client-side form field population
- Secure cookie storage

## Support

For issues, questions, or feature requests, please contact the plugin developer.

## License

This plugin is proprietary software. All rights reserved.
