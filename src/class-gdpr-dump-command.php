<?php

// Only run through WP CLI.
if ( ! defined( 'WP_CLI' ) ) {
	return;
}

/**
 * GDPR Dump command for WP CLI.
 */
class GDPR_Dump_Command extends WP_CLI_Command {

	/**
	 * Export the database to a file with personal information anonymized.
	 *
	 * ### Examples
	 *
	 *     wp gdpr-dump
	 *     wp gdpr-dump path/to/config_file.yml
	 *
	 * @param array $args
	 * @param array $options
	 *
	 * @when before_wp_load
	 */
	public function __invoke( array $args = [], array $options = [] ) {
		if ( empty( $args ) ) {
			$args[] = getcwd();
		}

		print_r( $args );
	}

}

\WP_CLI::add_command( 'gdpr-dump', 'GDPR_Dump_Command' );
