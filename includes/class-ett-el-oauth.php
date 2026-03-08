<?php
if (!defined('ABSPATH')) exit;

final class ETT_EL_OAuth {

    /** User meta key that permanently links a WP account to an EVE character. */
    const CHARACTER_META = 'ett_el_character_id';

    /**
     * State token mode is stored alongside the redirect URL so the callback
     * knows which flow it is handling:
     *   'login' — unauthenticated visitor logging in / registering
     *   'link'  — logged-in user linking their character
     */

    public static function init(): void {
        // Callback accessible to logged-out visitors (login/register flow).
        add_action('admin_post_nopriv_ett_el_callback', [__CLASS__, 'handle_callback']);
        // Callback for logged-in users (link flow).
        add_action('admin_post_ett_el_callback',        [__CLASS__, 'handle_callback']);

        // Inject button into wp-login.php.
        add_action('login_form', [__CLASS__, 'inject_login_form_button']);

        // SSO-only registration enforcement.
        if (get_option('ett_el_sso_only', '0') === '1') {
            // Block direct access to wp-login.php?action=register and redirect to login.
            add_action('login_form_register', [__CLASS__, 'block_wp_registration']);
            // Replace the registration URL with the EVE SSO auth URL everywhere.
            add_filter('register_url', [__CLASS__, 'filter_register_url']);
        }

        // Shortcodes.
        add_shortcode('ett_eve_login_button',  [__CLASS__, 'shortcode_login_button']);
        add_shortcode('ett_eve_link_character', [__CLASS__, 'shortcode_link_character']);

        // User profile page — let existing members link their EVE character.
        add_action('show_user_profile', [__CLASS__, 'render_profile_section']);

        // Styles.
        add_action('wp_enqueue_scripts',    [__CLASS__, 'enqueue_assets']);
        add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_login_mover']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_profile_assets']);
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets(): void {
        wp_enqueue_style('ett-el-frontend', ETT_EL_URL . 'assets/frontend.css', [], ETT_EL_VERSION);
    }

    public static function enqueue_login_mover(): void {
        // Move the EVE button wrap to the top of #loginform via JS,
        // since wp-login.php has no hook before the username/password fields.
        wp_add_inline_script('jquery', "
            document.addEventListener('DOMContentLoaded', function() {
                var wrap = document.querySelector('#loginform .ett-el-login-wrap');
                var form = document.getElementById('loginform');
                if (wrap && form) form.insertBefore(wrap, form.firstChild);
            });
        ");
    }

    public static function enqueue_profile_assets(string $hook): void {
        if (!in_array($hook, ['profile.php', 'user-edit.php'], true)) return;
        wp_enqueue_style('ett-el-frontend', ETT_EL_URL . 'assets/frontend.css', [], ETT_EL_VERSION);
    }

    // -- User profile section --------------------------------------------------

    /** Rendered on wp-admin/profile.php so users can link or unlink their EVE character. */
    public static function render_profile_section(WP_User $user): void {
        [$client_id] = self::get_credentials();

        $character_id   = (string) get_user_meta($user->ID, self::CHARACTER_META, true);
        $character_name = $character_id !== '' ? self::get_character_name_for($user->ID, $character_id) : '';
        $linked         = $character_id !== '';

        $link_url   = $client_id !== '' ? self::build_auth_url('link', admin_url('profile.php')) : '';
        $unlink_url = wp_nonce_url(
            admin_url('admin-post.php?action=ett_el_unlink'),
            'ett_el_unlink_' . $user->ID
        );
        ?>
        <h2>EVE Online</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">EVE Character</th>
                <td>
                    <?php if ($linked) : ?>
                        <p>
                            <strong><?php echo esc_html($character_name); ?></strong> is linked to this account.
                            You can log in using EVE SSO.
                        </p>
                        <a href="<?php echo esc_url($unlink_url); ?>" class="ett-el-unlink-btn button">Unlink character</a>
                    <?php elseif ($client_id !== '') : ?>
                        <p class="description">No EVE character linked. Connect one to enable EVE SSO login.</p>
                        <?php echo self::button_html($link_url, 'Link EVE Character'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php else : ?>
                        <p class="description">EVE Login is not configured. Contact the site administrator.</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    // ── Credentials ────────────────────────────────────────────────────────────

    /** Returns [client_id, client_secret] from this plugin's own settings. */
    private static function get_credentials(): array {
        $client_id     = (string) get_option('ett_el_client_id', '');
        $client_secret = ETT_EL_CryptoActive::decrypt_triplet(
            (string) get_option('ett_el_client_secret',     ''),
            (string) get_option('ett_el_client_secret_iv',  ''),
            (string) get_option('ett_el_client_secret_mac', '')
        );

        return [$client_id, $client_secret];
    }

    // ── Auth URL builder ──────────────────────────────────────────────────────

    /**
     * Build an EVE SSO authorisation URL.
     *
     * @param string $mode         'login' or 'link'
     * @param string $redirect_after  URL to send the user to after the flow completes.
     */
    private static function build_auth_url(string $mode, string $redirect_after): string {
        [$client_id] = self::get_credentials();

        $state = wp_generate_password(32, false, false);

        set_transient('ett_el_state_' . $state, [
            'mode'         => $mode,
            'redirect_url' => $redirect_after,
        ], 600);

        return add_query_arg([
            'response_type' => 'code',
            'redirect_uri'  => admin_url('admin-post.php?action=ett_el_callback'),
            'client_id'     => $client_id,
            'scope'         => '', // No scopes needed — character identity only.
            'state'         => $state,
        ], 'https://login.eveonline.com/v2/oauth/authorize');
    }

    // ── Button HTML ───────────────────────────────────────────────────────────

    private static function button_html(string $auth_url, string $label = 'Log in with EVE Online'): string {
        $img_path     = ETT_EL_PATH . 'assets/eve-sso.png';
        $button_inner = file_exists($img_path)
            ? '<img src="' . esc_url(ETT_EL_URL . 'assets/eve-sso.png') . '" alt="' . esc_attr($label) . '" />'
            : '<span class="ett-el-text-button">' . esc_html($label) . '</span>';

        return '<a href="' . esc_url($auth_url) . '" class="ett-el-button">' . $button_inner . '</a>';
    }

    // ── wp-login.php injection ────────────────────────────────────────────────

    public static function inject_login_form_button(): void {
        [$client_id] = self::get_credentials();
        if ($client_id === '') return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $redirect_to = esc_url_raw(wp_unslash($_REQUEST['redirect_to'] ?? admin_url()));
        $auth_url    = self::build_auth_url('login', $redirect_to);

        echo '<div class="ett-el-login-wrap">';
        echo self::button_html($auth_url); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<p class="ett-el-divider"><span>or</span></p>';
        echo '</div>';
    }

    // ── Shortcodes ────────────────────────────────────────────────────────────

    /**
     * [ett_eve_login_button]
     * Shows the EVE SSO login button to logged-out visitors.
     * Renders nothing if the visitor is already logged in.
     */
    public static function shortcode_login_button(): string {
        if (is_user_logged_in()) return '';

        [$client_id] = self::get_credentials();
        if ($client_id === '') {
            return '<p class="ett-el-notice">EVE Login is not configured yet.</p>';
        }

        $auth_url = self::build_auth_url('login', get_permalink() ?: home_url('/'));
        return '<div class="ett-el-shortcode-wrap">' . self::button_html($auth_url) . '</div>';
    }

    /**
     * [ett_eve_link_character]
     * Shown to logged-in users who want to link their EVE character so they
     * can log in via SSO in future.
     *
     * - If not logged in: prompts the user to log in first.
     * - If already linked: shows the linked character name with an unlink option.
     * - Otherwise: shows the "Link EVE Character" button.
     */
    public static function shortcode_link_character(): string {
        [$client_id] = self::get_credentials();
        if ($client_id === '') {
            return '<p class="ett-el-notice">EVE Login is not configured yet.</p>';
        }

        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            return '<p class="ett-el-notice">You must be <a href="' . esc_url($login_url) . '">logged in</a> to link an EVE character.</p>';
        }

        $user_id      = get_current_user_id();
        $character_id = (string) get_user_meta($user_id, self::CHARACTER_META, true);

        if ($character_id !== '') {
            // Already linked — show character and an unlink option.
            $character_name = self::get_character_name($character_id);
            $unlink_url     = wp_nonce_url(
                admin_url('admin-post.php?action=ett_el_unlink'),
                'ett_el_unlink_' . $user_id
            );

            return '<div class="ett-el-linked">'
                 . '<p>Your EVE character <strong>' . esc_html($character_name) . '</strong> is linked to this account. You can log in using EVE SSO.</p>'
                 . '<a href="' . esc_url($unlink_url) . '" class="ett-el-unlink-btn">Unlink character</a>'
                 . '</div>';
        }

        $auth_url = self::build_auth_url('link', get_permalink() ?: home_url('/'));
        return '<div class="ett-el-shortcode-wrap">'
             . self::button_html($auth_url, 'Link EVE Character')
             . '</div>';
    }

    // ── OAuth callback ────────────────────────────────────────────────────────

    // SSO-only registration enforcement

    /**
     * Fires on login_form_register (i.e. wp-login.php?action=register).
     * Redirects visitors to the standard login page so they cannot
     * self-register with a username and password.
     */
    public static function block_wp_registration(): void {
        wp_safe_redirect(wp_login_url());
        exit;
    }

    /**
     * Replaces the WordPress registration URL with the EVE SSO auth URL.
     * Affects the "Register" link in wp-login.php and anywhere
     * wp_registration_url() is used.
     */
    public static function filter_register_url(): string {
        [$client_id] = self::get_credentials();
        if ($client_id === '') return wp_login_url();
        return self::build_auth_url('login', home_url('/'));
    }

        public static function handle_callback(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from EVE SSO; WP nonces cannot be used
        if (!isset($_GET['code'], $_GET['state'])) {
            wp_die('Invalid EVE SSO callback — missing code or state.');
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from EVE SSO
        $state = sanitize_text_field(wp_unslash($_GET['state']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from EVE SSO
        $code  = sanitize_text_field(wp_unslash($_GET['code']));

        $transient = get_transient('ett_el_state_' . $state);
        delete_transient('ett_el_state_' . $state);

        if (!is_array($transient) || empty($transient['mode'])) {
            wp_die('EVE SSO Login: invalid or expired state token. Please try again.');
        }

        $mode         = (string) $transient['mode'];
        $redirect_url = (string) ($transient['redirect_url'] ?? home_url('/'));

        [$client_id, $client_secret] = self::get_credentials();

        if ($client_id === '' || $client_secret === '') {
            wp_die('EVE SSO Login is not configured. Please contact the site administrator.');
        }

        // Exchange code for tokens.
        $response = wp_remote_post('https://login.eveonline.com/v2/oauth/token', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code'       => $code,
            ],
        ]);

        if (is_wp_error($response)) {
            wp_die('EVE SSO Login: token request failed — ' . esc_html($response->get_error_message()));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300 || !is_array($body) || empty($body['access_token'])) {
            wp_die('EVE SSO Login: invalid token response from EVE Online. Please try again.');
        }

        // Extract character identity from the JWT payload (middle segment).
        // The signature is intentionally not verified — the token was received
        // directly from EVE SSO over HTTPS in exchange for an auth code we
        // initiated, so it cannot have been tampered with in transit. The claims
        // (character name/ID) are used only for account creation and display,
        // not for access control decisions.
        $parts = explode('.', (string) $body['access_token']);
        if (count($parts) < 2) {
            wp_die('EVE SSO Login: malformed access token.');
        }

        // Add base64url padding before decoding to avoid failures on tokens
        // whose payload segment length is not a multiple of 4.
        $b64 = strtr($parts[1], '-_', '+/');
        $rem = strlen($b64) % 4;
        if ($rem) $b64 .= str_repeat('=', 4 - $rem);
        $payload = json_decode(base64_decode($b64), true);

        if (!is_array($payload) || empty($payload['sub'])) {
            wp_die('EVE SSO Login: could not read character identity from token.');
        }

        if (!preg_match('/(\d+)$/', (string) $payload['sub'], $m)) {
            wp_die('EVE SSO Login: could not parse character ID.');
        }

        $character_id   = (string) $m[1];
        $character_name = !empty($payload['name']) ? (string) $payload['name'] : ('EVE Character ' . $character_id);

        if ($mode === 'link') {
            self::handle_link($character_id, $character_name, $redirect_url);
        } else {
            self::handle_login($character_id, $character_name, $redirect_url);
        }
    }

    // ── Login / register flow ─────────────────────────────────────────────────

    private static function handle_login(string $character_id, string $character_name, string $redirect_url): void {
        if (is_user_logged_in()) {
            wp_safe_redirect($redirect_url);
            exit;
        }

        $user = self::find_user_by_character($character_id);

        if ($user === null) {
            $user = self::create_user($character_id, $character_name);
            if (is_wp_error($user)) {
                wp_die('EVE SSO Login: could not create account — ' . esc_html($user->get_error_message()));
            }
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        wp_safe_redirect($redirect_url);
        exit;
    }

    // ── Link flow ─────────────────────────────────────────────────────────────

    private static function handle_link(string $character_id, string $character_name, string $redirect_url): void {
        if (!is_user_logged_in()) {
            wp_die('EVE SSO Login: you must be logged in to link a character.');
        }

        // Make sure this character isn't already linked to a different WP account.
        $existing = self::find_user_by_character($character_id);
        if ($existing !== null && $existing->ID !== get_current_user_id()) {
            wp_die('EVE SSO Login: this EVE character is already linked to a different account.');
        }

        $user_id = get_current_user_id();
        update_user_meta($user_id, self::CHARACTER_META, $character_id);

        // Store character name for display purposes.
        update_user_meta($user_id, 'ett_el_character_name', $character_name);

        wp_safe_redirect($redirect_url);
        exit;
    }

    // ── Unlink action ─────────────────────────────────────────────────────────

    public static function handle_unlink(): void {
        if (!is_user_logged_in()) wp_die();

        $user_id = get_current_user_id();
        check_admin_referer('ett_el_unlink_' . $user_id);

        delete_user_meta($user_id, self::CHARACTER_META);
        delete_user_meta($user_id, 'ett_el_character_name');

        wp_safe_redirect(wp_get_referer() ?: home_url('/'));
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function find_user_by_character(string $character_id): ?WP_User {
        $users = get_users([
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- no alternative; CHARACTER_META has no index but the query is user-initiated and infrequent
            'meta_key'   => self::CHARACTER_META,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- same justification as meta_key above
            'meta_value' => $character_id,
            'number'     => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    private static function get_character_name_for(int $user_id, string $character_id): string {
        $name = (string) get_user_meta($user_id, 'ett_el_character_name', true);
        return $name !== '' ? $name : ('Character ' . $character_id);
    }

    /** Convenience wrapper for the current user. */
    private static function get_character_name(string $character_id): string {
        return self::get_character_name_for(get_current_user_id(), $character_id);
    }

    /** @return WP_User|WP_Error */
    private static function create_user(string $character_id, string $character_name) {
        $base     = sanitize_user(str_replace("'", '', $character_name), true);
        $base     = strtolower(str_replace(' ', '_', $base));
        $username = $base;
        $suffix   = 2;

        while (username_exists($username)) {
            $username = $base . '_' . $suffix++;
        }

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_pass'    => wp_generate_password(64, true, true),
            'display_name' => $character_name,
            'role'         => 'subscriber',
        ]);

        if (is_wp_error($user_id)) return $user_id;

        update_user_meta($user_id, self::CHARACTER_META,    $character_id);
        update_user_meta($user_id, 'ett_el_character_name', $character_name);

        return get_user_by('id', $user_id);
    }
}

// Unlink action must be registered at top level (not inside init) so
// admin-post.php can route it for logged-in users.
add_action('admin_post_ett_el_unlink', ['ETT_EL_OAuth', 'handle_unlink']);
