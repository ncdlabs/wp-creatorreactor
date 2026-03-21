# CreatorReactor

WordPress plugin for OAuth, scheduled subscriber/follower sync, and entitlement checks for creator platforms (CreatorReactor today; additional products such as OnlyFans are supported at the data model level).

**Version:** 2.0.1 (see `creatorreactor.php`)

**Author:** Lou Grossi · [ncdLabs](https://ncdlabs.com)

## Requirements

- WordPress **5.9** or later  
- PHP **8.1** or later  
- HTTPS in production (OAuth and token handling)

## Overview

The plugin stores synced rows in `wp_*creatorreactor_entitlements` (table name constant: `creatorreactor_entitlements`). Each row includes a `product` column so entitlements can be filtered by source platform. Upgrades from older installs may migrate legacy option keys and the previous entitlements table name automatically.

## Operating modes

| Mode | Behavior |
|------|----------|
| **Creator** (direct) | OAuth and API calls go straight to **Fanvue** (default `auth.fanvue.com` + `api.fanvue.com`). **Required:** OAuth Client ID and Client Secret, redirect URI matching your app, and scopes as shown in **Settings → CreatorReactor**. |
| **Agency** (broker) | OAuth is handled by an external broker (default broker URL `https://auth.ncdlabs.com`; configurable). **Required:** broker URL and **Site ID** from the broker admin. **Optional:** OAuth Client ID, redirect URI, and scopes — they are only appended to the broker connect URL when set; the client secret is **not** used in Agency mode (authentication uses a JWT after connect). Subscriber/follower sync uses the broker-backed API path. |

Choose **Creator** or **Agency** under **Authentication Modes** in plugin settings. REST routes for broker connect/callback/disconnect load when Agency mode is active.

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
     │ CreatorReactor OAuth │            │ CreatorReactor API   │
     │ & API                │            │                      │
     └─────────────────────┘            └─────────────────────┘

Broker mode: WordPress → Broker (OAuth / token) → CreatorReactor API via broker proxy (`Broker_Client`).
```

## Features

- **OAuth** — Authorization code flow with **PKCE (S256)** (required for Fanvue-style OAuth per [their Quick Start](https://api.fanvue.com/docs/authentication/quick-start)); tokens stored encrypted.
- **Subscriber sync** — Tier tracking; scheduled sync via WP-Cron (`includes/class-cron.php`).
- **Follower sync** — Followers identified separately from paid tiers (follower tier constant `Entitlements::TIER_FOLLOWER`).
- **Entitlements API** — PHP helpers to test access and list active subscribers by tier and optional product.

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

Product slugs include `fanvue` (FanVue; legacy `creatorreactor` is normalized to this) and `onlyfans` (see `Entitlements::PRODUCT_*`).

```php
// Any active subscription (all products)
$has_access = \CreatorReactor\Entitlements::check_user_entitlement( $user_id );

// Specific tier (all products)
$has_tier = \CreatorReactor\Entitlements::check_user_entitlement( $user_id, 'premium' );

// Specific tier for one product
$has_tier = \CreatorReactor\Entitlements::check_user_entitlement( $user_id, 'premium', 'creatorreactor' );

// Active subscribers for a tier in one product
$users = \CreatorReactor\Entitlements::get_active_subscribers( 'premium', 'creatorreactor' );

// Active subscribers for a tier across products
$users = \CreatorReactor\Entitlements::get_active_subscribers( 'premium' );
```

Higher-level wrappers for profile/subscribers/followers (respecting direct vs broker mode) live on `CreatorReactor\Plugin`: `get_profile()`, `get_subscribers()`, `get_followers()`.

## Security

- OAuth tokens encrypted at rest  
- PKCE support for the OAuth flow  
- HTTPS expected for production  
- Client secret and related fields handled via the plugin’s encrypted storage patterns  

## Repository layout

Repository root is the plugin directory (install as `wp-content/plugins/creatorreactor/` or zip contents).

```
.
├── creatorreactor.php              # Bootstrap, constants, hooks
├── includes/
│   ├── partials/                   # Admin HTML fragments (OAuth/Sync by auth mode)
│   ├── class-admin-settings.php    # Settings UI, options, migrations
│   ├── class-broker-client.php     # Broker OAuth + API proxy
│   ├── class-broker-settings.php   # Broker settings placeholder
│   ├── class-creatorreactor.php    # Plugin bootstrap, mode helpers
│   ├── class-creatorreactor-client.php
│   ├── class-creatorreactor-oauth.php
│   ├── class-cron.php
│   └── class-entitlements.php
├── js/
│   └── creatorreactor-users-tab.js # Optional/auxiliary UI script
├── build-zip.sh                    # Release zip (see below)
└── README.md
```

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
