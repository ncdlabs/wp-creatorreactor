# CreatorReactor

WordPress plugin for OAuth, scheduled subscriber/follower sync, and entitlement checks for creator platforms (Fanvue today; additional products such as OnlyFans are supported at the data model level). It also provides shortcodes, Gutenberg blocks, and optional Elementor widgets for tier gates and Fanvue login on the front end.

**Version:** 2.0.3 (see `creatorreactor.php`)

**Author:** Lou Grossi · [ncdLabs](https://ncdlabs.com)

## Requirements

- WordPress **5.9** or later  
- PHP **8.1** or later  
- HTTPS in production (OAuth and token handling)  
- **Elementor** (optional) — only needed if you use the Elementor widgets; shortcodes and blocks work without it  

## Overview

The plugin stores synced rows in `wp_*creatorreactor_entitlements` (table name constant: `creatorreactor_entitlements`). Each row includes a `product` column so entitlements can be filtered by source platform. Upgrades from older installs may migrate legacy option keys and the previous entitlements table name automatically.

## Operating modes

| Mode | Behavior |
|------|----------|
| **Creator** (direct) | OAuth and API calls go straight to **Fanvue** (default `auth.fanvue.com` + `api.fanvue.com`). **Required:** OAuth Client ID and Client Secret, redirect URI matching your app, and scopes as shown in **Settings → CreatorReactor**. |
| **Agency** (broker) | OAuth is handled by an external broker (default broker URL `https://auth.ncdlabs.com`; configurable). **Required:** broker URL and **Site ID** from the broker admin. **Optional:** OAuth Client ID, redirect URI, and scopes — they are only appended to the broker connect URL when set; the client secret is **not** used in Agency mode (authentication uses a JWT after connect). Subscriber/follower sync uses the broker-backed API path. |

Choose **Creator** or **Agency** under **Authentication Modes** in plugin settings. The broker REST client registers in all modes so OAuth redirects such as `…/broker-callback` always resolve (for example when the Fanvue app redirect URI does not match the current mode).

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                     WordPress: CreatorReactor plugin              │
│  ┌────────────────────────┐    ┌─────────────────────────────┐   │
│  │ Admin (OAuth + sync UI) │    │ Entitlements + WP-Cron sync │   │
│  └───────────┬────────────┘    └──────────────┬──────────────┘   │
│              │                                │                  │
│              ▼                                ▼                  │
│  ┌───────────────────────┐         ┌───────────────────────┐     │
│  │ Direct: OAuth +       │         │ CreatorReactor_Client │     │
│  │ CreatorReactor_Client │   OR    │ (list subscribers /   │     │
│  └───────────┬───────────┘         │  followers)           │     │
│              │                      └───────────┬───────────┘     │
└──────────────┼──────────────────────────────────┼───────────────┘
               │ Direct API                         │
               ▼                                    ▼
     ┌─────────────────────┐            ┌─────────────────────┐
     │ Fanvue OAuth         │            │ Fanvue API           │
     │ & API                │            │                      │
     └─────────────────────┘            └─────────────────────┘

Broker mode: WordPress → Broker (OAuth / token) → Fanvue API via broker proxy (`Broker_Client`).
```

**Separate flow (visitors):** In **Creator** mode, **Fanvue OAuth** (`Fan_OAuth`) lets site visitors log in or link a Fanvue account via REST routes without replacing the site’s creator OAuth tokens. Shortcodes and blocks can render a “Login with Fanvue” link; that flow is unavailable in **Agency** mode.

## Features

- **OAuth (creator / site)** — Authorization code flow with **PKCE (S256)** (required for Fanvue-style OAuth per [their Quick Start](https://api.fanvue.com/docs/authentication/quick-start)); tokens stored encrypted.  
- **Fanvue OAuth (visitors)** — Optional front-end login/link flow; register the callback URL shown under **Settings → CreatorReactor → Shortcodes** in your Fanvue app when using `[fanvue_login_button]` or the matching block.  
- **Subscriber sync** — Tier tracking; scheduled sync via WP-Cron (`includes/class-cron.php`).  
- **Follower sync** — Followers identified separately from paid tiers (follower tier constant `Entitlements::TIER_FOLLOWER` and product-scoped stored tiers such as `fanvue_follower`).  
- **Entitlements API** — PHP helpers to test access and list active subscribers by tier and optional product.  
- **Shortcodes** — `[follower]`, `[subscriber]`, `[logged_out]`, `[logged_in]`, `[logged_in_no_role]`, `[has_tier]`, `[onboarding_incomplete]`, `[onboarding_complete]`, `[fanvue_connected]`, `[fanvue_not_connected]`, `[fanvue_login_button]` (see **Shortcodes** tab in settings).  
- **Gutenberg** — Blocks in category **CreatorReactor** for follower/subscriber gates, logged-in/logged-out visibility, tier checks, onboarding status, Fanvue connected state, and Login with Fanvue (same rules as shortcodes).  
- **Elementor** — Widgets in category **CreatorReactor** for the same gate types plus Fanvue OAuth and onboarding form output.  

## WordPress admin

**Settings → CreatorReactor** includes:

| Tab | Purpose |
|-----|---------|
| **Dashboard** | Connection status, OAuth start, high-level controls |
| **Users** | Entitlements table view, sync tooling |
| **Shortcodes** | Shortcode/block/widget reference and Fanvue redirect URI for visitor OAuth |
| **Settings** | OAuth, sync, and product-specific options (sidebar: OAuth, Sync) |

## Configuration

In the WordPress admin, open **Settings → CreatorReactor** to set **Authentication Modes** (Creator vs Agency), then fill in the fields that apply: for Creator, OAuth app credentials and endpoints; for Agency, broker URL and Site ID first, then optional OAuth fields for the connect link.

### Fanvue OAuth (Creator mode)

Official Fanvue walkthrough: **[OAuth Quick Start](https://api.fanvue.com/docs/authentication/quick-start)**.

That guide matches what this plugin implements for **Creator** (direct) mode:

| Fanvue docs | This plugin |
|-------------|-------------|
| PKCE with `code_challenge_method=S256` is **required** | Implemented: verifier/challenge generated per authorize request; verifier recovered at callback for token exchange |
| Authorization request uses `client_id`, `redirect_uri`, `response_type=code`, `scope`, `state`, PKCE params | Same query shape against your configured **Authorization URL** |
| Token exchange uses `grant_type=authorization_code`, `code`, `redirect_uri`, `code_verifier`, plus client authentication | Implemented via `POST` to your configured **Token URL** with HTTP Basic (`client_id`:`client_secret`) per existing `CreatorReactor_OAuth` |

For **Creator** mode, defaults match Fanvue’s OAuth hosts: **`https://auth.fanvue.com/oauth2/auth`**, **`https://auth.fanvue.com/oauth2/token`**, and API base **`https://api.fanvue.com`**. Override **OAuth → Endpoints** or **API Base URL** in settings only if you need a different environment. Register the same **Redirect URI** in the [Fanvue developer portal](https://fanvue.com/developers/apps).

Default scopes match Fanvue’s quick start: `openid offline_access offline read:self`. If your OAuth app is granted extra scopes (e.g. `read:fan`), add them under **Advanced → Scopes**.

## API (PHP)

Namespace: `CreatorReactor\Entitlements`.

Product slugs include `fanvue` (canonical; legacy `creatorreactor` is normalized to this) and `onlyfans` (see `Entitlements::PRODUCT_*`).

```php
// Any active subscription (all products)
$has_access = \CreatorReactor\Entitlements::check_user_entitlement( $user_id );

// Specific tier (all products)
$has_tier = \CreatorReactor\Entitlements::check_user_entitlement( $user_id, 'premium' );

// Specific tier for one product (prefer fanvue; creatorreactor is accepted as legacy)
$has_tier = \CreatorReactor\Entitlements::check_user_entitlement( $user_id, 'premium', 'fanvue' );

// Active subscribers for a tier in one product
$users = \CreatorReactor\Entitlements::get_active_subscribers( 'premium', 'fanvue' );

// Active subscribers for a tier across products
$users = \CreatorReactor\Entitlements::get_active_subscribers( 'premium' );
```

Higher-level wrappers for profile/subscribers/followers (respecting direct vs broker mode) live on `CreatorReactor\Plugin`: `get_profile()`, `get_subscribers()`, `get_followers()`.

**Developer helper:** `CreatorReactor\Editor_Context` (see `includes/class-editor-context.php`) offers heuristics for whether Elementor is active, the block editor is in use, and related admin/front-end context — useful for conditional UI or tooling.

## Security

- OAuth tokens encrypted at rest  
- PKCE support for the OAuth flow  
- HTTPS expected for production  
- Client secret and related fields handled via the plugin’s encrypted storage patterns  

## Repository layout

Repository root is the plugin directory (install as `wp-content/plugins/creatorreactor/` or zip contents).

```
.
├── creatorreactor.php                    # Bootstrap, constants, activation hooks
├── uninstall.php                         # Optional full cleanup when uninstall + setting enabled
├── includes/
│   ├── partials/                         # Admin HTML fragments (OAuth/Sync by auth mode)
│   ├── class-admin-settings.php          # Settings UI, options, migrations, tabs
│   ├── class-broker-client.php           # Broker OAuth + API proxy
│   ├── class-broker-settings.php         # Broker settings placeholder
│   ├── class-creatorreactor.php          # Plugin bootstrap, mode helpers, feature init
│   ├── class-creatorreactor-blocks.php   # Gutenberg block registration
│   ├── class-creatorreactor-client.php   # Direct API client
│   ├── class-creatorreactor-elementor.php
│   ├── class-creatorreactor-elementor-widgets.php
│   ├── class-creatorreactor-fan-oauth.php # Visitor Fanvue OAuth (REST)
│   ├── class-creatorreactor-oauth.php    # Site/creator OAuth
│   ├── class-creatorreactor-shortcodes.php
│   ├── class-cron.php
│   ├── class-editor-context.php          # Elementor vs block editor detection (developers)
│   ├── class-editor-blocks-prompt.php
│   └── class-entitlements.php
├── js/
│   ├── creatorreactor-blocks-editor.js   # Block editor script
│   ├── creatorreactor-editor-prompt-modal.js
│   └── creatorreactor-users-tab.js       # Users tab UI
├── build-zip.sh                          # Release zip (see below)
└── README.md
```

What loads at runtime is defined in `creatorreactor.php` and `includes/class-creatorreactor.php` (other files under `includes/` and `js/` may exist for in-progress or auxiliary use).

## Building a release zip

From the plugin root:

```bash
./build-zip.sh
```

This reads the version from `creatorreactor.php` and writes `creatorreactor-<version>.zip` including `creatorreactor.php`, `includes/`, `js/`, and `languages/` if present.

## License

GPL v2 or later

## Support

For issues and feature requests, contact [ncdLabs](https://ncdlabs.com).
