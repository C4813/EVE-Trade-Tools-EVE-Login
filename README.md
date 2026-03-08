# EVE Trade Tools — EVE Login

Lets visitors register and log in to your WordPress site using EVE Online SSO. Existing WordPress users can also link their EVE character to their account to enable SSO login going forward.

## Do I need a new developer application?

**No, if you already have the EVE Trade Tools Reprocess Trading plugin installed.** Both plugins share the same EVE application credentials automatically. All you need to do is add the new callback URL to your existing application on the EVE developer portal.

If you are installing this plugin standalone (without the Reprocess Trading plugin), you will need to create a developer application.

## Setup

### 1. Add the callback URL to your EVE developer application

Go to https://developers.eveonline.com/applications and open your existing application (or create a new one).

Add the following as a **Callback URL**:

```
https://yoursite.com/wp-admin/admin-post.php?action=ett_el_callback
```

No additional scopes are required — this plugin only reads character identity from the login token.

### 2. Credentials (standalone only)

If you are **not** using the Reprocess Trading plugin, go to **Settings → ETT EVE Login** and enter your Client ID and Client Secret.

If the Reprocess Trading plugin is active, the settings page will confirm that credentials are being shared and the credentials fields will not be shown.

### 3. EVE SSO button image (optional)

Download the official button image from CCP and save it as `assets/eve-sso.png` inside the plugin folder:

```
https://web.ccpgamescdn.com/eveonlineassets/developers/eve-sso-login-black-small.png
```

If the file is absent the button renders as a plain text link instead.

## How it works

**New visitors (login / register):**
1. Visitor clicks the EVE SSO button on wp-login.php (or via shortcode).
2. They authorise on EVE Online.
3. On first visit, a subscriber account is created automatically (username derived from character name, random unusable password).
4. On return visits they are simply logged back in.

**Existing WordPress users (link character):**
1. The logged-in user visits a page containing [ett_eve_link_character].
2. They click the button and authorise on EVE Online.
3. Their EVE character is linked to their existing WordPress account.
4. They can now log in via EVE SSO as well as their normal password.
5. The same shortcode shows the linked character name and an Unlink character option.

## Shortcodes

[ett_eve_login_button]      — EVE SSO login button. Hidden when already logged in.
[ett_eve_link_character]    — For logged-in users: link or unlink their EVE character.
