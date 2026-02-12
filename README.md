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
- `gclid` - Google Click ID for Google Ads tracking
- `fbclid` - Facebook Click ID for Facebook Ads tracking
- `referrer` - Page referrer URL

Both **first-touch** (initial visit) and **last-touch** (most recent visit) values are stored for each parameter.

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

When a Fluent Form is rendered on the page:

1. Hidden fields are automatically added for all attribution parameters
2. JavaScript fills these fields with the stored attribution data
3. On form submission, the data is captured and stored with the entry
4. Attribution data is saved in the form response JSON

## Data Structure

### Stored Cookie/LocalStorage Format

```json
{
  "first": {
    "src": "facebook",
    "mdm": "social",
    "cmp": "spring-sale",
    "gclid": null,
    "fbclid": "abc123",
    "referrer": "https://facebook.com",
    "ts": 1707696000
  },
  "last": {
    "src": "google",
    "mdm": "cpc",
    "cmp": "summer-sale",
    "gclid": "xyz789",
    "fbclid": null,
    "referrer": "https://google.com",
    "ts": 1707782400
  }
}
```

### Field Names in Form Submissions

Attribution data is stored with these field names:

- `first_utm_source`, `last_utm_source`
- `first_utm_medium`, `last_utm_medium`
- `first_utm_campaign`, `last_utm_campaign`
- `first_gclid`, `last_gclid`
- `first_fbclid`, `last_fbclid`
- `first_referrer`, `last_referrer`

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

### Using in SmartCodes/Merge Tags

Once hidden fields are added to your form, you can use attribution data in:

- **Email notifications**: `{inputs.first_utm_source}`, `{inputs.last_utm_campaign}`, etc.
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
Last Touch Source: {inputs.last_utm_source}
Last Touch Campaign: {inputs.last_utm_campaign}
Google Click ID: {inputs.last_gclid}
```

## Technical Details

### Cookie Specifications

- **Name**: `_atmos_attribution`
- **Duration**: 365 days
- **Attributes**: `SameSite=Lax; Secure; Path=/`
- **Storage**: Also duplicated in localStorage for redundancy

### Browser Compatibility

- Modern browsers with localStorage and cookie support
- JavaScript must be enabled
- Works with HTTPS (Secure cookie attribute)

### Performance

- Lightweight JavaScript (~2KB)
- Runs after page load (minimal performance impact)
- Form field population delayed by 1 second to ensure forms are loaded

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

Currently, the tracked parameters are hardcoded in the plugin. You can modify the `$params` array in `fluent-forms.php` to track additional parameters.

### Q: Does this work with other form plugins?

Currently, the plugin only has built-in integration with Fluent Forms. Integration with other form plugins would require custom development.

### Q: How long is attribution data stored?

- **Cookies**: 365 days
- **LocalStorage**: Persistent until manually cleared by the user or browser

## Changelog

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
