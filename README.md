# Atmos Cookie Tracker

A WordPress plugin that captures first-touch and last-touch attribution data from UTM parameters, ad IDs, and referrer information, storing them in browser localStorage and cookies for later form submission tracking.

## Description

Atmos Cookie Tracker automatically tracks visitor attribution data across your WordPress site and seamlessly integrates with Fluent Forms to capture marketing attribution alongside form submissions. This enables you to understand which marketing channels and campaigns are driving conversions.

## Features

- **Automatic Attribution Tracking**: Captures UTM parameters, Google, Microsoft and Meta click IDs, and referrer information
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
- `gclid` - Google Ads click ID
- `gbraid`, `wbraid` - Google Ads click IDs used in place of `gclid` for some iOS traffic
- `msclkid` - Microsoft (Bing) Ads click ID
- `fbclid` - Meta (Facebook/Instagram) click ID
- `referrer` - Referring URL, external sites only, reduced to origin + path (the referring site's query string and fragment are dropped)
- `landing` - The page the touch arrived on (origin + path). Recorded with every touch but never creates one; stored as a path and submitted as a full URL

Both **first-touch** (initial visit) and **last-touch** (most recent visit) values are stored. Only parameters that are present and non-empty are stored; missing ones are omitted rather than saved as empty values.

### Source and medium from click IDs

When a URL has a click ID but **neither** `utm_source` nor `utm_medium`, both are filled in automatically:

| URL contains | `utm_source` | `utm_medium` |
|---|---|---|
| `gclid`, `gbraid`, `wbraid`, or `gad_source` | `google` | `cpc` |
| `msclkid` | `bing` | `cpc` |
| `fbclid` | `meta` | `social` |

If either UTM is present, nothing is inferred, so tagged links are never altered or mixed. `gad_source` only marks a Google Ads click and isn't stored itself. `fbclid` is marked `social` rather than paid because Meta adds it to every outbound link, including organic posts; tag Meta ads with their own UTMs to identify paid traffic.

### What counts as a touch

A page view updates attribution only when it carries a new signal: a UTM parameter, a click ID, or a referrer from another site. Navigating between pages on your own site (including `www.` vs. bare domain) never overwrites stored attribution.

**Paid protection (24 hours):** if the last touch was paid and is less than 24 hours old, a visit with only a referrer (e.g. an organic Google search) is ignored, so the ad keeps the credit. A touch counts as paid when `utm_medium` is `cpc`, `ppc` or starts with `paid`, or it has a Google or Microsoft Ads click ID. Tagged visits (UTMs or click IDs) always replace the last touch. The window is the `PAID_PROTECTION_SECONDS` constant in `js/atmos-tracker.js`.

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

After a single touch, only `last` is stored (it is also the first touch). `v` is the storage format version:

```json
{
  "v": 2,
  "last": { "src": "google", "mdm": "cpc", "gclid": "xyz789", "ts": 1707696000 }
}
```

When a second touch arrives, the existing `last` moves to `first` (this happens once; `first` is never replaced) and the new touch becomes `last`:

```json
{
  "v": 2,
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
| `gbraid` | `first_gbraid` |
| `wbraid` | `first_wbraid` |
| `msclkid` | `first_msclkid` |
| `fbclid` | `first_fbclid` |
| `referrer` | `first_referrer` |
| `landing` | `first_landing` |

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

Hidden fields are only needed for entry columns and exports. Feeds, notifications and confirmations can use the Atmos SmartCodes below without adding any fields.

### Reading Attribution in Other Plugins

Use `atmos_get_attribution()` to read the cookie server-side. It returns only captured parameters, keyed by public name:

```php
$attribution = function_exists( 'atmos_get_attribution' ) ? atmos_get_attribution() : array();
// array( 'first' => array( 'utm_source' => 'google', 'referrer' => '...' ), 'last' => array( ... ) )
```

To work from a stored submission instead (e.g. in a feed or background job), rebuild it from the entry's fields and optionally turn it into the full attribution URL:

```php
$attribution = atmos_get_attribution_from_fields( $entry_data ); // same shape as above
$last_url    = atmos_build_touch_url( $attribution, 'last' );    // same value as {atmos_last_attribution}
$combined    = atmos_build_combined_url( $attribution );         // same value as {atmos_combined_attribution}
```

### SmartCodes

Every form gets an **Atmos Attribution** group in the SmartCode dropdown (feeds, notifications, confirmations). No hidden fields are needed. Values come from the submitted entry, so they work in asynchronous feeds too.

| SmartCode | Value |
|---|---|
| `{atmos_last_attribution}` | Last touch as a real URL (see below). Use this as a CRM Referrer |
| `{atmos_first_attribution}` | First touch, same format, e.g. for a CRM custom field |
| `{atmos_combined_attribution}` | Both touches in one URL (see below) |
| `{atmos_utm_source}`, `{atmos_utm_medium}`, `{atmos_utm_campaign}`, `{atmos_utm_term}`, `{atmos_utm_content}` | Last-touch UTMs |
| `{atmos_gclid}`, `{atmos_gbraid}`, `{atmos_wbraid}`, `{atmos_msclkid}`, `{atmos_fbclid}` | Last-touch click IDs |
| `{atmos_referrer}` | Last-touch referrer |
| `{atmos_landing}` | Last-touch landing page |
| `{atmos_first_utm_source}` ... `{atmos_first_landing}` | The same values for first touch |

Values that weren't captured resolve to an empty string. For the page a form was submitted on (the converting page), use Fluent Forms' own `{embed_post.permalink}`.

#### Attribution URLs

Params are only ever added to your own URLs, never to a referrer (google.com never had your UTMs). `{atmos_last_attribution}` and `{atmos_first_attribution}` return the real URL behind a touch, using plain param names:

| Touch | URL |
|---|---|
| Tagged (UTMs or click IDs) | The link the visitor clicked: landing page + its params, plus `referrer` when there was one |
| Untagged (organic search, referral) | The referrer itself, unchanged |

```
https://example.com/spring-open-house/?utm_source=google&utm_medium=cpc&gclid=abc&referrer=https%3A%2F%2Fwww.google.com%2F
https://example.com/spring-open-house/?utm_source=newsletter&utm_medium=email
https://www.google.com/
```

This matches a CRM Referrer ("referring site or pay-per-click source"). Older entries without a landing page use the home URL as the base.

`{atmos_combined_attribution}` bundles both touches: the last-touch landing page with every other value as a param, named as form fields (`referrer`, `first_utm_source`, `first_referrer`, `first_landing`, ...). First-touch values are always included, even when they match last touch, so the structure is the same for every lead. It's a data bundle rather than a real link, so prefer `{atmos_last_attribution}` for a Referrer field.

#### Example Email Template

```
New Form Submission

Name: {inputs.name}
Email: {inputs.email}

Attribution:
First Touch Source: {atmos_first_utm_source}
First Touch Campaign: {atmos_first_utm_campaign}
Last Touch Source: {atmos_utm_source}
Last Touch Campaign: {atmos_utm_campaign}
Google Click ID: {atmos_gclid}
Last touch URL: {atmos_last_attribution}
```

### Upgrading Stored Data

Data saved before format version 2 (plugin 1.1 and early 1.2/1.3 builds) is cleaned up once, on the visitor's next page view:

1. Referrers pointing to your own site are removed. v1.1 recorded internal page views as touches, overwriting `last` and sometimes setting `first` to your own page.
2. Touches left with no signal (no UTMs, click IDs or external referrer) are dropped.
3. External referrers are reduced to origin + path.
4. The result is saved in the current format: two different touches stay as `first`/`last`, and a single remaining touch (or two identical ones) becomes `last`. If nothing valid remains, the storage is cleared.

Last-touch values overwritten by the v1.1 bug can't be recovered.

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

### Version 1.3.0
- Landing page recorded with each touch (`landing`, `first_landing`, `{atmos_landing}`, `{atmos_first_landing}`); attribution URLs use it as their base, params are never added to a referrer
- Stored data carries a format version (`v: 2`); data from earlier versions is cleaned up once (own-site referrers and empty touches removed, referrers stripped of query strings)
- Fluent Forms SmartCodes: `{atmos_<field>}` for every captured value and `{atmos_combined_attribution}` for both touches in one URL, listed under "Atmos Attribution" in the SmartCode dropdown
- Added `atmos_get_attribution_from_fields()`, `atmos_build_combined_url()` and `atmos_build_touch_url()` for other plugins
- `{atmos_last_attribution}` and `{atmos_first_attribution}` SmartCodes: one touch as its real URL (clicked link for tagged touches, the referrer itself for untagged ones)

### Version 1.2
- Only `last` is stored until a second touch arrives, then it moves to `first` (smaller cookie); readers treat a missing `first` as equal to `last`
- Paid protection: a referrer-only visit within 24 hours of a paid last touch doesn't replace it
- Added `gbraid`, `wbraid` and `msclkid` click IDs
- `utm_source`/`utm_medium` inferred from click IDs when neither is present (Google and Bing: `cpc`, Meta: `social`)
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
