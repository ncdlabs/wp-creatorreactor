=== CreatorReactor ===
Contributors: lougrossi
Tags: oauth, membership, subscribers, elementor, login
Requires at least: 5.9
Tested up to: 6.9
Stable tag: 2.1.2
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
OAuth, sync, and content gates for creator memberships (Fanvue and related APIs); shortcodes, blocks, and optional Elementor.

== Description ==

CreatorReactor connects your WordPress site to creator platforms so you can:

* Run OAuth (authorization code + PKCE) for the site’s creator account and store tokens securely.
* Sync subscriber and follower data on a schedule (WP-Cron) into a local entitlements table.
* Gate content with shortcodes, Gutenberg blocks, or optional Elementor widgets (logged in/out, tiers, onboarding, Fanvue visitor login).
* Use **Creator** (direct API) or **Agency** (configurable broker) authentication modes.

**Third-party services**

Using OAuth, sync, or visitor login requires your site to reach the provider APIs you configure (for example Fanvue, Google for sign-in, an optional broker, or OnlyFans-related APIs where enabled). See each provider’s terms and privacy policy before enabling features.

**Optional metrics ingest**

If a metrics ingest base URL and bearer token are configured (or set via environment variables), the plugin may POST anonymized sync-completion events to that endpoint. No metrics are sent unless both URL and token are configured.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the zip from the Plugins screen.
2. Activate the plugin through the **Plugins** menu.
3. Open **Settings → CreatorReactor** and complete OAuth and sync settings for your mode (Creator or Agency).
4. Optional: install **Elementor** if you want Elementor widgets; shortcodes and blocks work without it.

== Frequently Asked Questions ==

= Does this work without Elementor? =

Yes. Shortcodes and Gutenberg blocks do not require Elementor.

= Does the plugin phone home? =

The plugin contacts external hosts only when you configure OAuth, sync, visitor login, broker, schema/metrics endpoints, or similar features—consistent with [WordPress.org Software as a Service guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#6-software-as-a-service-is-permitted).

= Where is the source code? =

Development repository: https://github.com/ncdlabs/creatorreactor

== Screenshots ==

1. CreatorReactor settings and dashboard in wp-admin.
2. Content gate blocks in the block editor.
3. Optional Elementor widgets in the Elementor panel.

== Changelog ==

= 2.1.2 =
* Aligns with WordPress Plugin Check and PHPCS expectations (database prepare patterns, admin input sanitization, shortcode CSS string style).
* Release workflow: opt into Node.js 24 for GitHub Actions and exclude `phpcs.xml.dist` from Plugin Check scans.

= 2.1 =
* Fixes Fanvue login enablement and role impersonation regressions.
* Includes CreatorReactor Cloud Metrics ingest sync scheduling improvements.

== Upgrade Notice ==

= 2.1.2 =
Maintenance release; safe to apply after quick staging checks on OAuth and admin settings saves.

= 2.1 =
Recommended update with regression fixes; review OAuth, role, and sync behavior in staging before production rollout.

== Privacy ==

This plugin may send data to third-party services you enable (OAuth providers, APIs, an optional broker, schema or metrics endpoints). Data handled depends on your configuration: tokens and entitlements are stored in the WordPress database; outbound requests use WordPress HTTP APIs. Configure only services you trust and document your site’s privacy policy accordingly.

The plugin registers personal data exporters and erasers with WordPress (under **Tools → Export Personal Data** and **Erase Personal Data**) for user meta and related records it controls, and can add suggested wording to the site privacy policy via **Settings → Privacy**.
