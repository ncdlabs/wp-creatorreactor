#!/usr/bin/env php
<?php
/**
 * Offline checks for wordpress.org readme.txt conventions (not a substitute for the official validator).
 *
 * @package CreatorReactor
 */

declare( strict_types=1 );

$root   = dirname( __DIR__ );
$readme = $root . '/readme.txt';
$main   = $root . '/creatorreactor.php';

$errors   = [];
$warnings = [];

if ( ! is_readable( $readme ) ) {
	fwrite( STDERR, "Missing or unreadable readme.txt\n" );
	exit( 1 );
}

$raw   = (string) file_get_contents( $readme );
$lines = preg_split( '/\R/', $raw ) ?: [];

if ( ! isset( $lines[0] ) || ! preg_match( '/^===\s+(.+?)\s+===$/', $lines[0], $m ) ) {
	$errors[] = 'First line must be === Plugin Name ===';
}

$headers = [];
$i       = 1;
while ( $i < count( $lines ) ) {
	$line = $lines[ $i ];
	if ( preg_match( '/^==\s/', $line ) ) {
		$errors[] = 'Encountered section heading (== ...) before short description; check header block.';
		break;
	}
	if ( preg_match( '/^([A-Za-z ]+):\s*(.*)$/', $line, $hm ) ) {
		$headers[ trim( $hm[1] ) ] = trim( $hm[2] );
		++$i;
		continue;
	}
	break;
}

// Optional blank line between headers and short description.
if ( isset( $lines[ $i ] ) && $lines[ $i ] === '' ) {
	++$i;
}

$short = isset( $lines[ $i ] ) ? $lines[ $i ] : '';
if ( $short === '' || preg_match( '/^==\s/', $short ) ) {
	$errors[] = 'Missing one-line short description after header block (before == Description ==).';
} else {
	if ( strlen( $short ) > 150 ) {
		$warnings[] = 'Short description is ' . strlen( $short ) . ' chars; WordPress recommends ≤150.';
	}
	if ( str_contains( $short, '`' ) || str_contains( $short, '[' ) ) {
		$warnings[] = 'Short description should avoid markup (handbook recommends plain text).';
	}
}

$required = [ 'Contributors', 'Tags', 'Requires at least', 'Tested up to', 'Stable tag', 'License' ];
foreach ( $required as $key ) {
	if ( ! isset( $headers[ $key ] ) || $headers[ $key ] === '' ) {
		$errors[] = "Missing or empty header: {$key}";
	}
}

if ( isset( $headers['Tags'] ) ) {
	$tags = array_map( 'trim', explode( ',', $headers['Tags'] ) );
	$tags = array_values( array_filter( $tags ) );
	if ( count( $tags ) > 5 ) {
		$errors[] = 'Tags: must be at most 5 (WordPress.org guideline 12). Found ' . count( $tags ) . '.';
	}
}

if ( is_readable( $main ) ) {
	$php = (string) file_get_contents( $main );
	if ( preg_match( '/^\s*\*\s*Version:\s*([\d.]+)/m', $php, $vm ) ) {
		$v = $vm[1];
		if ( isset( $headers['Stable tag'] ) && $headers['Stable tag'] !== $v ) {
			$errors[] = "Stable tag ({$headers['Stable tag']}) must match main file Version ({$v}) for directory releases.";
		}
	} else {
		$warnings[] = 'Could not parse Version from creatorreactor.php docblock.';
	}
}

foreach ( $warnings as $w ) {
	echo "Warning: {$w}\n";
}
foreach ( $errors as $e ) {
	fwrite( STDERR, "Error: {$e}\n" );
}

if ( $errors !== [] ) {
	exit( 1 );
}

echo "readme.txt: offline checks passed.\n";
exit( 0 );
