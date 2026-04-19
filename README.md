# bbPress Profile Plus

A lightweight WordPress plugin that replaces BuddyPress Extended Profiles and Account Settings for bbPress sites. It reuses existing BuddyPress xProfile database tables so BuddyPress itself can be disabled/removed while keeping all profile data and functionality.

**Version:** 1.0.0  
**Author:** OpenTuition  
**License:** GPL-2.0-or-later  
**Requires PHP:** 8.0+  
**Requires WordPress:** 6.0+

## Why this plugin

BuddyPress is a heavy dependency when all you use from it is the xProfile system on a bbPress forum. This plugin provides a BuddyPress-free runtime that:

- Renders xProfile fields on the WordPress registration form
- Lets logged-in users edit their profile and account settings on the front end
- Handles avatar uploads
- Adds anti-spam protection and email activation
- Reads/writes directly to the existing wp_bp_xprofile_* tables

Once activated you can deactivate (and remove) BuddyPress.

## Features

- xProfile integration using existing wp_bp_xprofile_* tables, no BuddyPress runtime needed
- Custom registration form with xProfile fields (First Name, Studying, Country, Referral, etc.)
- Confirm Email field to prevent signup typos
- Anti-spam captcha (random simple arithmetic)
- Email activation flow with 48h expiry
- Pending users are blocked from logging in until the email link is clicked
- Daily cron that purges expired activation keys
- Front-end profile editor and account settings
- Local avatar upload and removal
- bbppp_fix_register_url filter that rewrites legacy registration URLs

## File layout

```
bbp-profile-plus/
  bbp-profile-plus.php            Plugin bootstrap, constants, activation hooks
  includes/
    class-bbppp-loader.php        Loads classes, registers hooks, enqueues assets
    class-bbppp-xprofile.php      xProfile field read/write layer
    class-bbppp-account.php       Account settings / profile editing
    class-bbppp-router.php        Front-end routing and page detection
    class-bbppp-antispam.php      Captcha + registration validation
    class-bbppp-activation.php    Email activation / pending user handling
  templates/                      Front-end templates
  assets/                         CSS + JS for profile and login pages
```

## Installation

1. Download the production ZIP from the v1.0.0 release: https://github.com/kriskl-lgtm/bbp-profile-plus/releases/tag/v1.0.0
2. Extract it into wp-content/plugins/ so the folder path is wp-content/plugins/bbp-profile-plus/
3. In WordPress admin go to Plugins > Installed Plugins and click Activate on bbPress Profile Plus
4. Confirm the registration form on wp-login.php?action=register now shows the xProfile fields
5. (Optional) Deactivate and delete BuddyPress. The xProfile tables are preserved and used directly.

## Requirements

- PHP 8.0 or higher (enforced on load)
- WordPress 6.0 or higher
- bbPress
- Existing BuddyPress xProfile tables in the database (wp_bp_xprofile_*). BuddyPress runtime is not required once this plugin is active.

## Registration flow

1. Visitor opens /wp-login.php?action=register
2. Plugin injects confirm-email, captcha and all configured xProfile fields
3. On submit, BBPPP_AntiSpam validates the captcha and confirm-email
4. BBPPP_Activation stores the signup as pending and emails an activation link (48h TTL)
5. User clicks the link - account is activated, xProfile data is written, user is logged in
6. Unclicked keys are purged daily by bbppp_cleanup_expired_activations cron

## Development

All code lives in includes/ as small singletons booted from BBPPP_Loader::boot() on plugins_loaded. Entry points:

- BBPPP_Loader::init() registers the plugins_loaded and init hooks
- BBPPP_Loader::boot() requires and instantiates all feature classes, registers enqueue hooks and login_form / register_form message hooks
- BBPPP_Loader::activate() / deactivate() flush rewrite rules

Constants (defined in bbp-profile-plus.php): BBPPP_VERSION, BBPPP_FILE, BBPPP_DIR, BBPPP_URL, BBPPP_TPL_DIR, BBPPP_ASSETS_URL

## Changelog

### 1.0.0 - 2026-04-19
- Initial production release
- Clean rewrite of class-bbppp-loader.php (correct hook registration, proper enqueue function signatures)
- Email activation with 48h expiry and pending-user login block
- Anti-spam captcha and confirm-email validation on the registration form
- Front-end profile editor and local avatar support
- BuddyPress xProfile table reuse, runtime-independent

## License

Released under the GPL-2.0-or-later license, same as WordPress itself.
