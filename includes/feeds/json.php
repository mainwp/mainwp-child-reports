<?php
/** MainWP Child Reports JSON feed. */

header( 'Content-type: application/json; charset=' . get_option( 'blog_charset' ), true );
if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
	echo wp_mainwp_stream_json_encode( $records ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON output is safe; wp_json_encode() properly handles JSON encoding for AJAX responses sent as application/json content type.
} else {
	echo wp_mainwp_stream_json_encode( $records, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON output is safe; wp_json_encode() properly handles JSON encoding for AJAX responses sent as application/json content type.
}
