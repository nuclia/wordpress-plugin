<?php
/**
 * Lightweight PSR-4 style autoloader for plugin classes.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
	private const PREFIX = __NAMESPACE__ . '\\';

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	private static function load( string $class_name ): void {
		if ( 0 !== strncmp( $class_name, self::PREFIX, strlen( self::PREFIX ) ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( self::PREFIX ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
