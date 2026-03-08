<?php
if (!defined('ABSPATH')) exit;

/**
 * Registers a tab on the EVE Trade Tools central settings page
 * (provided by a parent ETT plugin such as ETT Price Helper).
 * Handles saving credentials directly — no separate settings page needed.
 */
final class ETT_EL_Tab {

    public static function init(): void {
        add_action('ett_admin_tabs', [__CLASS__, 'register_tab']);
        add_action('admin_init',     [__CLASS__, 'save_settings']);
    }

    public static function register_tab(): void {
        if (!class_exists('ETT_Admin')) return;

        ETT_Admin::register_tab(
            'eve-login',
            'EVE Login',
            [__CLASS__, 'render_tab']
        );
    }

    /** Handle form submission when on this tab. */
    public static function save_settings(): void {
        if (!isset($_POST['ett_el_save_settings'])) return;
        if (!current_user_can('manage_options')) return;

        check_admin_referer('ett_el_save_settings');

        update_option('ett_el_client_id', sanitize_text_field(wp_unslash($_POST['ett_el_client_id'] ?? '')));

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

        // Redirect back to the tab with a saved flag to show a confirmation notice.
        $page_slug = class_exists('ETT_Admin') ? ETT_Admin::SLUG : 'ett-price-helper';
        wp_safe_redirect(add_query_arg(
            ['tab' => 'eve-login', 'ett_el_saved' => '1'],
            admin_url('admin.php?page=' . $page_slug)
        ));
        exit;
    }

    public static function render_tab(): void {
        $callback_url  = admin_url('admin-post.php?action=ett_el_callback');
        $client_id     = (string) get_option('ett_el_client_id',     '');
        $client_secret = ETT_EL_CryptoActive::decrypt_triplet(
                (string) get_option('ett_el_client_secret',     ''),
                (string) get_option('ett_el_client_secret_iv',  ''),
                (string) get_option('ett_el_client_secret_mac', '')
            );
        $configured    = $client_id !== '' && $client_secret !== '';
        $sso_only      = get_option('ett_el_sso_only', '0') === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $saved         = isset($_GET['ett_el_saved']);
        ?>

        <?php if ($saved) : ?>
            <div class="notice notice-success inline" style="margin:0 0 16px;"><p>Settings saved.</p></div>
        <?php endif; ?>

        <!-- Status -->
        <div class="ett-settings-grid">
            <div class="ett-card">
                <h2>Setup Status</h2>
                <?php if ($configured) : ?>
                    <div class="ett-statusline">
                        <span class="ett-dot ok"></span>
                        <span class="ett-ok">Client ID and Secret configured</span>
                    </div>
                    <p class="description" style="margin-top:10px;">
                        EVE Login is ready. Ensure the callback URL in the instructions below is registered in your EVE developer application.
                    </p>
                <?php else : ?>
                    <div class="ett-statusline">
                        <span class="ett-dot bad"></span>
                        <span class="ett-bad">Not configured</span>
                    </div>
                    <p class="description" style="margin-top:10px;">
                        Follow the instructions below to create an EVE developer application, then enter your credentials in the form below.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Setup instructions -->
        <div class="ett-card" style="margin-top:20px;max-width:780px;">
            <h2>Creating an EVE Developer Application</h2>
            <p>ETT EVE Login requires its own EVE developer application, separate from any other ETT application you may have.</p>
            <ol style="margin-left:20px;line-height:2.2;">
                <li>
                    Go to <a href="https://developers.eveonline.com/applications" target="_blank">developers.eveonline.com/applications</a>
                    and click <strong>Create New Application</strong>.
                </li>
                <li>
                    Give it a name (e.g. <em>My Site &mdash; EVE Login</em>) and a short description.
                </li>
                <li>
                    Set <strong>Connection Type</strong> to <strong>Authentication Only</strong>.
                    No scopes are required &mdash; this plugin only reads character identity from the login token.
                </li>
                <li>
                    Set the <strong>Callback URL</strong> to:<br />
                    <code><?php echo esc_html($callback_url); ?></code>
                </li>
                <li>
                    Click <strong>Create Application</strong>. You will be shown a <strong>Client ID</strong> and <strong>Secret Key</strong>.
                </li>
                <li>
                    Copy both values into the <strong>Credentials</strong> form below and click <strong>Save Credentials</strong>.
                </li>
            </ol>
        </div>

        <!-- Credentials form -->
        <div class="ett-card" style="margin-top:20px;max-width:600px;">
            <h2>Credentials</h2>
            <form method="post" action="">
                <?php wp_nonce_field('ett_el_save_settings'); ?>
                <input type="hidden" name="ett_el_save_settings" value="1" />
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
                                   placeholder="<?php echo $client_secret !== '' ? esc_attr('(saved — leave blank to keep)') : esc_attr('Client Secret'); ?>"
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
