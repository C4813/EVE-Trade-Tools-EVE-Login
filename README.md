# EVE Trade Tools EVE Login

A WordPress plugin that lets visitors register and sign in using their EVE Online character via EVE SSO. New accounts are created automatically on first login. Existing WordPress users can link their EVE character to enable SSO login going forward.

Part of the EVE Trade Tools suite. Works standalone or alongside **[EVE Trade Tools Price Helper](https://github.com/C4813/EVE-Trade-Tools-Price-Helper)** and **[EVE Trade Tools Reprocessing Helper](https://github.com/C4813/EVE-Trade-Tools-Reprocess-Trading)** — if Price Helper is active, this plugin slots in as a tab on its shared admin page.

---

## Features

- **EVE SSO button on wp-login.php** — injected automatically above the username/password fields
- **Auto-registration** — accounts are created on first login with the EVE character name as the display name, a sanitised username, a subscriber role, and a long random unusable password
- **Character linking** — existing WordPress users can link their EVE character from their profile page or via shortcode, enabling SSO login without creating a new account
- **SSO-only mode** — optionally disables standard WordPress registration so accounts can only be created through EVE SSO; existing password-based accounts are unaffected
- **Multi-character protection** — a character cannot be linked to more than one WordPress account
- **Encrypted credential storage** — Client Secret is stored encrypted using AES-256-CBC with an HMAC-SHA256 MAC, derived from WordPress secret keys; compatible with ETT Price Helper's encryption so secrets are portable between plugins
- **Standalone or integrated** — runs without Price Helper using its own admin page; when Price Helper is active, registers as an "EVE Login" tab on the shared EVE Trade Tools admin page instead

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.0 or later |
| PHP | 7.4 or later |

**EVE Trade Tools Price Helper is optional.** This plugin is fully functional without it.

---

## Installation

1. Upload `ett-eve-login` to `/wp-content/plugins/` and activate it
2. Go to **WP Admin → EVE Trade Tools → EVE Login**
3. Create an EVE developer application at [developers.eveonline.com](https://developers.eveonline.com/applications):
   - Set **Connection Type** to **Authentication Only** — no scopes are required
   - Set the **Callback URL** to `https://yoursite.com/wp-admin/admin-post.php?action=ett_el_callback`
4. Enter the Client ID and Client Secret and click **Save Credentials**

---

## Shortcodes

### `[ett_eve_login_button]`

Renders the EVE SSO login button for logged-out visitors. Renders nothing if the visitor is already logged in. After a successful login or registration the user is returned to the page the shortcode is on.

### `[ett_eve_link_character]`

For logged-in WordPress users. Shows a button to connect their EVE character via SSO. Once linked, displays the connected character name with an option to unlink. If the user is not logged in, shows a login prompt.

---

## Flows

### Login / Registration

1. Visitor clicks the EVE SSO button on `wp-login.php` or via `[ett_eve_login_button]`
2. EVE SSO authenticates the character and returns to the callback URL
3. The character ID is extracted from the JWT access token payload
4. If a WordPress account is already linked to that character ID, the user is logged in
5. If no account exists, one is created automatically (subscriber role, random unusable password) and the user is logged in

### Character Linking

1. Logged-in user clicks the link button in their profile or via `[ett_eve_link_character]`
2. EVE SSO authenticates the character and returns to the callback URL
3. The character is checked — if it is already linked to a different account, the request is rejected
4. The character ID and name are saved to the user's meta, enabling SSO login from that point forward

### Unlink

Available from the user's WordPress profile page and from the `[ett_eve_link_character]` shortcode when a character is already linked. Removes the character association from the account.

---

## SSO Application Setup

This plugin requires its own EVE developer application — separate from any other ETT application. At [developers.eveonline.com](https://developers.eveonline.com/applications):

- **Connection Type:** Authentication Only
- **Scopes:** none
- **Callback URL:** `https://yoursite.com/wp-admin/admin-post.php?action=ett_el_callback`

No ESI scopes are needed. The plugin reads only the character's name and ID from the JWT payload of the access token returned during the OAuth exchange. No ESI API calls are made.

---

## SSO-Only Mode

When enabled in the plugin settings:

- `wp-login.php?action=register` is blocked and redirected to the login page
- The WordPress registration URL (used by the "Register" link in `wp-login.php` and `wp_registration_url()`) is replaced with the EVE SSO authorisation URL
- Standard password-based login and existing accounts with passwords are not affected

---

## Admin

Settings are managed under **WP Admin → EVE Trade Tools → EVE Login**.

- If **EVE Trade Tools Price Helper is active**, the EVE Login settings appear as a tab on Price Helper's shared admin page
- If **Price Helper is not active**, a standalone top-level "EVE Trade Tools" menu entry is created

The settings page shows the configured callback URL, a setup status indicator, step-by-step application creation instructions, and the credentials form.

---

## Data Storage

| Storage | Key | Content |
|---|---|---|
| `wp_options` | `ett_el_client_id` | EVE developer application Client ID |
| `wp_options` | `ett_el_client_secret` / `_iv` / `_mac` | AES-256-CBC encrypted Client Secret + IV + HMAC |
| `wp_options` | `ett_el_sso_only` | SSO-only registration flag (`0` or `1`) |
| `wp_usermeta` | `ett_el_character_id` | EVE character ID linked to a WordPress user |
| `wp_usermeta` | `ett_el_character_name` | EVE character name (display use only) |
| `wp_options` (transients) | `ett_el_state_{token}` | Short-lived OAuth CSRF state tokens (10 min TTL) |

### On uninstall

- All `ett_el_*` options are deleted
- `ett_el_character_id` and `ett_el_character_name` user meta are deleted for all users
- All `ett_el_state_*` transients are deleted

---

## Encryption

The Client Secret is encrypted at rest using AES-256-CBC with a per-encryption random IV and an HMAC-SHA256 MAC for authentication, with keys derived from WordPress's `AUTH_KEY` and `SECURE_AUTH_KEY` constants. The implementation is identical to ETT Price Helper's encryption — secrets are cross-readable between the two plugins without migration if both are active.

When Price Helper is active, `ETT_Crypto` is aliased to `ETT_EL_CryptoActive`. When it is not, the plugin's own `ETT_EL_Crypto` class is used and aliased instead.

---

## License

GPLv2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
