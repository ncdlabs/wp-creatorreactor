# FanBridge - OAuth Integration Platform

## Overview

FanBridge is a secure OAuth integration platform that connects Fanvue creators to external applications. It periodically syncs subscribers and followers for use in custom integrations.

**Author:** ncdLabs - [ncdlabs.com](https://ncdlabs.com)

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    FanBridge Plugin                         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              Fanvue OAuth + API Client               │   │
│  └─────────────────────────┬───────────────────────────┘   │
└────────────────────────────┼────────────────────────────────┘
                             │
                             ▼
                   ┌─────────────────────┐
                   │    Fanvue API      │
                   │  (Direct Calls)    │
                   └─────────────────────┘
```

## Features

### Subscriber Sync
- Automatic sync of subscribers from Fanvue
- Subscription tier tracking
- Scheduled sync via WP-Cron

### Follower Sync
- Automatic sync of followers
- Follower/non-subscriber identification

### Entitlements
- Check if user has active subscription
- Check tier-specific access
- Automatic expiration handling

## API Functions

### Check User Entitlement

```php
// Check if user has any active subscription
$has_access = FanBridge\Entitlements::check_user_entitlement($user_id);

// Check if user has specific tier
$has_tier = FanBridge\Entitlements::check_user_entitlement($user_id, 'premium');

// Get all active subscribers for a tier
$subscribers = FanBridge\Entitlements::get_active_subscribers('premium');
```

## Security

- OAuth tokens encrypted at rest
- PKCE support for OAuth flow
- HTTPS required
- Tokens with expiration
- No secrets stored in database options

## Directory Structure

```
fanbridge/
├── fanbridge.php              # Main plugin file
└── includes/
    ├── class-admin-settings.php  # Admin settings UI
    ├── class-entitlements.php    # Entitlements management
    ├── class-fanvue-oauth.php    # OAuth handling
    ├── class-fanvue-client.php   # Fanvue API client
    └── class-cron.php           # WP-Cron sync
```

## License

GPL v2 or later

## Support

For issues and feature requests, please contact ncdLabs.
