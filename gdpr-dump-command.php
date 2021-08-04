<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wpcli_gdpr_dump_autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $wpcli_gdpr_dump_autoloader ) ) {
	require_once $wpcli_gdpr_dump_autoloader;
}

WP_CLI::add_command( 'gdpr-dump', GDPR_Dump_Command::class );
