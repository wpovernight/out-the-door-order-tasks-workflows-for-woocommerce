<?php

namespace WPO\AOM\Repositories;

defined( 'ABSPATH' ) || exit;

final class RepositoryRegistry {
	/** @var array<string, BaseRepository> */
	private static array $map = array();

	/**
	 * Register a repository for a model class.
	 *
	 * @param string $model_class Fully qualified model class name.
	 * @param callable $factory A callable that returns an instance of the repository.
	 *
	 * @return void
	 */
	public static function register( string $model_class, callable $factory ): void {
		self::$map[ $model_class ] = $factory;
	}

	/**
	 * Get the repository for a model class.
	 *
	 * @param string $model_class Fully qualified model class name.
	 *
	 * @return BaseRepository
	 * @throws \RuntimeException If no repository is registered for the model class.
	 */
	public static function get( string $model_class ): BaseRepository {
		$repository = self::$map[ $model_class ] ?? null;

		// Lazy instantiate if it's a callable
		if ( is_callable( $repository ) ) {
			$repository                = $repository();
			self::$map[ $model_class ] = $repository;
		}

		if ( ! $repository instanceof BaseRepository ) {
			throw new \RuntimeException( esc_html( "No repository registered for model: $model_class" ) );
		}

		return $repository;
	}
}

