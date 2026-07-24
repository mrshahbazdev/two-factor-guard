# Two Factor Guard

A WordPress plugin that adds TOTP-based two-factor authentication (2FA) to the standard login form with QR setup, backup codes, and role-based enforcement.

## Features

- **TOTP 2FA**: works with Google Authenticator, Authy, Microsoft Authenticator, and any TOTP app.
- **QR code setup**: users scan a QR code or enter a secret key from their profile.
- **Backup codes**: one-time recovery codes are generated when 2FA is enabled.
- **Role enforcement**: require 2FA for specific user roles.
- **Opt-in support**: allow users to enable 2FA voluntarily.
- **Standard login form**: adds a 2FA code field to `wp-login.php`.

## Screenshots

![Settings](https://raw.githubusercontent.com/mrshahbazdev/two-factor-guard/main/screenshots/settings.png)
![Profile Setup](https://raw.githubusercontent.com/mrshahbazdev/two-factor-guard/main/screenshots/profile.png)
![Login Form](https://raw.githubusercontent.com/mrshahbazdev/two-factor-guard/main/screenshots/login.png)

## Installation

1. Download `two-factor-guard.zip` from [Releases](https://github.com/mrshahbazdev/two-factor-guard/releases).
2. Upload and extract to `wp-content/plugins/two-factor-guard/`.
3. Activate the plugin.
4. Go to **Tools > Two Factor Guard** to enable and select roles.
5. Users can set up 2FA from their **Profile** page.

## Requirements

- WordPress 6.5 or higher
- PHP 7.4 or higher

## License

GPL-2.0-or-later
