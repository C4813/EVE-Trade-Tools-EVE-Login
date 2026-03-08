=== EVE Trade Tools EVE Login ===
Contributors: c4813
Tags: eve online, esi, sso, login, registration
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: ett-price-helper

Register and log in using EVE Online SSO. Accounts are created on first login. Existing users can link their EVE character.

== Description ==

EVE Trade Tools — EVE Login lets visitors register and sign in to your WordPress site using their EVE Online character via the official EVE SSO.

Features:

* EVE SSO button injected into wp-login.php automatically.
* New accounts created automatically on first login (subscriber role, random unusable password).
* Existing WordPress users can link their EVE character from their profile page or via shortcode.
* Optional SSO-only mode: disables standard WP registration so accounts can only be created via EVE SSO.
* Shortcodes: `[ett_eve_login_button]` and `[ett_eve_link_character]`.
* Settings managed via the shared EVE Trade Tools admin page (Price Helper tab framework).

This plugin requires EVE Trade Tools Price Helper to be installed and active.

== Installation ==

1. Install and activate EVE Trade Tools Price Helper first.
2. Upload this plugin folder to `/wp-content/plugins/` and activate it.
3. Go to **WP Admin → EVE Trade Tools → EVE Login**.
4. Follow the on-screen instructions to create an EVE developer application and enter your credentials.

== Frequently Asked Questions ==

= Do I need a separate EVE developer application? =
Yes, EVE Login requires its own application registered at developers.eveonline.com with Connection Type set to Authentication Only. No scopes are required.

= Does this plugin store passwords? =
No. Accounts created via EVE SSO are given a long random unusable password. Users authenticate exclusively through EVE Online.

= What does SSO-only mode do? =
When enabled, `wp-login.php?action=register` is blocked and redirected, and the standard Register link is replaced with the EVE SSO button. Existing accounts with passwords are not affected.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
