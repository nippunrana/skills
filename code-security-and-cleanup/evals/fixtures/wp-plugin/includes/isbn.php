<?php
/**
 * ISBN helpers.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert a ten-digit ISBN to thirteen digits.
 *
 * @param string $isbn10 Ten-digit ISBN, with or without hyphens.
 * @return string Empty when the input is not a valid ISBN-10.
 */
function shelfmark_isbn10_to_isbn13( $isbn10 ) {
	$digits = preg_replace( '/[^0-9Xx]/', '', (string) $isbn10 );
	if ( 10 !== strlen( $digits ) ) {
		return '';
	}

	$body = '978' . substr( $digits, 0, 9 );
	return $body . shelfmark_isbn_checksum( $body );
}

/**
 * Check digit for the first twelve digits of an ISBN-13.
 *
 * @param string $digits Twelve digits.
 * @return int
 */
function shelfmark_isbn_checksum( $digits ) {
	$sum = 0;
	foreach ( str_split( $digits ) as $index => $digit ) {
		$sum += (int) $digit * ( 0 === $index % 2 ? 1 : 3 );
	}

	return ( 10 - ( $sum % 10 ) ) % 10;
}
