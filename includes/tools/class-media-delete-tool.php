<?php
/**
 * media.delete — delete an attachment.
 *
 * @package WPCommander
 */

namespace CMCP\Tools;

use CMCP\Plugin;

defined( 'ABSPATH' ) || exit;

final class MediaDeleteTool extends AbstractTool {

    public function name(): string { return 'media_delete'; }

    public function description(): string {
        return 'Permanently delete an attachment from the media library along with its files. Requires "Allow destructive operations" to be enabled in plugin settings.';
    }

    public function input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
            'required'             => [ 'id' ],
            'additionalProperties' => false,
        ];
    }

    public function required_scope(): string      { return \CMCP\Auth::SCOPE_WRITE; }
    public function required_capability(): string { return 'upload_files'; }

    public function execute( array $args ): array {
        $id = (int) $args['id'];
        if ( get_post_type( $id ) !== 'attachment' ) {
            throw new \InvalidArgumentException( 'Not an attachment.' );
        }
        if ( ! current_user_can( 'delete_post', $id ) ) {
            throw new \RuntimeException( 'Not allowed to delete this attachment.' );
        }
        // media_delete is a hard delete (no trash for attachments by default in
        // wp_delete_attachment), so it always counts as a destructive op.
        if ( empty( Plugin::get_settings()['allow_destructive'] ) ) {
            throw new \RuntimeException( 'Permanent delete is disabled. Enable "Allow destructive operations" in Sayed WP Conductor settings.' );
        }
        $res = wp_delete_attachment( $id, true );
        if ( ! $res ) {
            throw new \RuntimeException( 'Delete failed.' );
        }
        return $this->json( [ 'id' => $id, 'deleted' => true ] );
    }
}