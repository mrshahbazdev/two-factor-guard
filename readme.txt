=== Two Factor Guard ===
Contributors: mrshahbaznns
Donate link: https://github.com/mrshahbazdev/two-factor-guard
Tags: two-factor, 2fa, totp, authentication, security
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add TOTP-based two-factor authentication to WordPress with QR setup, backup codes and role-based enforcement.

== Description ==

Two Factor Guard adds an extra layer of security to WordPress logins using time-based one-time passwords (TOTP).

Features:

* TOTP 2FA via any authenticator app (Google Authenticator, Authy, etc.).
* QR code and secret key setup on the user profile page.
* Backup codes for account recovery.
* Global enable/disable toggle.
* Optional user opt-in or enforced for specific roles.
* Works with the standard WordPress login form.

== Installation ==

1. Upload the `two-factor-guard` folder to `wp-content/plugins/`.
2. Activate the plugin.
3. Go to **Tools > Two Factor Guard** to enable and choose enforced roles.
4. Each user can set up 2FA from their **Profile** page.

== Changelog ==

= 1.0.0 =
* Initial release.
