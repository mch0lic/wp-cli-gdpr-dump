<?php

use WP_CLI\Process;
use WP_CLI\Utils;

// Only run through WP CLI.
if ( ! defined( 'WP_CLI' ) ) {
	return;
}

/**
 * GDPR Dump command for WP CLI.
 */
class GDPR_Dump_Command extends WP_CLI_Command {

	/**
	 * Exports the database to a file with personal information anonymized.
	 *
	 * ## Options
	 *
	 * [--config=<filename>]
	 * : Path to custom YML config file.
	 *
	 * ## Examples
	 *
	 *     wp gdpr-dump
	 *     wp gdpr-dump --config=path/to/config_file.yml
	 *
	 * @when after_wp_config_load
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$assoc_args = array_merge(
			array(
				'config' => '',
			),
			$assoc_args
		);

		// Environment variables passed to shell.
		$env_variables = array(
			'DB_HOST'     => DB_HOST,
			'DB_USER'     => DB_USER,
			'DB_PASSWORD' => DB_PASSWORD,
			'DB_NAME'     => DB_NAME,
		);

		$root_path     = rtrim( ABSPATH, '/' );
		$gdpr_dump_bin = $this->get_gdpr_dump_bin();
		$config        = $this->get_config_file( $root_path, $assoc_args );

		if ( empty( $gdpr_dump_bin ) ) {
			WP_CLI::error( 'Unable to locate gdpr-dump executable.' );
		}

		if ( empty( $config ) ) {
			WP_CLI::error( 'Unable to locate config file.' );
		}

		// Execute gdpr-dump.
		$command = Utils\esc_cmd( sprintf( '%s %%s', $gdpr_dump_bin ), $config );
		if ( WP_CLI::get_config( 'debug' ) ) {
			WP_CLI::debug( sprintf( 'Running command: %s', $command ) );
		}

		$process_run = Process::create( $command, null, $env_variables )->run();
		if ( 0 !== $process_run->return_code ) {
			if ( ! empty( $process_run->stderr ) ) {
				WP_CLI::error( $process_run->stderr );
			} else {
				WP_CLI::error( 'Database export failed.' );
			}
		} else {
			WP_CLI::success( 'Database exported successfully.' );
		}
	}

	/**
	 * Returns path to gdpr-dump bin file.
	 *
	 * @return string
	 */
	private function get_gdpr_dump_bin() {
		$bin = rtrim( WP_CLI::get_runner()->get_packages_dir_path(), '/' ) . '/vendor/smile/gdpr-dump/bin/gdpr-dump';

		if ( file_exists( $bin ) ) {
			return $bin;
		}

		return null;
	}

	/**
	 * Get config file.
	 *
	 * @param string $root_path WordPress root path.
	 * @param array  $options Command line options.
	 *
	 * @return string
	 */
	private function get_config_file( $root_path, $options = array() ) {
		// Plausible locations for config file.
		$paths = array(
			getcwd(),
			rtrim( preg_replace( '/\/web\/wp$/', '', $root_path ), '/' ),
			$root_path,
		);

		// Config file provided.
		if ( ! empty( $options['config'] ) ) {
			if ( file_exists( $options['config'] ) ) {
				return $options['config'];
			}

			foreach ( $paths as $path ) {
				$config = WP_CLI\Utils\normalize_path( $path . DIRECTORY_SEPARATOR . $options['config'] );
				if ( file_exists( $config ) ) {
					return $config;
				}
			}
		}

		// Attempt to find gdpr-dump.yml config.
		foreach ( $paths as $path ) {
			$config = WP_CLI\Utils\normalize_path( $path . DIRECTORY_SEPARATOR . 'gdpr-dump.yml' );
			if ( file_exists( $config ) ) {
				return $config;
			}
		}

		// Create default config and return the path.
		return $this->get_default_config_path();
	}

	/**
	 * Creates a config file and returns file path.
	 *
	 * @return string
	 */
	private function get_default_config_path() {
		global $table_prefix;

		$tmp_dir       = rtrim( WP_CLI\Utils\get_temp_dir(), DIRECTORY_SEPARATOR );
		$package_root  = dirname( dirname( __FILE__ ) );
		$template_path = $package_root . '/templates/';
		$config_file   = Utils\normalize_path( $tmp_dir . DIRECTORY_SEPARATOR . 'wordpress.yml' );
		if ( file_exists( $config_file ) ) {
			unlink( $config_file );
		}

		return $this->create_file(
			$config_file,
			WP_CLI\Utils\mustache_render(
				"{$template_path}/wordpress.mustache",
				array(
					'table_prefix'    => ! empty( $table_prefix ) ? $table_prefix : 'wp_',
					'backup_filename' => 'backup-{YmdHis}.sql',
				)
			)
		);
	}

	/**
	 * Helper method for creating files.
	 *
	 * @param string $filename Filename including path.
	 * @param string $contents File contents.
	 *
	 * @return string
	 */
	private function create_file( $filename, $contents ) {
		if ( ! is_dir( dirname( $filename ) ) ) {
			Process::create( Utils\esc_cmd( 'mkdir -p %s', dirname( $filename ) ) )->run();
		}

		if ( ! file_put_contents( $filename, $contents ) ) {
			WP_CLI::error( sprintf( 'Error creating file: %s', $filename ) );
		}

		return $filename;
	}
}
