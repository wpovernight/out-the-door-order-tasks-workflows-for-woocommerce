<?php

namespace WPO\OTD\Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPO\OTD\Tests\TestCase;

/**
 * Where database access, hooks and the container may appear, and how hooks are named. Each allow-list entry says why
 * it is allowed.
 */
final class ArchitectureTest extends TestCase {
	private const FIRING_FUNCTIONS = array(
		'apply_filters',
		'apply_filters_ref_array',
		'do_action',
		'do_action_ref_array',
	);

	private const LISTENING_FUNCTIONS = array(
		'add_action',
		'add_filter',
		'remove_action',
		'remove_filter',
		'has_action',
		'has_filter',
	);

	/**
	 * The bootstrap wires what must happen at file load, before the kernel that boots the providers exists.
	 */
	private const BOOTSTRAP = 'OutTheDoor.php';

	public function test_only_repositories_and_core_use_wpdb(): void {
		$this->assertSame(
			array(),
			$this->files_matching(
				'/\$wpdb\b|\$GLOBALS\s*\[\s*[\'"]wpdb[\'"]\s*\]/',
				array(
					'Repositories/',
					// Create, check and drop the plugin's tables and their foreign keys.
					'Core/ForeignKeys.php',
					'Core/Installer.php',
					'Core/Schema.php',
				)
			),
			'Only repositories and the installer may use $wpdb; elsewhere, go through a repository.'
		);
	}

	public function test_constructors_have_no_side_effects(): void {
		$side_effect = '/' . self::call_of(
			'add_action',
			'add_filter',
			'apply_filters',
			'apply_filters_ref_array',
			'do_action',
			'do_action_ref_array',
			'get_option',
			'update_option',
			'add_option',
			'delete_option',
			'register_post_status',
			'register_post_type',
			'as_\w+'
		) . '|wpdb->/';
		$violations  = array();

		foreach ( $this->files() as $path => $file ) {
			if ( self::BOOTSTRAP === $path || $this->is_email( $file ) ) {
				continue;
			}

			if ( preg_match( $side_effect, $this->split( $file, array( '__construct' ) )[1] ) ) {
				$violations[] = $path;
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'A constructor only stores its dependencies: no hooks, options or queries, since the container builds'
				. ' classes whenever they are first needed.'
		);
	}

	public function test_only_the_composition_roots_use_the_container(): void {
		$this->assertSame(
			array(),
			$this->files_matching(
				'/\bContainer\b(?!\\\\)|\$container\b/',
				array( 'Core/Providers/', 'Core/Container/', 'Core/Kernel.php', self::BOOTSTRAP )
			),
			'Only providers, the kernel and the bootstrap may use the container; other classes take their dependencies'
				. ' in the constructor.'
		);
	}

	public function test_only_providers_build_repositories_and_services(): void {
		$this->assertSame(
			array(),
			$this->files_matching( '/\bnew\s+[\w\\\\]*(Repository|Service)\s*\(/', array( 'Core/Providers/' ) ),
			'Take a repository or service in the constructor, so the container builds one instance for everyone.'
		);
	}

	public function test_hooks_are_added_only_where_hooks_are_registered(): void {
		$violations = array();
		$add        = '/' . self::call_of( 'add_action', 'add_filter' ) . '/';

		foreach ( $this->files() as $path => $file ) {
			$functions = array_merge(
				array( 'register_hooks', 'register_routes' ),
				str_ends_with( $path, 'ServiceProvider.php' ) ? array( 'boot' ) : array(),
				self::BOOTSTRAP === $path ? array( '__construct', 'init_hooks' ) : array(),
				// WooCommerce builds its emails itself, and an email hooks its trigger in its constructor.
				$this->is_email( $file ) ? array( '__construct' ) : array()
			);

			if ( preg_match( $add, $this->split( $file, $functions )[0] ) ) {
				$violations[] = $path;
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'add_action() and add_filter() belong in register_hooks(), register_routes() or a provider\'s boot().'
		);
	}

	public function test_hook_names_are_literals_or_scheduled_action_constants(): void {
		$literal  = '\'[^\']*\'\s*[,)]';
		$fired    = self::call_of( ...self::FIRING_FUNCTIONS ) . '\s*+(?!' . $literal . ')';
		$listened = self::call_of( ...self::LISTENING_FUNCTIONS )
			. '\s*+(?!' . $literal . '|[\w\\\\]+::(\w+_)?HOOK\s*[,)])';

		$this->assertSame(
			array(),
			$this->files_matching( '/' . $fired . '|' . $listened . '/' ),
			'Name a hook with one whole string literal at the call, so its name can be searched for; only where we'
				. ' listen to our own Action Scheduler hooks may the name be a constant named HOOK or ..._HOOK.'
		);
	}

	public function test_dynamic_hook_names_are_never_waved_through(): void {
		$violations = array();

		foreach ( $this->files() as $path => $file ) {
			if ( str_contains( file_get_contents( $file ), 'DynamicHooknameFound' ) ) {
				$violations[] = $path;
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'Name the hook with a literal instead of ignoring DynamicHooknameFound.'
		);
	}

	public function test_fired_hooks_are_prefixed(): void {
		$names = array();

		foreach ( $this->files() as $file ) {
			preg_match_all(
				'/' . self::call_of( ...self::FIRING_FUNCTIONS ) . '\s*\'(?!wpo_otd_)([^\']*)\'/',
				$this->code( $file ),
				$matches
			);
			$names = array_merge( $names, $matches[1] );
		}

		$this->assertSame(
			array(),
			// WordPress's own filter, applied as WordPress applies it.
			array_values( array_diff( $names, array( 'plugin_locale' ) ) ),
			'Hooks we fire start with "wpo_otd_".'
		);
	}

	public function test_every_fired_hook_has_a_docblock(): void {
		$violations = array();

		foreach ( $this->files() as $path => $file ) {
			$tokens = token_get_all( file_get_contents( $file ) );

			foreach ( $tokens as $at => $token ) {
				if (
					self::is_call( $tokens, $at, self::FIRING_FUNCTIONS )
					// WordPress documents its own filter, which the bootstrap applies as WordPress does.
					&& "'plugin_locale'" !== self::first_argument( $tokens, $at )
					&& ! self::has_docblock( $tokens, $at )
				) {
					$violations[] = $path . ':' . $token[2];
				}
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'Put a docblock right above each apply_filters() and do_action(): what it changes, the valid range, what'
				. ' happens to an invalid value, and an @param with the default.'
		);
	}

	public function test_scheduled_action_hooks_and_groups_are_prefixed(): void {
		// Groups the released 1.0.0 already queues actions under: renamed, has_scheduled and unschedule calls would
		// miss those actions.
		$released_groups = array( 'wpo_otd', 'wpo-otd' );
		$violations      = array();

		foreach ( $this->files() as $path => $file ) {
			preg_match_all(
				'/const\s+((\w+_)?(HOOK|GROUPS?))\s*=\s*([^;]+);/',
				$this->code( $file ),
				$constants
			);

			foreach ( $constants[4] as $value ) {
				preg_match_all( '/\'([^\']*)\'/', $value, $names );

				foreach ( $names[1] as $name ) {
					if ( ! str_starts_with( $name, 'wpo_otd_' ) && ! in_array( $name, $released_groups, true ) ) {
						$violations[] = $path . ': ' . $name;
					}
				}
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'Our Action Scheduler hooks and groups start with "wpo_otd_".'
		);
	}

	/**
	 * A pattern, without delimiters, for a call to one of these functions: not a method of the same name, nor its
	 * declaration.
	 *
	 * @param string ...$functions
	 *
	 * @return string
	 */
	private static function call_of( string ...$functions ): string {
		return '(?<![\w$>:])(?<!function )(?:' . implode( '|', $functions ) . ')\s*\(';
	}

	/**
	 * Whether a file holds a WooCommerce email.
	 *
	 * @param string $file
	 *
	 * @return bool
	 */
	private function is_email( string $file ): bool {
		return 1 === preg_match( '/\bextends\s+\\\\?WC_Email\b/', $this->code( $file ) );
	}

	/**
	 * Whether the token at $at starts a call to one of the functions.
	 *
	 * @param array    $tokens
	 * @param int      $at
	 * @param string[] $functions
	 *
	 * @return bool
	 */
	private static function is_call( array $tokens, int $at, array $functions ): bool {
		$token = $tokens[ $at ];

		if (
			! is_array( $token )
			|| ! in_array( $token[0], array( T_STRING, T_NAME_FULLY_QUALIFIED ), true )
			|| ! in_array( ltrim( $token[1], '\\' ), $functions, true )
		) {
			return false;
		}

		$before         = self::neighbour( $tokens, $at, -1 );
		$not_a_function = array( T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION );

		return '(' === self::neighbour( $tokens, $at, 1 )
			&& ! ( is_array( $before ) && in_array( $before[0], $not_a_function, true ) );
	}

	/**
	 * The source of the first token after the parenthesis that follows the token at $at.
	 *
	 * @param array $tokens
	 * @param int   $at
	 *
	 * @return string
	 */
	private static function first_argument( array $tokens, int $at ): string {
		$argument = self::neighbour( $tokens, self::neighbour_at( $tokens, $at, 1 ), 1 );

		return is_array( $argument ) ? $argument[1] : (string) $argument;
	}

	/**
	 * Whether a docblock with a summary or an @param, not only tags such as @var, sits between the start of the
	 * statement holding the token at $at and the token.
	 *
	 * @param array $tokens
	 * @param int   $at
	 *
	 * @return bool
	 */
	private static function has_docblock( array $tokens, int $at ): bool {
		for ( $before = $at - 1; $before >= 0; $before-- ) {
			if ( is_array( $tokens[ $before ] ) && T_DOC_COMMENT === $tokens[ $before ][0] ) {
				return 1 === preg_match( '/^[ \t\/*]*+(?!@)[^\s*\/]|@param\b/m', $tokens[ $before ][1] );
			}

			if ( in_array( $tokens[ $before ], array( ';', '{', '}' ), true ) ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * The nearest token before ($step -1) or after ($step 1) the one at $at, whitespace and comments skipped.
	 *
	 * @param array $tokens
	 * @param int   $at
	 * @param int   $step
	 *
	 * @return array|string|null
	 */
	private static function neighbour( array $tokens, int $at, int $step ) {
		$near = self::neighbour_at( $tokens, $at, $step );

		return null === $near ? null : $tokens[ $near ];
	}

	/**
	 * Where the nearest token before ($step -1) or after ($step 1) the one at $at is, whitespace and comments skipped.
	 *
	 * @param array $tokens
	 * @param int   $at
	 * @param int   $step
	 *
	 * @return int|null
	 */
	private static function neighbour_at( array $tokens, int $at, int $step ): ?int {
		for ( $near = $at + $step; isset( $tokens[ $near ] ); $near += $step ) {
			$skipped = is_array( $tokens[ $near ] )
				&& in_array( $tokens[ $near ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );

			if ( ! $skipped ) {
				return $near;
			}
		}

		return null;
	}

	/**
	 * The files under includes/ whose code, comments left out, matches a pattern.
	 *
	 * @param string   $pattern
	 * @param string[] $allowed Paths from includes/, a folder ending in a slash, left out.
	 *
	 * @return string[] Their paths from includes/.
	 */
	private function files_matching( string $pattern, array $allowed = array() ): array {
		$matching = array();

		foreach ( $this->files() as $path => $file ) {
			foreach ( $allowed as $prefix ) {
				if ( $path === $prefix || ( str_ends_with( $prefix, '/' ) && str_starts_with( $path, $prefix ) ) ) {
					continue 2;
				}
			}

			if ( preg_match( $pattern, $this->code( $file ) ) ) {
				$matching[] = $path;
			}
		}

		return $matching;
	}

	/**
	 * The PHP files under includes/.
	 *
	 * @return array<string, string> Path from includes/, with forward slashes => full path.
	 */
	private function files(): array {
		$includes = str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . '/includes/';
		$files    = array();

		foreach (
			new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $includes, RecursiveDirectoryIterator::SKIP_DOTS )
			) as $file
		) {
			if ( 'php' === $file->getExtension() ) {
				$path = $file->getPathname();

				$files[ substr( str_replace( '\\', '/', $path ), strlen( $includes ) ) ] = $path;
			}
		}

		ksort( $files );

		return $files;
	}

	/**
	 * A file's code, comments left out.
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	private function code( string $file ): string {
		return $this->split( $file, array() )[0];
	}

	/**
	 * A file's code, comments left out, split into the bodies of the functions named and the rest. A closure is part
	 * of the function around it.
	 *
	 * @param string   $file
	 * @param string[] $functions
	 *
	 * @return array{0: string, 1: string} The code outside those bodies, and the bodies.
	 */
	private function split( string $file, array $functions ): array {
		$tokens  = token_get_all( file_get_contents( $file ) );
		$parts   = array( '', '' );
		$pending = false;
		$depth   = 0;

		foreach ( $tokens as $at => $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			if ( 0 === $depth && is_array( $token ) && T_FUNCTION === $token[0] ) {
				$name    = self::neighbour( $tokens, $at, 1 );
				$pending = is_array( $name ) && in_array( $name[1], $functions, true );
			} elseif ( 0 === $depth && ';' === $token ) {
				$pending = false;
			}

			if ( $pending && '{' === $token ) {
				$pending = false;
				$depth   = 1;
			} elseif ( $depth > 0 ) {
				$depth += self::brace( $token );
			}

			$parts[ $depth > 0 ? 1 : 0 ] .= is_array( $token ) ? $token[1] : $token;
		}

		return $parts;
	}

	/**
	 * How a token changes the depth of curly braces, the ones that open a variable in a string included.
	 *
	 * @param array|string $token
	 *
	 * @return int
	 */
	private static function brace( $token ): int {
		if ( is_array( $token ) ) {
			return in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ? 1 : 0;
		}

		return array(
			'{' => 1,
			'}' => -1,
		)[ $token ] ?? 0;
	}
}
