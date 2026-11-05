<?php
/**
 * `wp otd build`: builds the distributable plugin without a WordPress install.
 *
 * Loaded by the wp-cli.yml next to the plugin's main file, so any WP-CLI run from the plugin
 * folder picks it up; from elsewhere, pass `--require=<plugin>/bin/build-command.php`.
 * Registered for `before_wp_load` (like `wp i18n`), so WordPress is never looked for or loaded.
 */

namespace WPO\OTD\Build;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use WP_CLI;
use WP_CLI\Process;

use function WP_CLI\Utils\esc_cmd;
use function WP_CLI\Utils\get_temp_dir;
use function WP_CLI\Utils\normalize_path;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Builds the distributable plugin from a git commit or tag.
 *
 * Runs the same steps as the release workflow (JS build, CSS minification, production
 * Composer install, translation template) in a temporary git worktree of the chosen ref,
 * then copies everything .distignore doesn't exclude into the destination folder.
 * The current checkout and its vendor/ are never touched.
 */
final class BuildCommand {
	/**
	 * The clean-css CLI release used for minification, pinned so every build produces the same output.
	 */
	private const CLEAN_CSS = 'clean-css-cli@5.6.3';

	/**
	 * Register the command with WP-CLI.
	 *
	 * @return void
	 */
	public static function register(): void {
		WP_CLI::add_command(
			'otd build',
			new self(),
			array(
				'shortdesc' => 'Builds the distributable plugin from a git commit or tag into a folder.',
				'synopsis'  => array(
					array(
						'type'        => 'positional',
						'name'        => 'destination',
						'optional'    => false,
						'description' => 'Folder to write the built plugin into. Created if missing; an existing folder must be empty.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'ref',
						'optional'    => true,
						'default'     => 'HEAD',
						'description' => 'Commit, tag or branch to build. Uncommitted changes are never included.',
					),
					array(
						'type'        => 'flag',
						'name'        => 'skip-pot',
						'optional'    => true,
						'description' => 'Do not generate the translation template.',
					),
				),
				'when'      => 'before_wp_load',
			)
		);
	}

	/**
	 * @param string[]             $arguments Positional arguments.
	 * @param array<string, mixed> $options   Associative arguments.
	 *
	 * @return void
	 */
	public function __invoke( array $arguments, array $options ): void {
		try {
			$this->build( $arguments[0], $options['ref'] ?? 'HEAD', ! empty( $options['skip-pot'] ) );
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Build $ref into $destination.
	 *
	 * Failures throw instead of calling WP_CLI::error(), which exits and would skip
	 * the `finally` that removes the temporary worktree.
	 *
	 * @param string $destination Destination folder, absolute or relative to the current directory.
	 * @param string $ref         Git commit, tag or branch.
	 * @param bool   $skip_pot    Whether to skip the translation template.
	 *
	 * @return void
	 */
	private function build( string $destination, string $ref, bool $skip_pot ): void {
		$repository  = $this->run( 'git rev-parse --show-toplevel', dirname( __DIR__ ) );
		$commit      = $this->resolve_commit( $repository, $ref );
		$destination = $this->resolve_destination( $destination );

		// Checked now so a long build isn't wasted, and again before copying in case files appeared meanwhile.
		$this->check_destination( $destination );

		$temp   = rtrim( normalize_path( get_temp_dir() ), '/' ) . '/otd-build-' . bin2hex( random_bytes( 4 ) );
		$source = $temp . '/source';

		$cleaned = false;
		$cleanup = function () use ( &$cleaned, $repository, $source, $temp ): void {
			if ( ! $cleaned ) {
				$cleaned = true;
				$this->remove_worktree( $repository, $source, $temp );
			}
		};

		// Ctrl+C skips `finally`, but exiting from the interrupt handler still runs shutdown functions.
		register_shutdown_function( $cleanup );
		$this->exit_on_interrupt( true );

		try {
			WP_CLI::log( sprintf( 'Checking out %s (%s) into %s', $ref, substr( $commit, 0, 10 ), $source ) );
			$this->run( esc_cmd( 'git worktree add --detach %s %s', $source, $commit ), $repository );

			$plugin = $this->plugin_headers( $source );

			$this->check_stable_tag( $source, $plugin['version'] );
			$this->build_assets( $source );

			if ( ! $skip_pot ) {
				$this->make_pot( $source, $plugin['domain'] );
			}

			$destination = $this->create_destination( $destination );

			WP_CLI::log( sprintf( 'Copying distributable files into %s', $destination ) );
			$count = $this->copy_distributable( $source, $destination );
		} finally {
			$cleanup();
			$this->exit_on_interrupt( false );
		}

		WP_CLI::success( sprintf( 'Built %s %s from %s: %d file(s) in %s', $plugin['name'], $plugin['version'], $ref, $count, $destination ) );
	}

	/**
	 * Resolve $ref to a full commit hash.
	 *
	 * @param string $repository Repository root.
	 * @param string $ref        Git commit, tag or branch.
	 *
	 * @return string
	 */
	private function resolve_commit( string $repository, string $ref ): string {
		$result = Process::create( esc_cmd( 'git rev-parse --verify --quiet %s', $ref . '^{commit}' ), $repository, null )->run();

		if ( 0 !== $result->return_code ) {
			throw new RuntimeException( sprintf( 'Unknown commit, tag or branch: %s', $ref ) );
		}

		return trim( $result->stdout );
	}

	/**
	 * Make the destination absolute, resolving it against the current directory.
	 *
	 * @param string $destination Destination folder, absolute or relative to the current directory.
	 *
	 * @return string
	 */
	private function resolve_destination( string $destination ): string {
		// "/x", "\x", "C:/x", "C:\x" and "\\server\x" are absolute.
		if ( ! preg_match( '#^(?:[a-zA-Z]:)?[/\\\\]#', $destination ) ) {
			$destination = getcwd() . '/' . $destination;
		}

		return normalize_path( $destination );
	}

	/**
	 * Refuse a destination that is a file or a folder with content; a missing one is fine.
	 *
	 * Requiring an empty folder means the build never deletes anything, and also rules out
	 * the repository or a folder containing it.
	 *
	 * @param string $destination Absolute destination path.
	 *
	 * @return void
	 */
	private function check_destination( string $destination ): void {
		if ( ! file_exists( $destination ) ) {
			return;
		}

		if ( ! is_dir( $destination ) ) {
			throw new RuntimeException( sprintf( 'Destination is a file, not a folder: %s', $destination ) );
		}

		$entries = scandir( $destination );

		if ( false === $entries ) {
			throw new RuntimeException( sprintf( 'Could not read the destination folder: %s', $destination ) );
		}

		if ( array_diff( $entries, array( '.', '..' ) ) ) {
			throw new RuntimeException( sprintf( 'Destination folder is not empty: %s', $destination ) );
		}
	}

	/**
	 * Check the destination again and create it, only once the build has succeeded.
	 *
	 * @param string $destination Absolute destination path.
	 *
	 * @return string The destination's canonical path.
	 */
	private function create_destination( string $destination ): string {
		$this->check_destination( $destination );

		if ( ! $this->mkdir( $destination ) ) {
			throw new RuntimeException( sprintf( 'Could not create the destination folder: %s', $destination ) );
		}

		return normalize_path( (string) realpath( $destination ) );
	}

	/**
	 * Warn when readme.txt's "Stable tag" differs from the plugin's version, which the release workflow refuses.
	 *
	 * @param string $source  Worktree root.
	 * @param string $version The main plugin file's "Version" header.
	 *
	 * @return void
	 */
	private function check_stable_tag( string $source, string $version ): void {
		$readme = $source . '/readme.txt';

		if ( ! is_file( $readme ) ) {
			return;
		}

		$stable_tag = $this->file_headers( $readme, array( 'stable_tag' => 'Stable tag' ) )['stable_tag'];

		if ( $stable_tag !== $version ) {
			WP_CLI::warning( sprintf( 'readme.txt has "Stable tag: %s" but the plugin\'s version is %s; the release workflow would refuse this.', $stable_tag, $version ) );
		}
	}

	/**
	 * Make Ctrl+C (and, where available, SIGTERM) exit through shutdown functions, or restore the default.
	 *
	 * @param bool $enable Whether to install the handlers or remove them.
	 *
	 * @return void
	 */
	private function exit_on_interrupt( bool $enable ): void {
		static $handler = null;

		if ( null === $handler ) {
			$handler = static function (): void {
				WP_CLI::log( 'Interrupted; removing the temporary build folder.' );
				exit( 130 );
			};
		}

		if ( function_exists( 'sapi_windows_set_ctrl_handler' ) ) {
			sapi_windows_set_ctrl_handler( $handler, $enable );
		}

		if ( function_exists( 'pcntl_signal' ) && function_exists( 'pcntl_async_signals' ) ) {
			pcntl_async_signals( true );
			pcntl_signal( SIGINT, $enable ? $handler : SIG_DFL );
			pcntl_signal( SIGTERM, $enable ? $handler : SIG_DFL );
		}
	}

	/**
	 * Read the name, version and text domain from the ref's main plugin file.
	 *
	 * The file is looked up rather than hardcoded, since older refs use a different file name and text domain.
	 *
	 * @param string $source Worktree root.
	 *
	 * @return array{name: string, version: string, domain: string}
	 */
	private function plugin_headers( string $source ): array {
		foreach ( (array) glob( $source . '/*.php' ) as $file ) {
			$headers = $this->file_headers(
				$file,
				array(
					'name'    => 'Plugin Name',
					'version' => 'Version',
					'domain'  => 'Text Domain',
				)
			);

			if ( '' !== $headers['name'] ) {
				return $headers;
			}
		}

		throw new RuntimeException( 'No main plugin file (with a "Plugin Name" header) found at the root of the ref.' );
	}

	/**
	 * Read header fields from the first 8 KB of a file, the way WordPress's get_file_data() does.
	 *
	 * @param string                $file    File path.
	 * @param array<string, string> $headers Keys mapped to header names.
	 *
	 * @return array<string, string> The keys mapped to the values found, '' for missing ones.
	 */
	private function file_headers( string $file, array $headers ): array {
		$data   = str_replace( "\r", "\n", (string) file_get_contents( $file, false, null, 0, 8192 ) );
		$values = array();

		foreach ( $headers as $key => $name ) {
			$values[ $key ] = preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $data, $match )
				? trim( (string) preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) )
				: '';
		}

		return $values;
	}

	/**
	 * Build the JS, minify the CSS and install the production Composer dependencies.
	 *
	 * @param string $source Worktree root.
	 *
	 * @return void
	 */
	private function build_assets( string $source ): void {
		if ( is_file( $source . '/package.json' ) ) {
			$this->step( is_file( $source . '/package-lock.json' ) ? 'npm ci' : 'npm install', $source );
			$this->step( 'npm run build', $source );
		}

		$stylesheets = $this->css_sources( $source );

		if ( $stylesheets ) {
			// --batch writes each file's minified copy next to it; the suffix makes that <name>.min.css (the default is <name>-min.css).
			$this->step( 'npx --yes ' . self::CLEAN_CSS . ' --batch --batch-suffix .min ' . implode( ' ', array_map( 'escapeshellarg', $stylesheets ) ), $source );
		}

		if ( is_file( $source . '/composer.json' ) ) {
			$this->step( 'composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist', $source );
		}
	}

	/**
	 * The hand-written stylesheets under assets/css/, relative to $source.
	 *
	 * @param string $source Worktree root.
	 *
	 * @return string[]
	 */
	private function css_sources( string $source ): array {
		$folder = $source . '/assets/css';

		if ( ! is_dir( $folder ) ) {
			return array();
		}

		$root        = strlen( normalize_path( $source ) ) + 1;
		$stylesheets = array();

		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $folder, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$name = $file->getFilename();

			if ( '.css' === substr( $name, -4 ) && '.min.css' !== substr( $name, -8 ) ) {
				$stylesheets[] = substr( normalize_path( $file->getPathname() ), $root );
			}
		}

		sort( $stylesheets );

		return $stylesheets;
	}

	/**
	 * Generate languages/<domain>.pot.
	 *
	 * Runs after the JS build, because the template harvests strings from the built bundles in assets/js/.
	 *
	 * @param string $source Worktree root.
	 * @param string $domain Text domain, also used as the slug.
	 *
	 * @return void
	 */
	private function make_pot( string $source, string $domain ): void {
		if ( '' === $domain ) {
			throw new RuntimeException( 'The main plugin file has no "Text Domain" header; pass --skip-pot to build without a translation template.' );
		}

		$this->mkdir( $source . '/languages' );

		$command = esc_cmd(
			'i18n make-pot %s %s --slug=%s --domain=%s --exclude=src,docs',
			$source,
			$source . '/languages/' . $domain . '.pot',
			$domain,
			$domain
		);

		WP_CLI::log( '> wp ' . $command );

		$result = WP_CLI::runcommand(
			$command,
			array(
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			)
		);

		if ( 0 !== $result->return_code ) {
			throw new RuntimeException( sprintf( "`wp %s` failed:\n%s", $command, trim( $result->stderr . "\n" . $result->stdout ) ) );
		}
	}

	/**
	 * Copy every file .distignore doesn't exclude from $source into $destination.
	 *
	 * @param string $source      Worktree root.
	 * @param string $destination Empty destination folder.
	 *
	 * @return int The number of files copied.
	 */
	private function copy_distributable( string $source, string $destination ): int {
		$patterns = $this->distignore_patterns( $source . '/.distignore' );
		$root     = strlen( normalize_path( $source ) ) + 1;

		$filter = static function ( SplFileInfo $file ) use ( $patterns, $root ): bool {
			$relative = substr( normalize_path( $file->getPathname() ), $root );

			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $relative ) ) {
					return false;
				}
			}

			return true;
		};

		// Filtering before recursion prunes excluded folders (node_modules/, src/, ...) instead of walking them.
		$files = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
				$filter
			)
		);

		$count = 0;

		foreach ( $files as $file ) {
			$target = $destination . '/' . substr( normalize_path( $file->getPathname() ), $root );

			if ( ! $this->mkdir( dirname( $target ) ) || ! copy( $file->getPathname(), $target ) ) {
				throw new RuntimeException( sprintf( 'Could not copy %s', $target ) );
			}

			++$count;
		}

		return $count;
	}

	/**
	 * Compile .distignore into regexes matched against paths relative to the plugin root.
	 *
	 * Follows how the release workflow feeds .distignore to `zip -x`: patterns are anchored at
	 * the root, `*` also matches "/", and a trailing slash means the folder and everything in it.
	 *
	 * @param string $file Path to .distignore.
	 *
	 * @return string[]
	 */
	private function distignore_patterns( string $file ): array {
		if ( ! is_file( $file ) ) {
			throw new RuntimeException( 'The ref has no .distignore, so there is no way to tell which files to ship.' );
		}

		$patterns = array();

		foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}

			$line      = ltrim( $line, '/' );
			$is_folder = '/' === substr( $line, -1 );
			$regex     = str_replace( array( '\*', '\?' ), array( '.*', '.' ), preg_quote( rtrim( $line, '/' ), '#' ) );

			$patterns[] = $is_folder ? "#^{$regex}(/.*)?$#" : "#^{$regex}$#";
		}

		return $patterns;
	}

	/**
	 * Remove the temporary worktree, falling back to deleting it by hand.
	 *
	 * A failure here only warns: the build itself has already finished or failed.
	 *
	 * @param string $repository Repository root.
	 * @param string $source     Worktree root.
	 * @param string $temp       Temporary folder holding the worktree.
	 *
	 * @return void
	 */
	private function remove_worktree( string $repository, string $source, string $temp ): void {
		Process::create( esc_cmd( 'git worktree remove --force %s', $source ), $repository, null )->run();

		if ( file_exists( $temp ) && ! $this->delete( $temp ) ) {
			WP_CLI::warning( sprintf( 'Could not delete the temporary build folder; remove it by hand: %s', $temp ) );
		}

		Process::create( 'git worktree prune', $repository, null )->run();
	}

	/**
	 * Create a folder and any missing parents.
	 *
	 * @param string $folder Folder path.
	 *
	 * @return bool Whether the folder exists afterwards.
	 */
	private function mkdir( string $folder ): bool {
		return is_dir( $folder ) || @mkdir( $folder, 0777, true ) || is_dir( $folder ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the result is checked.
	}

	/**
	 * Delete a folder and everything in it.
	 *
	 * Files are made writable first, since Windows refuses to delete read-only ones.
	 *
	 * @param string $folder Folder path.
	 *
	 * @return bool Whether the folder is gone.
	 */
	private function delete( string $folder ): bool {
		$entries = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $folder, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $entries as $entry ) {
			$path = $entry->getPathname();

			if ( $entry->isDir() && ! $entry->isLink() ) {
				@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- checked by the final file_exists().
			} else {
				@chmod( $path, 0666 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		@rmdir( $folder ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return ! file_exists( $folder );
	}

	/**
	 * Run a build step, logging it first so a slow step doesn't look like a hang.
	 *
	 * @param string $command Shell command.
	 * @param string $cwd     Working directory.
	 *
	 * @return void
	 */
	private function step( string $command, string $cwd ): void {
		WP_CLI::log( '> ' . $command );
		$this->run( $command, $cwd );
	}

	/**
	 * Run a shell command and return its trimmed output.
	 *
	 * The environment is passed as null so the command inherits PATH; an empty array would clear it.
	 *
	 * @param string $command Shell command.
	 * @param string $cwd     Working directory.
	 *
	 * @return string
	 */
	private function run( string $command, string $cwd ): string {
		$result = Process::create( $command, $cwd, null )->run();

		if ( 0 !== $result->return_code ) {
			throw new RuntimeException( sprintf( "`%s` failed:\n%s", $command, trim( $result->stderr . "\n" . $result->stdout ) ) );
		}

		return trim( $result->stdout );
	}
}

BuildCommand::register();
