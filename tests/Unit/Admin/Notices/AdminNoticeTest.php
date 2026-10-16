<?php

namespace WPO\OTD\Tests\Unit\Admin\Notices;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;
use RuntimeException;
use WPO\OTD\Admin\Notices\AdminNotice;
use WPO\OTD\Core\Logger;
use WPO\OTD\Enums\NoticeType;
use WPO\OTD\Tests\TestCase;
use WPO\OTD\Tests\Unit\Admin\Notices\Fixtures\PartsNotice;

class AdminNoticeTest extends TestCase {
	/**
	 * @var Mockery\MockInterface
	 */
	private $wc_logger;

	/**
	 * Whether the current user has every capability.
	 *
	 * @var bool
	 */
	private bool $user_can = true;

	/**
	 * The capabilities checked, in order.
	 *
	 * @var string[]
	 */
	private array $checked = array();

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'current_user_can' )->alias(
			function ( string $capability ): bool {
				$this->checked[] = $capability;

				return $this->user_can;
			}
		);

		// The WooCommerce logger is memoized in a static property.
		$this->set_static_property( Logger::class, 'wc_logger', null );
		$this->wc_logger = Mockery::mock( 'WC_Logger_Interface' );
		Functions\when( 'wc_get_logger' )->justReturn( $this->wc_logger );
	}

	protected function tearDown(): void {
		$this->set_static_property( Logger::class, 'wc_logger', null );

		parent::tearDown();
	}

	public function test_it_is_shown_with_the_other_admin_notices(): void {
		$notice = $this->notice();

		Actions\expectAdded( 'admin_notices' )->once()->with( array( $notice, 'show' ) );

		$notice->register_hooks();
	}

	public function test_prints_the_message_as_a_paragraph_in_wordpress_markup(): void {
		$this->assertSame(
			'<div class="notice notice-warning"><p>Check <a href="#">this</a>.</p></div>',
			$this->show( $this->notice( array( 'message' => 'Check <a href="#">this</a>.' ) ) )
		);
	}

	public function test_prints_the_details_below_the_paragraph(): void {
		$this->assertSame(
			'<div class="notice notice-warning"><p>It failed:</p><ul><li>One</li></ul></div>',
			$this->show( $this->notice( array( 'message' => 'It failed:', 'details' => '<ul><li>One</li></ul>' ) ) )
		);
	}

	public function test_each_type_has_its_own_class(): void {
		foreach ( NoticeType::cases() as $type ) {
			$this->assertStringStartsWith(
				'<div class="notice notice-' . $type->value . '">',
				$this->show( $this->notice( array( 'type' => $type ) ) )
			);
		}
	}

	public function test_the_message_and_the_details_pass_through_kses(): void {
		Functions\when( 'wp_kses_post' )->alias( static fn( $html ) => "[kses:{$html}]" );

		$this->assertSame(
			'<div class="notice notice-warning"><p>[kses:Message]</p>[kses:<ul></ul>]</div>',
			$this->show( $this->notice( array( 'details' => '<ul></ul>' ) ) )
		);
	}

	public function test_nothing_is_shown_without_a_message(): void {
		$this->assertSame( '', $this->show( $this->notice( array( 'message' => null ) ) ) );
	}

	public function test_store_managers_see_it_by_default(): void {
		$this->assertNotSame( '', $this->show( $this->notice() ) );
		$this->assertSame( array( 'manage_woocommerce' ), $this->checked );
	}

	public function test_users_without_its_capability_see_nothing_and_its_message_is_never_asked_for(): void {
		$this->user_can = false;
		$asked          = false;

		$notice = $this->notice(
			array(
				'capability' => 'activate_plugins',
				'message'    => static function () use ( &$asked ): string {
					$asked = true;

					return 'Message';
				},
			)
		);

		$this->assertSame( '', $this->show( $notice ) );
		$this->assertSame( array( 'activate_plugins' ), $this->checked );
		$this->assertFalse( $asked, 'A costly check must not run for users who cannot see the notice.' );
	}

	public function test_without_screens_it_shows_on_every_screen_without_looking_the_screen_up(): void {
		Functions\expect( 'get_current_screen' )->never();

		$this->assertNotSame( '', $this->show( $this->notice() ) );
	}

	public function test_it_shows_on_its_screens(): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'woocommerce_page_b' ) );

		$this->assertNotSame( '', $this->show( $this->notice( array( 'screens' => array( 'woocommerce_page_a', 'woocommerce_page_b' ) ) ) ) );
	}

	public function test_it_shows_nothing_on_other_screens(): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'dashboard' ) );

		$this->assertSame( '', $this->show( $this->notice( array( 'screens' => array( 'woocommerce_page_a' ) ) ) ) );
	}

	public function test_it_shows_nothing_when_the_screen_is_unknown(): void {
		Functions\when( 'get_current_screen' )->justReturn( null );

		$this->assertSame( '', $this->show( $this->notice( array( 'screens' => array( 'woocommerce_page_a' ) ) ) ) );
	}

	public function test_a_message_that_throws_is_logged_and_shows_nothing(): void {
		$this->wc_logger->shouldReceive( 'log' )->once()->with(
			'error',
			'Out the Door could not show the ' . PartsNotice::class . ' admin notice (Count failed).',
			array( 'source' => 'wpo-otd' )
		);

		$this->assertSame( '', $this->show( $this->notice( array( 'message' => self::throws( 'Count failed' ) ) ) ) );
	}

	public function test_details_that_throw_print_nothing_at_all(): void {
		$this->wc_logger->shouldReceive( 'log' )->once();

		$this->assertSame( '', $this->show( $this->notice( array( 'details' => self::throws( 'Details failed' ) ) ) ), 'Half a notice must not be printed.' );
	}

	public function test_an_exception_without_a_message_is_named_by_its_class(): void {
		$this->wc_logger->shouldReceive( 'log' )->once()->with( 'error', Mockery::pattern( '/ admin notice \(RuntimeException\)\.$/' ), Mockery::any() );

		$this->show( $this->notice( array( 'message' => self::throws( '' ) ) ) );
	}

	public function test_a_log_that_cannot_be_written_never_escapes(): void {
		$this->wc_logger->shouldReceive( 'log' )->once()->andThrow( new RuntimeException( 'Log is full' ) );

		$this->assertSame( '', $this->show( $this->notice( array( 'message' => self::throws( 'Count failed' ) ) ) ) );
	}

	/**
	 * A warning notice saying "Message", with the given parts instead.
	 *
	 * @param array<string, mixed> $parts
	 *
	 * @return AdminNotice
	 */
	private function notice( array $parts = array() ): AdminNotice {
		return new PartsNotice( $parts + array( 'type' => NoticeType::WARNING, 'message' => 'Message' ) );
	}

	/**
	 * A part that throws when asked for.
	 *
	 * @param string $message The exception's message.
	 *
	 * @return callable
	 */
	private static function throws( string $message ): callable {
		return static function () use ( $message ): string {
			throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test data, never printed.
		};
	}

	/**
	 * What a notice prints.
	 *
	 * @param AdminNotice $notice
	 *
	 * @return string
	 */
	private function show( AdminNotice $notice ): string {
		ob_start();
		$notice->show();

		return (string) ob_get_clean();
	}
}
