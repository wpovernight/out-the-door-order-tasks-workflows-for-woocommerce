<?php

namespace WPO\OTD\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use ReflectionProperty;

/**
 * Base class for unit tests.
 *
 * WordPress is not loaded: Brain Monkey stubs the WordPress functions a test touches. The
 * translation, escaping and common sanitizing functions are stubbed for every test.
 */
abstract class TestCase extends PHPUnitTestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Start Brain Monkey and stub the WordPress functions most code paths touch.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		Functions\when( 'absint' )->alias( static fn( $value ) => abs( (int) $value ) );
		Functions\when( 'sanitize_title' )->alias( array( self::class, 'sanitize_title' ) );
	}

	/**
	 * Tear Brain Monkey down, which also verifies and closes Mockery.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A close-enough stand-in for WordPress's sanitize_title() for ASCII input.
	 *
	 * @param string $title
	 *
	 * @return string
	 */
	public static function sanitize_title( $title ): string {
		$title = strtolower( strip_tags( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9_\s-]/', '', $title );
		$title = preg_replace( '/[\s-]+/', '-', $title );

		return trim( $title, '-' );
	}

	/**
	 * Overwrite a static property, typically to reset a singleton or a static cache.
	 *
	 * @param string $class_name
	 * @param string $property
	 * @param mixed  $value
	 *
	 * @return void
	 */
	protected function set_static_property( string $class_name, string $property, mixed $value ): void {
		$reflection = new ReflectionProperty( $class_name, $property );
		$reflection->setValue( null, $value );
	}
}
