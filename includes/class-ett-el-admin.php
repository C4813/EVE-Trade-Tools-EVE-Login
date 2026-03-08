<?php
if (!defined('ABSPATH')) exit;

/**
 * Standalone admin page — only registered when the ETT_Admin tab framework
 * (provided by ETT Price Helper) is not present.
 *
 * Registers a top-level "EVE Trade Tools" menu page with the same title,
 * icon, and tab chrome that Price Helper uses, so the experience is identical
 * whether or not Price Helper is installed. If Price Helper is later activated,
 * it takes ownership of the top-level menu and the EVE Login tab slots in
 * alongside its own tabs automatically via ETT_EL_Tab.
 */
final class ETT_EL_Admin {

    /** Must match ETT_Admin::SLUG so WP deduplicates the menu entry if both are active. */
    const SLUG = 'ett-price-helper';

    public static function init(): void {
        if (class_exists('ETT_Admin')) return;

        add_action('admin_menu',                      [__CLASS__, 'add_menu']);
        add_action('admin_enqueue_scripts',           [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_ett_el_save_settings', [__CLASS__, 'handle_save']);
    }

    public static function add_menu(): void {
        add_menu_page(
            'EVE Trade Tools',
            'EVE Trade Tools',
            'manage_options',
            self::SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-database',
            58
        );
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_' . self::SLUG) return;
        wp_enqueue_style('ett-el-admin', ETT_EL_URL . 'assets/frontend.css', [], ETT_EL_VERSION);
    }

    public static function handle_save(): void {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
        check_admin_referer('ett_el_save_settings');

        update_option('ett_el_client_id', sanitize_text_field(wp_unslash($_POST['ett_el_client_id'] ?? '')), false);

        // Only overwrite the stored secret when a new value is explicitly provided.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password; do not mangle characters
        $new_secret = trim((string) wp_unslash($_POST['ett_el_client_secret'] ?? ''));
        if ($new_secret !== '') {
            $enc = ETT_EL_CryptoActive::encrypt_triplet($new_secret);
            update_option('ett_el_client_secret',     $enc['ciphertext'], false);
            update_option('ett_el_client_secret_iv',  $enc['iv'],         false);
            update_option('ett_el_client_secret_mac', $enc['mac'],        false);
        }

        update_option('ett_el_sso_only', isset($_POST['ett_el_sso_only']) ? '1' : '0');

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'tab' => 'eve-login', 'ett_el_saved' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing param
        $active_tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'eve-login'));
        ?>
        <div class="wrap">
            <h1>EVE Trade Tools</h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'tab' => 'eve-login'], admin_url('admin.php'))); ?>"
                   class="nav-tab<?php echo $active_tab === 'eve-login' ? ' nav-tab-active' : ''; ?>">
                    EVE Login
                </a>
            </nav>

            <div class="ett-tab-panel">
                <?php self::render_eve_login_tab(); ?>
            </div>
        </div>
        <?php
    }

    private static function render_eve_login_tab(): void {
        $callback_url = admin_url('admin-post.php?action=ett_el_callback');
        $client_id    = (string) get_option('ett_el_client_id', '');
        $secret_saved = get_option('ett_el_client_secret', '') !== '';
        $configured   = $client_id !== '' && $secret_saved;
        $sso_only     = get_option('ett_el_sso_only', '0') === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $saved        = !empty($_GET['ett_el_saved']);
        ?>

        <?php if ($saved): ?>
            <div class="notice notice-success inline" style="margin:12px 0;"><p>Settings saved.</p></div>
        <?php endif; ?>

        <div class="ett-settings-grid" style="margin-top:16px;">

            <div class="ett-card">
                <h2>Setup Status</h2>
                <?php if ($configured): ?>
                    <div class="ett-statusline">
                        <span class="ett-dot ok"></span>
                        <span class="ett-ok">Client ID and Secret configured</span>
                    </div>
                    <p class="description" style="margin-top:10px;">
                        EVE Login is ready. Ensure the callback URL below is registered in your EVE developer application.
                    </p>
                <?php else: ?>
                    <div class="ett-statusline">
                        <span class="ett-dot bad"></span>
                        <span class="ett-bad">Not configured</span>
                    </div>
                    <p class="description" style="margin-top:10px;">
                        Follow the instructions below to create an EVE developer application, then enter your credentials.
                    </p>
                <?php endif; ?>
            </div>

            <div class="ett-card">
                <h2>Application Setup</h2>
                <p class="description">
                    Create an application at
                    <a href="https://developers.eveonline.com/applications" target="_blank" rel="noopener">developers.eveonline.com</a>.
                    Set <strong>Connection Type</strong> to <strong>Authentication Only</strong> — no scopes required.
                </p>
                <p class="description" style="margin-top:10px;">Callback URL:</p>
                <p><code><?php echo esc_html($callback_url); ?></code></p>
            </div>

        </div>

        <div class="ett-card" style="margin-top:20px;max-width:600px;">
            <h2>Credentials</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ett_el_save_settings'); ?>
                <input type="hidden" name="action" value="ett_el_save_settings" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ett_el_client_id">Client ID</label></th>
                        <td>
                            <input type="text"
                                   id="ett_el_client_id"
                                   name="ett_el_client_id"
                                   value="<?php echo esc_attr($client_id); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ett_el_client_secret">Client Secret</label></th>
                        <td>
                            <input type="password"
                                   id="ett_el_client_secret"
                                   name="ett_el_client_secret"
                                   value=""
                                   placeholder="<?php echo $secret_saved ? esc_attr('(saved — leave blank to keep)') : esc_attr('Client Secret'); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ett_el_sso_only">SSO-Only Registration</label></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="ett_el_sso_only"
                                       name="ett_el_sso_only"
                                       value="1"
                                       <?php checked($sso_only); ?> />
                                Disable standard WordPress registration &mdash; users can only register via EVE SSO
                            </label>
                            <p class="description">
                                When enabled, <code>wp-login.php?action=register</code> is blocked and redirected,
                                and the Register link is replaced with the EVE SSO login button.
                                Existing accounts with passwords are unaffected.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Credentials'); ?>
            </form>
        </div>
        <?php
    }
}
