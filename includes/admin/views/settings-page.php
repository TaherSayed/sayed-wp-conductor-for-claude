<?php
/**
 * Settings page view.
 *
 * @var array $settings
 *
 * @package WPCommander
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View-template locals, scoped to include().

$tool_groups = [
    __( 'Read', 'sayed-wp-conductor-for-claude' ) => [
        'site_info'        => 'site_info — public site info',
        'site_health'      => 'site_health — technical health snapshot (admin)',
        'posts_list'       => 'posts_list — list posts / pages',
        'posts_get'        => 'posts_get — single post',
        'posts_search'     => 'posts_search — search content',
        'media_list'       => 'media_list — list attachments',
        'comments_list'    => 'comments_list — list comments',
        'terms_list'       => 'terms_list — list taxonomy terms',
        'settings_get'     => 'settings_get — read site options',
    ],
    __( 'Write', 'sayed-wp-conductor-for-claude' ) => [
        'posts_create'      => 'posts_create — create post/page',
        'posts_update'      => 'posts_update — edit post/page',
        'posts_delete'      => 'posts_delete — trash post (permanent requires danger mode)',
        'media_upload'      => 'media_upload — upload image from URL (SSRF-guarded)',
        'media_delete'      => 'media_delete — delete attachment',
        'comments_moderate' => 'comments_moderate — approve/spam/trash comments',
        'terms_create'      => 'terms_create — add category / tag',
    ],
    __( 'Admin', 'sayed-wp-conductor-for-claude' ) => [
        'users_list'        => 'users_list — list users (PII!)',
        'users_create'      => 'users_create — create user',
        'users_update'      => 'users_update — update user role/email/name',
        'plugins_list'      => 'plugins_list — list installed plugins',
        'plugins_toggle'    => 'plugins_toggle — activate / deactivate plugin',
        'themes_list'       => 'themes_list — list installed themes',
        'themes_activate'   => 'themes_activate — switch active theme',
        'settings_update'   => 'settings_update — update whitelisted options',
    ],
];
?>
<div class="wrap">
    <h1 style="display:flex;align-items:center;gap:10px">
        <span class="dashicons dashicons-shield-alt" style="color:#2271b1;font-size:30px;width:30px;height:30px"></span>
        <?php esc_html_e( 'Sayed WP Conductor for Claude', 'sayed-wp-conductor-for-claude' ); ?>
    </h1>
    <p class="description" style="margin-top:-4px"><?php esc_html_e( 'Powered by Taher Sayed', 'sayed-wp-conductor-for-claude' ); ?> · v<?php echo esc_html( CMCP_VERSION ); ?></p>

    <div class="notice notice-info">
        <p>
            <strong><?php esc_html_e( 'Endpoint:', 'sayed-wp-conductor-for-claude' ); ?></strong>
            <code><?php echo esc_html( rest_url( CMCP_REST_NAMESPACE . '/rpc' ) ); ?></code>
            &nbsp;|&nbsp;
            <strong><?php esc_html_e( 'Discovery:', 'sayed-wp-conductor-for-claude' ); ?></strong>
            <code><?php echo esc_html( rest_url( CMCP_REST_NAMESPACE . '/discovery' ) ); ?></code>
        </p>
        <p><?php esc_html_e( 'Protocol version:', 'sayed-wp-conductor-for-claude' ); ?> <code><?php echo esc_html( CMCP_PROTOCOL_VERSION ); ?></code></p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'cmcp_settings_group' ); ?>

        <h2 class="title"><?php esc_html_e( 'Transport security', 'sayed-wp-conductor-for-claude' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Require HTTPS', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[require_https]" value="1" <?php checked( ! empty( $settings['require_https'] ) ); ?> />
                        <?php esc_html_e( 'Reject non-HTTPS requests (recommended; localhost is always allowed).', 'sayed-wp-conductor-for-claude' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Allowed origins', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <textarea name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[allowed_origins]" rows="4" cols="60" placeholder="https://claude.ai&#10;https://app.example.com"><?php echo esc_textarea( implode( "\n", (array) ( $settings['allowed_origins'] ?? [] ) ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One origin per line. Same-site is always allowed. Empty Origin headers (server-to-server clients) are accepted.', 'sayed-wp-conductor-for-claude' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Rate limit', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <input type="number" min="0" max="6000" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[rate_limit_per_min]" value="<?php echo esc_attr( (int) $settings['rate_limit_per_min'] ); ?>" />
                    <?php esc_html_e( 'requests / minute / token (0 = unlimited)', 'sayed-wp-conductor-for-claude' ); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Max request size', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <input type="number" min="4096" max="<?php echo esc_attr( 8 * 1024 * 1024 ); ?>" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[max_request_bytes]" value="<?php echo esc_attr( (int) $settings['max_request_bytes'] ); ?>" />
                    <?php esc_html_e( 'bytes (raise this if uploading large media)', 'sayed-wp-conductor-for-claude' ); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Trust reverse proxy', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[trust_proxy]" value="1" <?php checked( ! empty( $settings['trust_proxy'] ) ); ?> />
                        <?php esc_html_e( 'Honor X-Forwarded-Proto, X-Forwarded-For and CF-Connecting-IP headers.', 'sayed-wp-conductor-for-claude' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Only enable this when WordPress sits behind a trusted reverse proxy (Cloudflare, nginx, load balancer). With this off, the plugin uses REMOTE_ADDR — safer when traffic reaches WP directly. With it on, the real client IP can be used for brute-force lockouts even behind a proxy.', 'sayed-wp-conductor-for-claude' ); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e( 'Notifications (webhook)', 'sayed-wp-conductor-for-claude' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="cmcp-webhook-url"><?php esc_html_e( 'Webhook URL', 'sayed-wp-conductor-for-claude' ); ?></label></th>
                <td>
                    <input id="cmcp-webhook-url" type="url" class="regular-text" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[webhook_url]" value="<?php echo esc_attr( (string) ( $settings['webhook_url'] ?? '' ) ); ?>" placeholder="https://hooks.slack.com/services/…" />
                    <p class="description"><?php esc_html_e( 'POSTed with a JSON body { event, timestamp, site, data } on brute-force lockouts and new OAuth client registrations. Leave empty to disable.', 'sayed-wp-conductor-for-claude' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmcp-webhook-secret"><?php esc_html_e( 'Webhook secret (optional)', 'sayed-wp-conductor-for-claude' ); ?></label></th>
                <td>
                    <input id="cmcp-webhook-secret" type="text" class="regular-text" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[webhook_secret]" value="<?php echo esc_attr( (string) ( $settings['webhook_secret'] ?? '' ) ); ?>" autocomplete="off" />
                    <p class="description"><?php esc_html_e( 'Shared secret. When set, the request includes an HMAC-SHA256 signature in X-Commander-Signature so the receiver can verify the call really came from this site.', 'sayed-wp-conductor-for-claude' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"></th>
                <td>
                    <button type="button" id="cmcp-webhook-test" class="button"><?php esc_html_e( 'Send test ping', 'sayed-wp-conductor-for-claude' ); ?></button>
                    <span id="cmcp-webhook-test-result" style="margin-left:10px;font-size:12px;color:#646970"></span>
                    <p class="description"><?php esc_html_e( 'Fires a test event so you can verify the receiver accepts it. Saves any unsaved changes first.', 'sayed-wp-conductor-for-claude' ); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e( 'OAuth', 'sayed-wp-conductor-for-claude' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr style="background:#fff7e6">
                <th scope="row" style="color:#b26a00"><?php esc_html_e( 'Dynamic Client Registration', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[allow_dcr]" value="1" <?php checked( ! empty( $settings['allow_dcr'] ) ); ?> />
                        <strong style="color:#b26a00"><?php esc_html_e( 'Allow anonymous OAuth client registration (RFC 7591).', 'sayed-wp-conductor-for-claude' ); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e( 'Required for one-click connect from clients like Claude Desktop. Leave OFF unless you need it: when on, anyone on the internet can register an OAuth client. Tokens are still only issued after an admin approves the consent screen on this site.', 'sayed-wp-conductor-for-claude' ); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e( 'Audit, alerts & destructive operations', 'sayed-wp-conductor-for-claude' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Audit log', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[enable_audit_log]" value="1" <?php checked( ! empty( $settings['enable_audit_log'] ) ); ?> />
                        <?php esc_html_e( 'Enable audit logging of every MCP call.', 'sayed-wp-conductor-for-claude' ); ?>
                    </label>
                    <br>
                    <label>
                        <?php esc_html_e( 'Keep logs for', 'sayed-wp-conductor-for-claude' ); ?>
                        <input type="number" min="1" max="3650" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[log_retention_days]" value="<?php echo esc_attr( (int) $settings['log_retention_days'] ); ?>" />
                        <?php esc_html_e( 'days.', 'sayed-wp-conductor-for-claude' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Block scanner user-agents', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[block_bad_uas]" value="1" <?php checked( ! empty( $settings['block_bad_uas'] ) ); ?> />
                        <?php esc_html_e( 'Reject sqlmap, nikto, wpscan, nmap and other known scanner UAs.', 'sayed-wp-conductor-for-claude' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Email alerts', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[notify_admin_email]" value="1" <?php checked( ! empty( $settings['notify_admin_email'] ) ); ?> />
                        <?php esc_html_e( 'Email site admin when an IP is locked out for brute force.', 'sayed-wp-conductor-for-claude' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Rate-limited to one email per IP per hour. Sent to:', 'sayed-wp-conductor-for-claude' ); ?> <code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Token rotation warning', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <?php esc_html_e( 'Warn on dashboard if a personal token is older than', 'sayed-wp-conductor-for-claude' ); ?>
                    <input type="number" min="7" max="730" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[rotation_warn_days]" value="<?php echo esc_attr( (int) ( $settings['rotation_warn_days'] ?? 90 ) ); ?>" /> <?php esc_html_e( 'days.', 'sayed-wp-conductor-for-claude' ); ?>
                </td>
            </tr>
            <tr style="background:#fff7e6">
                <th scope="row" style="color:#b26a00"><?php esc_html_e( 'Allow destructive ops', 'sayed-wp-conductor-for-claude' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[allow_destructive]" value="1" <?php checked( ! empty( $settings['allow_destructive'] ) ); ?> />
                        <strong style="color:#b26a00"><?php esc_html_e( 'I understand — permit permanent deletes and sensitive option writes (siteurl, home, admin_email, default_role, permalink_structure, …).', 'sayed-wp-conductor-for-claude' ); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e( 'Keep this off unless you specifically need it. The plugin still requires admin scope + WP capability for those calls — this is an extra interlock.', 'sayed-wp-conductor-for-claude' ); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e( 'Enabled tools', 'sayed-wp-conductor-for-claude' ); ?></h2>
        <table class="form-table" role="presentation">
            <?php foreach ( $tool_groups as $group => $tools ) : ?>
            <tr>
                <th scope="row"><?php echo esc_html( $group ); ?></th>
                <td>
                    <?php foreach ( $tools as $slug => $desc ) : ?>
                        <label style="display:block">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( CMCP\Plugin::OPT_SETTINGS ); ?>[enabled_tools][]"
                                   value="<?php echo esc_attr( $slug ); ?>"
                                   <?php checked( in_array( $slug, (array) ( $settings['enabled_tools'] ?? [] ), true ) ); ?> />
                            <code><?php echo esc_html( $slug ); ?></code> — <?php echo esc_html( substr( $desc, strpos( $desc, '—' ) + 1 ) ); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
