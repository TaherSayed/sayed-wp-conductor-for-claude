<?php
/**
 * media.upload — sideload an attachment from a URL into the media library.
 *
 * Only HTTP/HTTPS URLs are allowed; private/loopback IPs are blocked
 * (SSRF protection). Mime types are restricted to those WordPress
 * already considers safe (get_allowed_mime_types).
 *
 * @package WPCommander
 */

namespace CMCP\Tools;

defined( 'ABSPATH' ) || exit;

final class MediaUploadTool extends AbstractTool {

    public function name(): string { return 'media_upload'; }

    public function description(): string {
        return 'Download an image or file from a public URL and add it to the media library. Returns the new attachment ID and URL. Private/internal hosts are blocked.';
    }

    public function input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'url'         => [ 'type' => 'string', 'maxLength' => 2048 ],
                'title'       => [ 'type' => 'string', 'maxLength' => 200 ],
                'alt'         => [ 'type' => 'string', 'maxLength' => 200 ],
                'description' => [ 'type' => 'string', 'maxLength' => 1000 ],
                'parent_id'   => [ 'type' => 'integer', 'minimum' => 0 ],
            ],
            'required'             => [ 'url' ],
            'additionalProperties' => false,
        ];
    }

    public function required_scope(): string      { return \CMCP\Auth::SCOPE_WRITE; }
    public function required_capability(): string { return 'upload_files'; }

    public function execute( array $args ): array {
        $url = esc_url_raw( (string) $args['url'] );
        if ( ! $url ) {
            throw new \InvalidArgumentException( 'Invalid URL.' );
        }
        $this->guard_ssrf( $url );

        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Use media_handle_sideload-style flow to get attachment ID back.
        $tmp = download_url( $url, 30 );
        if ( is_wp_error( $tmp ) ) {
            throw new \RuntimeException( esc_html( 'Download failed: ' . $tmp->get_error_message() ) );
        }

        $filename = basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'upload' );
        $file = [ 'name' => sanitize_file_name( $filename ), 'tmp_name' => $tmp ];

        // Mime check via WordPress' own allowlist.
        $check = wp_check_filetype_and_ext( $tmp, $file['name'] );
        if ( empty( $check['type'] ) ) {
            wp_delete_file( $tmp );
            throw new \RuntimeException( 'File type not allowed.' );
        }

        $att_id = media_handle_sideload( $file, (int) ( $args['parent_id'] ?? 0 ), (string) ( $args['title'] ?? '' ) );
        if ( is_wp_error( $att_id ) ) {
            wp_delete_file( $tmp );
            throw new \RuntimeException( esc_html( $att_id->get_error_message() ) );
        }

        if ( ! empty( $args['alt'] ) ) {
            update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt'] ) );
        }
        if ( ! empty( $args['description'] ) ) {
            wp_update_post( [ 'ID' => $att_id, 'post_content' => sanitize_textarea_field( (string) $args['description'] ) ] );
        }

        return $this->json( [
            'id'        => (int) $att_id,
            'url'       => wp_get_attachment_url( $att_id ),
            'thumbnail' => wp_get_attachment_image_url( $att_id, 'thumbnail' ),
            'mime'      => get_post_mime_type( $att_id ),
        ] );
    }

    /** Block SSRF: only http/https, public hosts only (IPv4 + IPv6, literal IPs in URL also caught). */
    private function guard_ssrf( string $url ): void {
        $p = wp_parse_url( $url );
        if ( ! $p || empty( $p['scheme'] ) || ! in_array( strtolower( $p['scheme'] ), [ 'http', 'https' ], true ) ) {
            throw new \InvalidArgumentException( 'Only http(s) URLs are accepted.' );
        }
        $host = $p['host'] ?? '';
        if ( $host === '' ) {
            throw new \InvalidArgumentException( 'URL host required.' );
        }

        // Strip brackets if host is a literal IPv6 ([::1] form).
        $bare_host = ( $host !== '' && $host[0] === '[' && substr( $host, -1 ) === ']' )
            ? substr( $host, 1, -1 )
            : $host;

        // If the host is itself a literal IP, check it directly (skips DNS).
        if ( filter_var( $bare_host, FILTER_VALIDATE_IP ) ) {
            $this->assert_public_ip( $bare_host );
            return;
        }

        // Resolve both IPv4 and IPv6 records. gethostbynamel() is IPv4-only;
        // dns_get_record covers AAAA so an attacker host cannot bypass the
        // guard by serving an AAAA record pointing at fc00::… or ::1.
        $records = @dns_get_record( $bare_host, DNS_A + DNS_AAAA );
        $ips     = [];
        if ( is_array( $records ) ) {
            foreach ( $records as $r ) {
                if ( ! empty( $r['ip'] ) )   { $ips[] = $r['ip']; }    // A
                if ( ! empty( $r['ipv6'] ) ) { $ips[] = $r['ipv6']; }  // AAAA
            }
        }
        if ( empty( $ips ) ) {
            // Fall back to IPv4-only resolution; if that also fails, refuse.
            $v4 = gethostbynamel( $bare_host );
            if ( is_array( $v4 ) ) { $ips = $v4; }
        }
        if ( empty( $ips ) ) {
            throw new \InvalidArgumentException( 'Host does not resolve.' );
        }
        foreach ( $ips as $ip ) {
            $this->assert_public_ip( $ip );
        }
    }

    /**
     * Reject any IP that is private, reserved, loopback, link-local, an IPv6
     * ULA, IPv4-mapped IPv6, or a known cloud-metadata literal. Throws on bad
     * IPs so callers do not have to inspect a return value.
     */
    private function assert_public_ip( string $ip ): void {
        // PHP's FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE cover most ranges
        // (10/8, 172.16/12, 192.168/16, 127/8, 169.254/16 link-local incl.
        // 169.254.169.254 cloud-metadata, 224/3 multicast, 240/4 reserved,
        // 0/8, IPv6 ::1 / ::/128 / fe80::/10 / fc00::/7 / 2001:db8::/32 etc.)
        // but coverage varies slightly across PHP versions. Belt-and-braces
        // explicit checks below catch anything missed.
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            throw new \InvalidArgumentException( 'Host resolves to a private or reserved IP — refusing for SSRF protection.' );
        }
        // Explicit cloud-metadata literals. 169.254.169.254 is the
        // AWS/Azure/OpenStack/DigitalOcean IMDS address; fd00:ec2::254 is the
        // IPv6 form on EC2. Already covered by NO_RES_RANGE on PHP >= 7.0,
        // but make it explicit so the intent is grep-able.
        if ( $ip === '169.254.169.254' || strtolower( $ip ) === 'fd00:ec2::254' ) {
            throw new \InvalidArgumentException( 'Cloud metadata endpoint blocked.' );
        }
        $lower = strtolower( $ip );
        // IPv6 ULA fc00::/7 — private equivalent, must be blocked.
        if ( strpos( $lower, 'fc' ) === 0 || strpos( $lower, 'fd' ) === 0 ) {
            throw new \InvalidArgumentException( 'IPv6 ULA address blocked.' );
        }
        // IPv6 link-local fe80::/10 (covers fe80–febf prefix).
        if ( preg_match( '/^fe[89ab]/i', $lower ) ) {
            throw new \InvalidArgumentException( 'IPv6 link-local address blocked.' );
        }
        // IPv4-mapped IPv6 (::ffff:a.b.c.d) — decode and re-check.
        if ( strpos( $lower, '::ffff:' ) === 0 ) {
            $mapped = substr( $ip, 7 );
            if ( filter_var( $mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                $this->assert_public_ip( $mapped );
            }
        }
    }
}