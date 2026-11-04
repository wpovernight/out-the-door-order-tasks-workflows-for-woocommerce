<?php

namespace WPO\OTD\Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPO\OTD\Tests\TestCase;

/**
 * Where database access, hooks and the container may appear, how hooks are named, and what models, providers, REST
 * controllers and enums may hold. Each allow-list entry says why it is allowed. A failure lists each place as
 * "path:line", the path from includes/ or, for a template, from the plugin's folder.
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

	private const WPDB = '\$wpdb\b|\$GLOBALS\s*\[\s*[\'"]wpdb[\'"]\s*\]';

	/**
	 * The bootstrap wires what must happen at file load, before the kernel that boots the providers exists.
	 */
	private const BOOTSTRAP = 'OutTheDoor.php';

	public function test_only_repositories_and_core_use_wpdb(): void {
		$this->assertSame(
			array(),
			$this->files_matching(
				'/' . self::WPDB . '/',
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
		$calls      = array(
			...self::FIRING_FUNCTIONS,
			...self::LISTENING_FUNCTIONS,
			...array(
				'get_option',
				'update_option',
				'add_option',
				'delete_option',
				'\w+_transient',
				'register_\w+',
				'as_\w+',
				'wp_schedule_\w+',
				'wp_unschedule_\w+',
				'wp_clear_scheduled_hook',
			),
		);
		$violations = array();

		foreach ( $this->files() as $path => $file ) {
			if ( self::BOOTSTRAP === $path ) {
				continue;
			}

			// WooCommerce builds its emails itself, and an email hooks its trigger and reads its settings in its
			// constructor, as WooCommerce's own emails do.
			$allowed     = $this->is_email( $file ) ? array( 'add_action', 'add_filter', 'get_option' ) : array();
			$side_effect = '/' . self::call_of( ...array_diff( $calls, $allowed ) )
				. '|(' . self::WPDB . '|\bwpdb)\s*->/';

			array_push(
				$violations,
				...self::places( $side_effect, $this->split( $file, array( '__construct' ) )[1], $path )
			);
		}

		$this->assertSame(
			array(),
			$violations,
			'A constructor only stores its dependencies: no hooks, options, transients, schedules or queries, since the'
				. ' container builds classes whenever they are first needed.'
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

			array_push( $violations, ...self::places( $add, $this->split( $file, $functions )[0], $path ) );
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
			$this->files_matching( '/' . $fired . '|' . $listened . '/', array(), $this->files() + $this->templates() ),
			'Name a hook with one whole string literal at the call, so its name can be searched for; only where we'
				. ' listen to our own Action Scheduler hooks may the name be a constant named HOOK or ..._HOOK.'
		);
	}

	public function test_dynamic_hook_names_are_never_waved_through(): void {
		$violations = array();

		foreach ( $this->files() + $this->templates() as $path => $file ) {
			array_push( $violations, ...self::places( '/DynamicHooknameFound/', file_get_contents( $file ), $path ) );
		}

		$this->assertSame(
			array(),
			$violations,
			'Name the hook with a literal instead of ignoring DynamicHooknameFound.'
		);
	}

	public function test_fired_hooks_are_prefixed(): void {
		$violations = array();

		foreach ( $this->files() + $this->templates() as $path => $file ) {
			$code = $this->code( $file );

			preg_match_all(
				'/' . self::call_of( ...self::FIRING_FUNCTIONS ) . '\s*\'(?!wpo_otd_)([^\']*)\'/',
				$code,
				$found,
				PREG_SET_ORDER | PREG_OFFSET_CAPTURE
			);

			foreach ( $found as $match ) {
				if ( ! self::is_foreign_hook( $match[1][0], $path ) ) {
					$violations[] = self::line_of( $code, $match[0][1], $path ) . ' ' . $match[1][0];
				}
			}
		}

		$this->assertSame( array(), $violations, 'Hooks we fire start with "wpo_otd_".' );
	}

	public function test_every_fired_hook_has_a_docblock(): void {
		$violations = array();

		foreach ( $this->files() + $this->templates() as $path => $file ) {
			$tokens = token_get_all( file_get_contents( $file ) );

			foreach ( $tokens as $at => $token ) {
				if (
					self::is_call( $tokens, $at, self::FIRING_FUNCTIONS )
					&& ! self::is_foreign_hook( trim( self::first_argument( $tokens, $at ), '\'' ), $path )
					&& ! self::has_docblock( $tokens, $at )
				) {
					$violations[] = $path . ':' . $token[2];
				}
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'Put a docblock right above each apply_filters() and do_action(), one for each: what it changes, the valid'
				. ' range, what happens to an invalid value, and an @param with the default.'
		);
	}

	public function test_scheduled_action_hooks_and_groups_are_prefixed(): void {
		// Groups the released 1.0.0 already queues actions under: renamed, has_scheduled and unschedule calls would
		// miss those actions.
		$released_groups = array( 'wpo_otd', 'wpo-otd' );
		$violations      = array();

		foreach ( $this->files() as $path => $file ) {
			$code = $this->code( $file );

			preg_match_all(
				'/const\s+((\w+_)?(HOOK|GROUPS?))\s*=\s*([^;]+);/',
				$code,
				$constants,
				PREG_SET_ORDER | PREG_OFFSET_CAPTURE
			);

			foreach ( $constants as $constant ) {
				preg_match_all( '/\'([^\']*)\'/', $constant[4][0], $names );

				foreach ( $names[1] as $name ) {
					if ( ! str_starts_with( $name, 'wpo_otd_' ) && ! in_array( $name, $released_groups, true ) ) {
						$violations[] = self::line_of( $code, $constant[0][1], $path ) . ' ' . $name;
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

	public function test_models_are_plain_data(): void {
		$models     = $this->files_in( 'Models' );
		$names      = array_diff(
			array_map( static fn( string $path ): string => basename( $path, '.php' ), array_keys( $models ) ),
			array( 'BaseModel' )
		);
		$reaching   = '/(repositor(y|ies)|services?|logger)\b|\b(container|kernel)\b|->get\s*\(/i';
		$violations = array();

		foreach ( $models as $path => $file ) {
			$code   = $this->code( $file );
			$others = array_diff( $names, array( basename( $path, '.php' ) ) );

			array_push(
				$violations,
				...self::places( $reaching, $code, $path ),
				...( $others ? self::places( '/\b(' . implode( '|', $others ) . ')\b/', $code, $path ) : array() )
			);
		}

		$this->assertSame(
			array(),
			$violations,
			'A model is plain data and never reaches a repository, a service, the logger, the container or another'
				. ' model: related rows load through a repository method, many in one query.'
		);
	}

	public function test_register_hooks_calls_no_storage_scheduling_or_role_functions(): void {
		$work       = '/' . self::call_of(
			'(get|update|add|delete)_(site_)?option',
			'\w+_transient',
			'\w+_post_meta',
			'flush_rewrite_rules',
			'as_\w+',
			'wp_schedule_\w+',
			'wp_unschedule_\w+',
			'wp_clear_scheduled_hook',
			'add_role',
			'remove_role',
			'get_role'
		) . '|(' . self::WPDB . '|\bwpdb)\s*->|->((add|remove)_cap|queue)\s*\(/';
		$violations = array();

		foreach ( $this->files() as $path => $file ) {
			$body = self::without_closures( $this->split( $file, array( 'register_hooks', 'register_routes' ) )[1] );

			array_push( $violations, ...self::places( $work, $body, $path ) );
		}

		$this->assertSame(
			array(),
			$violations,
			'register_hooks() and register_routes() only add hooks and routes: no options, meta, transients, queries,'
				. ' schedules, rewrite flushes or roles, which belong to the installer or the method that needs them.'
		);
	}

	public function test_providers_bind_in_register_and_wire_in_boot(): void {
		$violations = array();

		foreach ( $this->service_providers() as $path => $file ) {
			array_push(
				$violations,
				...self::places(
					'/->(get|register_hooks|register_routes)\s*\(|\bnew\s|::instance\s*\(/',
					self::without_closures( $this->split( $file, array( 'register' ) )[1] ),
					$path
				),
				...self::places( '/->(bind|singleton)\s*\(/', $this->split( $file, array( 'boot' ) )[1], $path )
			);
		}

		$this->assertSame(
			array(),
			$violations,
			'A provider\'s register() only binds and its boot() only wires: resolving in register() or binding in'
				. ' boot() makes resolution depend on the order the providers run in.'
		);
	}

	public function test_only_the_bootstrap_wires_what_runs_before_the_kernel_boots(): void {
		$this->assertSame(
			array(),
			$this->files_matching(
				'/' . self::call_of( 'add_action', 'add_filter' ) . '\s*\'(plugins_loaded|woocommerce_loaded)\''
					. '|\b(register_(activation|deactivation|uninstall)_hook|declare_compatibility)\s*\(/',
				array( self::BOOTSTRAP )
			),
			'The kernel boots during plugins_loaded, too late for plugins_loaded, woocommerce_loaded and the'
				. ' activation, deactivation and uninstall hooks, and does not boot at all when a dependency is'
				. ' missing, yet the HPOS declaration must still be made: the bootstrap and the main plugin file wire'
				. ' these.'
		);
	}

	public function test_only_the_main_plugin_file_and_the_bootstrap_build_a_container(): void {
		$this->assertSame(
			array(),
			$this->files_matching( '/\bnew\s+(?:[\w\\\\]*\\\\)?(Container|Kernel)\b/', array( self::BOOTSTRAP ) ),
			'Only the main plugin file, for activation, and the bootstrap build a container or a kernel, so a stateful'
				. ' service never gets a second, stale copy.'
		);
	}

	public function test_every_rest_controller_extends_the_base_controller(): void {
		$classes    = $this->classes();
		$parents    = array_column( $classes, 'parent', 'name' );
		$violations = array();

		foreach ( $classes as $class ) {
			if (
				( str_starts_with( $class['path'], 'REST/' ) || $this->registers_routes( $class['file'] ) )
				&& 'BaseRestController' !== $class['name']
				&& ! self::reaches( $class['parent'], 'BaseRestController', $parents )
			) {
				$violations[] = $class['place'];
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'A REST controller, and any class that registers a route, extends BaseRestController, which holds the'
				. ' namespace and the permission check.'
		);
	}

	public function test_every_endpoint_checks_permissions(): void {
		$violations = array();

		foreach ( $this->files() as $path => $file ) {
			$tokens = token_get_all( file_get_contents( $file ) );

			foreach ( $tokens as $at => $token ) {
				if ( ! self::is_call( $tokens, $at, array( 'register_rest_route' ) ) ) {
					continue;
				}

				$route     = self::arguments( $tokens, $at );
				$endpoints = substr_count( $route, "'callback'" );
				$checked   = preg_match_all(
					'/\'permission_callback\'\s*=>\s*array\(\s*\$this,\s*\'check_permissions\'\s*\)/',
					$route
				);

				if (
					0 === $endpoints
					|| $endpoints !== $checked
					|| substr_count( $route, "'permission_callback'" ) !== $checked
				) {
					$violations[] = $path . ':' . $token[2];
				}
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'Each endpoint takes \'permission_callback\' => array( $this, \'check_permissions\' ).'
		);
	}

	public function test_only_repositories_extend_the_base_repository(): void {
		$classes    = $this->classes();
		$parents    = array_column( $classes, 'parent', 'name' );
		$violations = array();

		foreach ( $classes as $class ) {
			if (
				! preg_match( '#(^|/)Repositories/#', $class['path'] )
				&& self::reaches( $class['parent'], 'BaseRepository', $parents )
			) {
				$violations[] = $class['place'];
			}
		}

		$this->assertSame(
			array(),
			$violations,
			'A class that extends BaseRepository lives in a Repositories folder.'
		);
	}

	public function test_enums_are_backed_and_live_in_an_enums_folder(): void {
		$this->assertSame(
			array(),
			$this->files_matching( '/^[ \t]*enum\s++\w++\s*+(?!:\s*(string|int)\b)/m' ),
			'An enum is backed, so its cases can be stored and sent.'
		);
		$this->assertSame(
			array(),
			$this->files_matching(
				'/^[ \t]*enum\s+\w+/m',
				array(),
				array_diff_key( $this->files(), $this->files_in( 'Enums' ) )
			),
			'An enum lives in an Enums folder.'
		);
	}

	/**
	 * The service providers.
	 *
	 * @return array<string, string> Path from includes/ => full path.
	 */
	private function service_providers(): array {
		return array_filter(
			$this->files(),
			static fn( string $path ): bool => str_ends_with( $path, 'ServiceProvider.php' ),
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * The files under includes/ inside a folder of this name, at any depth.
	 *
	 * @param string $folder
	 *
	 * @return array<string, string> Path from includes/ => full path.
	 */
	private function files_in( string $folder ): array {
		return array_filter(
			$this->files(),
			static fn( string $path ): bool => 1 === preg_match( '#(^|/)' . preg_quote( $folder, '#' ) . '/#', $path ),
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Whether a file calls register_rest_route().
	 *
	 * @param string $file
	 *
	 * @return bool
	 */
	private function registers_routes( string $file ): bool {
		return 1 === preg_match( '/' . self::call_of( 'register_rest_route' ) . '/', $this->code( $file ) );
	}

	/**
	 * Every class declared under includes/, anonymous ones left out.
	 *
	 * @return array<int, array{name: string, parent: string|null, path: string, file: string, place: string}> The
	 *         parent is the short name of the class it extends, a use alias resolved.
	 */
	private function classes(): array {
		$classes = array();

		foreach ( $this->files() as $path => $file ) {
			$tokens  = token_get_all( file_get_contents( $file ) );
			$aliases = self::aliases( $tokens );

			foreach ( $tokens as $at => $token ) {
				if ( ! is_array( $token ) || T_CLASS !== $token[0] ) {
					continue;
				}

				$before = self::neighbour( $tokens, $at, -1 );
				$name   = self::neighbour_at( $tokens, $at, 1 );

				if (
					( is_array( $before ) && in_array( $before[0], array( T_DOUBLE_COLON, T_NEW ), true ) )
					|| null === $name
					|| ! is_array( $tokens[ $name ] )
					|| T_STRING !== $tokens[ $name ][0]
				) {
					continue;
				}

				$extends = self::neighbour_at( $tokens, $name, 1 );
				$parent  = null;

				if ( is_array( $tokens[ $extends ] ) && T_EXTENDS === $tokens[ $extends ][0] ) {
					$parent = self::neighbour( $tokens, $extends, 1 )[1];
					$parent = self::short_name( $aliases[ strtolower( $parent ) ] ?? $parent );
				}

				$classes[] = array(
					'name'   => $tokens[ $name ][1],
					'parent' => $parent,
					'path'   => $path,
					'file'   => $file,
					'place'  => $path . ':' . $token[2],
				);
			}
		}

		return $classes;
	}

	/**
	 * The names a file's top-level use statements import.
	 *
	 * @param array $tokens
	 *
	 * @return array<string, string> Lowercase alias, or last part of the name => the name.
	 */
	private static function aliases( array $tokens ): array {
		$names   = array( T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR );
		$aliases = array();
		$depth   = 0;

		foreach ( $tokens as $at => $token ) {
			$depth += self::brace( $token );

			if ( 0 !== $depth || ! is_array( $token ) || T_USE !== $token[0] ) {
				continue;
			}

			$prefix = '';
			$name   = '';
			$alias  = null;
			$as     = false;

			for ( $near = $at + 1; isset( $tokens[ $near ] ) && ';' !== $tokens[ $near ]; $near++ ) {
				$part = $tokens[ $near ];
				$id   = is_array( $part ) ? $part[0] : $part;

				if ( $as && T_STRING === $id ) {
					$alias = $part[1];
					$as    = false;
				} elseif ( in_array( $id, $names, true ) ) {
					$name .= $part[1];
				} elseif ( T_AS === $id ) {
					$as = true;
				} elseif ( '{' === $id ) {
					$prefix = $name;
					$name   = '';
				} elseif ( ',' === $id || '}' === $id ) {
					$aliases[ strtolower( $alias ?? self::short_name( $name ) ) ] = $prefix . $name;

					$name  = '';
					$alias = null;
				}
			}

			if ( '' !== $name ) {
				$aliases[ strtolower( $alias ?? self::short_name( $name ) ) ] = $prefix . $name;
			}
		}

		return $aliases;
	}

	/**
	 * The last part of a class name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	private static function short_name( string $name ): string {
		$parts = explode( '\\', $name );

		return end( $parts );
	}

	/**
	 * Whether a class is the base or extends it, through the classes declared here.
	 *
	 * @param string|null                $name    Short name.
	 * @param string                     $base    Short name.
	 * @param array<string, string|null> $parents Short name => short name of the class it extends.
	 *
	 * @return bool
	 */
	private static function reaches( ?string $name, string $base, array $parents ): bool {
		for ( $seen = array(); null !== $name && ! isset( $seen[ $name ] ); $name = $parents[ $name ] ?? null ) {
			if ( $base === $name ) {
				return true;
			}

			$seen[ $name ] = true;
		}

		return false;
	}

	/**
	 * The source of the arguments of the call that starts at $at, parentheses included and comments left out.
	 *
	 * @param array $tokens
	 * @param int   $at
	 *
	 * @return string
	 */
	private static function arguments( array $tokens, int $at ): string {
		$source = '';
		$depth  = 0;

		for ( $near = self::neighbour_at( $tokens, $at, 1 ); isset( $tokens[ $near ] ); $near++ ) {
			$token = $tokens[ $near ];

			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$source .= is_array( $token ) ? $token[1] : $token;
			$depth  += array(
				'(' => 1,
				')' => -1,
			)[ is_array( $token ) ? '' : $token ] ?? 0;

			if ( 0 === $depth ) {
				break;
			}
		}

		return $source;
	}

	/**
	 * Code with the bodies of its closures and arrow functions blanked out, since they run when called, not where
	 * they are written. Line breaks are kept.
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	private static function without_closures( string $code ): string {
		$opens  = array( '(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES );
		$closes = array( ')', ']', '}' );
		$result = '';
		$state  = '';
		$depth  = 0;

		foreach ( array_slice( token_get_all( '<?php ' . $code ), 1 ) as $token ) {
			$text = is_array( $token ) ? $token[1] : $token;
			$id   = is_array( $token ) ? $token[0] : $token;

			if ( '' === $state ) {
				$state = array(
					T_FUNCTION => 'function',
					T_FN       => 'fn',
				)[ $id ] ?? '';
			} elseif ( 'function' === $state && '{' === $id ) {
				$state = 'body';
				$depth = 1;
				$text  = self::blank( $text );
			} elseif ( 'fn' === $state && T_DOUBLE_ARROW === $id ) {
				$state = 'arrow';
				$depth = 0;
			} elseif ( 'body' === $state ) {
				$depth += self::brace( $token );
				$state  = 0 === $depth ? '' : $state;
				$text   = self::blank( $text );
			} elseif ( 'arrow' === $state ) {
				if ( 0 === $depth && ( ',' === $id || ';' === $id || in_array( $id, $closes, true ) ) ) {
					$state = '';
				} else {
					$depth += in_array( $id, $opens, true ) ? 1 : 0;
					$depth -= in_array( $id, $closes, true ) ? 1 : 0;
					$text   = self::blank( $text );
				}
			}

			$result .= $text;
		}

		return $result;
	}

	/**
	 * Whether a hook we fire belongs to WordPress or WooCommerce, which document it themselves.
	 *
	 * @param string $name
	 * @param string $path Where it is fired.
	 *
	 * @return bool
	 */
	private static function is_foreign_hook( string $name, string $path ): bool {
		// The bootstrap applies WordPress's filter as WordPress does.
		if ( 'plugin_locale' === $name ) {
			return true;
		}

		// The email templates fire WooCommerce's email hooks, as WooCommerce's own templates do.
		return str_starts_with( $path, 'templates/' ) && str_starts_with( $name, 'woocommerce_email_' );
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
		return '(?<![\w$])(?<!->)(?<!::)(?<!function )(?:' . implode( '|', $functions ) . ')\s*\(';
	}

	/**
	 * Whether a file holds a WooCommerce email, directly or through our task email base.
	 *
	 * @param string $file
	 *
	 * @return bool
	 */
	private function is_email( string $file ): bool {
		return 1 === preg_match( '/\bextends\s+\\\\?(?:WC_Email|TaskEmail)\b/', $this->code( $file ) );
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
	 * Whether a docblock with a summary or an @param, not only tags such as @var, sits between the token at $at and
	 * the start of its statement, with no other hook fired in between: each hook needs its own.
	 *
	 * @param array $tokens
	 * @param int   $at
	 *
	 * @return bool
	 */
	private static function has_docblock( array $tokens, int $at ): bool {
		for ( $before = $at - 1; $before >= 0; $before-- ) {
			$token = $tokens[ $before ];

			if ( is_array( $token ) && T_DOC_COMMENT === $token[0] ) {
				return 1 === preg_match( '/^[ \t\/*]*+(?!@)[^\s*\/]|@param\b/m', $token[1] );
			}

			if (
				in_array( $token, array( ';', '{', '}' ), true )
				|| ( is_array( $token ) && in_array( $token[0], array( T_OPEN_TAG, T_CLOSE_TAG ), true ) )
				|| self::is_call( $tokens, $before, self::FIRING_FUNCTIONS )
			) {
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
	 * Where a pattern matches in a file's code.
	 *
	 * @param string $pattern
	 * @param string $code    With its lines where they are in the file.
	 * @param string $path
	 *
	 * @return string[] "path:line" for each match.
	 */
	private static function places( string $pattern, string $code, string $path ): array {
		preg_match_all( $pattern, $code, $found, PREG_OFFSET_CAPTURE );

		return array_map(
			static fn( array $hit ): string => self::line_of( $code, $hit[1], $path ),
			$found[0]
		);
	}

	/**
	 * "path:line" for an offset in a file's code.
	 *
	 * @param string $code   With its lines where they are in the file.
	 * @param int    $offset
	 * @param string $path
	 *
	 * @return string
	 */
	private static function line_of( string $code, int $offset, string $path ): string {
		return $path . ':' . ( substr_count( substr( $code, 0, $offset ), "\n" ) + 1 );
	}

	/**
	 * Where the code of the files, comments left out, matches a pattern.
	 *
	 * @param string                     $pattern
	 * @param string[]                   $allowed Paths left out, a folder ending in a slash.
	 * @param array<string, string>|null $files   Path => full path. Default the files under includes/.
	 *
	 * @return string[] "path:line" for each match.
	 */
	private function files_matching( string $pattern, array $allowed = array(), ?array $files = null ): array {
		$matching = array();

		foreach ( $files ?? $this->files() as $path => $file ) {
			foreach ( $allowed as $prefix ) {
				if ( $path === $prefix || ( str_ends_with( $prefix, '/' ) && str_starts_with( $path, $prefix ) ) ) {
					continue 2;
				}
			}

			array_push( $matching, ...self::places( $pattern, $this->code( $file ), $path ) );
		}

		return $matching;
	}

	/**
	 * The PHP files under includes/.
	 *
	 * @return array<string, string> Path from includes/, with forward slashes => full path.
	 */
	private function files(): array {
		return self::php_files( 'includes/', '' );
	}

	/**
	 * The email templates.
	 *
	 * @return array<string, string> Path from the plugin's folder, with forward slashes => full path.
	 */
	private function templates(): array {
		return self::php_files( 'templates/', 'templates/' );
	}

	/**
	 * The PHP files in a folder of the plugin, recursively.
	 *
	 * @param string $folder From the plugin's folder, ending in a slash.
	 * @param string $prefix Put before each path from the folder.
	 *
	 * @return array<string, string> Path => full path.
	 */
	private static function php_files( string $folder, string $prefix ): array {
		$root  = str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . '/' . $folder;
		$files = array();

		foreach (
			new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS )
			) as $file
		) {
			if ( 'php' === $file->getExtension() ) {
				$path = $file->getPathname();

				$files[ $prefix . substr( str_replace( '\\', '/', $path ), strlen( $root ) ) ] = $path;
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
	 * @return string With its lines where they are in the file.
	 */
	private function code( string $file ): string {
		return $this->split( $file, array() )[0];
	}

	/**
	 * A file's code, comments left out, split into the bodies of the functions named and the rest. A closure is part
	 * of the function around it. Each part keeps the other's line breaks, so its lines are where they are in the file.
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
			$text = is_array( $token ) ? $token[1] : $token;

			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$text = self::blank( $text );
			} elseif ( 0 === $depth && is_array( $token ) && T_FUNCTION === $token[0] ) {
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

			$inside = $depth > 0 ? 1 : 0;

			$parts[ $inside ]     .= $text;
			$parts[ 1 - $inside ] .= self::blank( $text );
		}

		return $parts;
	}

	/**
	 * Source with everything but its line breaks blanked out.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private static function blank( string $text ): string {
		return (string) preg_replace( '/[^\n]+/', ' ', $text );
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
