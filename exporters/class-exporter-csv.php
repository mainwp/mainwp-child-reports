<?php
/** MainWP Child Reports CSV exporter. */

namespace WP_MainWP_Stream;

/**
 * Class Exporter_CSV
 * @package WP_MainWP_Stream
 */
class Exporter_CSV extends Exporter {

	/** @var string Exporter name. */
	public $name = 'CSV';

	/**  @var string Exporter slug. */
	public $slug = 'csv';

	/**
	 * Outputs CSV data for download.
	 *
	 * @param array $data Array of data to output.
	 * @param array $columns Column names included in data set.
	 */
	public function output_file( $data, $columns ) {
		if ( ! defined( 'WP_MAINWP_STREAM_TESTS' ) || ( defined( 'WP_MAINWP_STREAM_TESTS' ) && ! WP_MAINWP_STREAM_TESTS ) ) {
			header( 'Content-type: text/csv' );
			header( 'Content-Disposition: attachment; filename="stream.csv"' );
		}

		$output = join( ',', array_values( $columns ) ) . "\n";
		foreach ( $data as $row ) {
			$output .= join( ',', $row ) . "\n";
		}

		echo $output; // @codingStandardsIgnoreLine text-only output
		if ( ! defined( 'WP_MAINWP_STREAM_TESTS' ) || ( defined( 'WP_MAINWP_STREAM_TESTS' ) && ! WP_MAINWP_STREAM_TESTS ) ) {
			exit;
		}
	}
}
