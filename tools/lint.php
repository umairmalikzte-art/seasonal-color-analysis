<?php
/* PHP syntax check for the plugin, theme and seed (npm run lint:php). Runs in Playground's PHP, so no local PHP is needed. */
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( '/app/wp-content' ) );
$files = array( '/app/setup/seed.php' );
foreach ( $it as $f ) { if ( substr( $f, -4 ) === '.php' ) { $files[] = (string) $f; } }
foreach ( $files as $f ) {
	try { token_get_all( file_get_contents( $f ), TOKEN_PARSE ); echo "OK   $f\n"; }
	catch ( ParseError $e ) { echo "FAIL $f line {$e->getLine()}: {$e->getMessage()}\n"; }
}
