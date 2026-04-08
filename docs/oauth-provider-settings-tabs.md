# OAuth provider settings tabs — guidelines and requirements

This document is the **authoritative spec** for CreatorReactor “social login” provider tabs under **Settings** (alongside existing Google / Instagram patterns). New providers must follow it unless an exception is explicitly documented below.

## Tab layout

- **Creator (direct) mode**: Each provider uses **two stacked cards** on its tab:
  1. **OAuth credentials** — client app registration, redirect URI, Client ID / Secret, optional provider-specific fields, **Test** / **Disable** actions.
  2. **Login Button Appearance** — Compact vs Full (site default or per-provider override), admin preview chips.
- **Agency (broker) mode**: Social OAuth for visitors is **not** used. Show a short notice that the provider tab applies only in Creator mode; still allow saving non-secret prefs only if needed, or hide appearance card (match Google tab behavior).

## Credentials card

- **Title**: `H2` naming the provider (e.g. “Sign in with …”).
- **Instructions**: Wrapped in `<details class="…-instructions">` with `<summary>Instructions: …</summary>`. **Open by default** when the provider is not yet configured (no valid Client ID + Secret); closed once configured (match Google).
- **Ordered steps**: Link to the vendor’s developer console; include **exact** redirect URI registration step using the readonly field below.
- **Authorized redirect URI**: Read-only `input.large-text.code`, plus **Copy** (`creatorreactor-copy-redirect-uri`, `creatorreactor-redirect-uri-row`). Value must match the REST callback URL registered for this plugin (including trailing slash if the plugin outputs one).
- **Client ID / Client Secret**: Same semantics as Google — secrets stored **encrypted**; display `********` when unchanged; empty means “clear on save” only when combined with explicit disable flow where applicable.
- **Test**: Opens modal; validates credentials via a **probe** request (invalid authorization code → expected error proving client id/secret/redirect are accepted). Remediation text on failure.
- **Disable**: Clears encrypted credentials for that provider (admin POST + nonce), logs connection message, redirects back to tab.

## Option and meta naming

| Concept | Pattern |
|--------|---------|
| Client ID option | `creatorreactor_{slug}_oauth_client_id` (encrypted) |
| Client Secret option | `creatorreactor_{slug}_oauth_client_secret` (encrypted) |
| Button size mode | `creatorreactor_{slug}_oauth_button_size_mode` (`follow_site` \| `compact` \| `full`) |
| Login notice query arg | `creatorreactor_{slug}=` (see `Login_Page`) |
| Stable account id user meta | `creatorreactor_{slug}_sub` |

Slug uses **underscores** in PHP option keys (e.g. `x_twitter`, `tiktok`). REST route segments may use hyphenated forms for readability (`x-twitter-oauth-start`).

## Appearance card

- Reuse **`render_oauth_logo_button_size_mode_fieldset`** with per-provider mode and site-wide default from **General**.
- **Compact**: logo-only control, `OAUTH_COMPACT_BOX_PX` × `OAUTH_COMPACT_ICON_PX`.
- **Full**: branded label + icon row; provider-specific color/SVG allowed.
- **Preview**: Non-interactive chips matching front-end classes (`…--admin-preview`).

## Security and i18n

- All client IDs and secrets for these providers belong in **`Admin_Settings::ENCRYPTED_FIELDS`**.
- Strings: `wp-creatorreactor` text domain; escape output; sanitize input in `sanitize_options`.
- Broker mode: block OAuth **start** REST handlers and shortcodes with an Agency notice (same as Google).

## Exceptions

### Mastodon

- **Instance base URL** required: option `creatorreactor_mastodon_instance` (normalized `https://host` with no trailing path). Authorize and token endpoints are derived from this host.

### Bluesky (atproto OAuth)

- Not plain OAuth2-only: may require **client metadata URL**, **PAR**, **DPoP**. Tab layout stays the same where possible; extra fields or copy may reference Bluesky’s OAuth client documentation. Implementation lives in **`Bluesky_OAuth`**, not the generic `Social_OAuth` registry.

## References in code

- Google tab: `render_google_settings_fields`, `render_google_login_button_appearance_fields` in `includes/class-admin-settings.php`.
- Google OAuth: `includes/class-google-oauth.php`.
- PKCE helpers: `includes/class-creatorreactor-oauth.php`.
