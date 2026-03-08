<?php
/**
 * Uninstall — EVE Trade Tools EVE Login
 *
 * Runs when the plugin is deleted via WP Admin → Plugins.
 * Removes all WordPress-side data owned by this plugin:
 *   - Plugin options
 *   - Per-user character link meta (all users)
 *   - State transients created during the OAuth flow
 *
 * Nothing outside WordPress (e.g. the external database managed by
 * ETT Price Helper) is ever touched here.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// ── Options ──────────────────────────────────────────────────────────────────

$options = [
    'ett_el_client_id',
    'ett_el_client_secret',
    'ett_el_client_secret_iv',
    'ett_el_client_secret_mac',
    'ett_el_sso_only',
];

foreach ($options as $option) {
    delete_option($option);
}

// ── User meta ─────────────────────────────────────────────────────────────────
// Delete character link and cached name for every user on the site.

$meta_keys = [
    'ett_el_character_id',
    'ett_el_character_name',
];

foreach ($meta_keys as $key) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- direct delete required in uninstall; caching irrelevant
    $wpdb->delete($wpdb->usermeta, ['meta_key' => $key], ['%s']);
}

// ── Transients ────────────────────────────────────────────────────────────────
// OAuth state tokens are short-lived (10 min) but clean them up anyway.
// Transients are stored in wp_options as _transient_{name} and
// _transient_timeout_{name}.

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query required for LIKE-pattern transient cleanup in uninstall
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_ett\_el\_state\_%'
        OR option_name LIKE '\_transient\_timeout\_ett\_el\_state\_%'"
);
