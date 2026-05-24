<?php
/**
 * Outbound webhook notifier.
 *
 * Sends a small JSON payload to the admin-configured webhook URL on
 * security-relevant events (brute-force lockout, OAuth client registration,
 * destructive operations). Optional shared secret produces an HMAC-SHA256
 * signature in the X-Commander-Signature header so the receiver can verify
 * the call really came from this site.
 *
 * Calls are dispatched via wp_schedule_single_event so they never block the
 * request that triggered them. If WP-Cron is disabled, falls back to a
 * best-effort sync wp_remote_post with a short timeout.
 *
 * @package WPCommander
 */

namespace CMCP;

defined( 'ABSPATH' ) || exit;

final class Notifier {

    public const EVENT_LOCKOUT      = 'brute_force.lockout';
    public const EVENT_OAUTH_CLIENT = 'oauth.client_registered';
    public const EVENT_DESTRUCTIVE  = 'tool.destructive_executed';
    public const EVENT_TEST         = 'test.ping';

    /**
     * Public entry point. Queues a webhook send for `$event` with `$data`.
     */
    public static function notify( string $event, array $data ): void {
        $settings = Plugin::get_settings();
        $url      = (string) ( $settings['webhook_url'] ?? '' );
        if ( $url === '' || ! self::is_valid_url( $url ) ) {
            return;
        }

        $payload = [
            'event'     => $event,
            'timestamp' => gmdate( 'c' ),
            'site'      => home_url( '/' ),
            'data'      => $data,
        ];

        $args = [ $url, $payload, (string) ( $settings['webhook_secret'] ?? '' ) ];

        // Try async via WP-Cron; fall back to sync if cron is disabled.
        if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
            wp_schedule_single_event( time() + 1, 'cmcp_send_webhook', $args );
        } else {
            self::send_now( ...$args );
        }
    }

    /**
     * Actually deliver the webhook. Hooked to `cmcp_send_webhook`.
     */
    public static function send_now( string $url, array $payload, string $secret = '' ): void {
        $body    = wp_json_encode( $payload );
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent'   => 'Commander/' . CMCP_VERSION . ' (+' . home_url( '/' ) . ')',
        ];
        if ( $secret !== '' && $body !== false ) {
            $headers['X-Commander-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
        }
        $headers['X-Commander-Event'] = (string) ( $payload['event'] ?? '' );

        $resp = wp_remote_post( $url, [
            'timeout'     => 5,
            'redirection' => 2,
            'blocking'    => true,
            'sslverify'   => true,
            'headers'     => $headers,
            'body'        => $body,
        ] );

        $code = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );
        $note = is_wp_error( $resp )
            ? 'webhook error: ' . $resp->get_error_message()
            : 'webhook delivered: HTTP ' . $code;

        Logger::log( [
            'method'      => '(webhook)',
            'success'     => ( $code >= 200 && $code < 300 ) ? 1 : 0,
            'status_code' => $code,
            'note'        => mb_substr( $note, 0, 240 ),
        ] );
    }

    /** Synchronous test-fire used by the "Send test webhook" admin button. */
    public static function send_test(): array {
        $settings = Plugin::get_settings();
        $url      = (string) ( $settings['webhook_url'] ?? '' );
        if ( $url === '' ) {
            return [ 'ok' => false, 'message' => __( 'No webhook URL configured.', 'mcp-for-claude' ) ];
        }
        if ( ! self::is_valid_url( $url ) ) {
            return [ 'ok' => false, 'message' => __( 'Invalid webhook URL.', 'mcp-for-claude' ) ];
        }
        $payload = [
            'event'     => self::EVENT_TEST,
            'timestamp' => gmdate( 'c' ),
            'site'      => home_url( '/' ),
            'data'      => [ 'message' => 'Test ping from Commander.' ],
        ];
        $body    = wp_json_encode( $payload );
        $secret  = (string) ( $settings['webhook_secret'] ?? '' );
        $headers = [
            'Content-Type'       => 'application/json',
            'User-Agent'         => 'Commander/' . CMCP_VERSION,
            'X-Commander-Event'  => self::EVENT_TEST,
        ];
        if ( $secret !== '' && $body !== false ) {
            $headers['X-Commander-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
        }
        $start = microtime( true );
        $resp  = wp_remote_post( $url, [
            'timeout'   => 8,
            'sslverify' => true,
            'headers'   => $headers,
            'body'      => $body,
        ] );
        $ms = (int) round( ( microtime( true ) - $start ) * 1000 );
        if ( is_wp_error( $resp ) ) {
            return [ 'ok' => false, 'message' => $resp->get_error_message(), 'latency_ms' => $ms ];
        }
        $code = (int) wp_remote_retrieve_response_code( $resp );
        return [
            'ok'         => ( $code >= 200 && $code < 300 ),
            'status'     => $code,
            'latency_ms' => $ms,
            'message'    => sprintf(
                /* translators: 1: HTTP status code, 2: latency in ms */
                __( 'HTTP %1$d in %2$d ms', 'mcp-for-claude' ),
                $code,
                $ms
            ),
        ];
    }

    private static function is_valid_url( string $url ): bool {
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }
        $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
        return in_array( $scheme, [ 'http', 'https' ], true );
    }
}

// Wire the async handler.
add_action( 'cmcp_send_webhook', [ Notifier::class, 'send_now' ], 10, 3 );
