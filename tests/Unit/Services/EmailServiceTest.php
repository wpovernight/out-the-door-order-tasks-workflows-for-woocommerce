<?php

namespace WPO\OTD\Tests\Unit\Services;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use ReflectionClass;
use WPO\OTD\Services\EmailService;
use WPO\OTD\Tests\TestCase;

/**
 * Field formatting for the task emails. Registering the email classes and rendering the
 * templates need WooCommerce, see Integration\Services\EmailServiceTest.
 */
class EmailServiceTest extends TestCase {
	private EmailService $service;

	protected function setUp(): void {
		parent::setUp();

		// Formatting never touches the task manager the constructor asks for.
		$this->service = ( new ReflectionClass( EmailService::class ) )->newInstanceWithoutConstructor();

		Functions\when( 'get_option' )->justReturn( 'Y-m-d' );
		Functions\when( 'date_i18n' )->alias( static fn( $format, $timestamp ) => gmdate( $format, $timestamp ) );
		Functions\when( 'get_userdata' )->alias(
			static fn( $id ) => 7 === $id ? (object) array( 'display_name' => 'Jane Doe' ) : false
		);
	}

	public function test_registers_the_task_actions_with_the_mailer(): void {
		$this->assertSame(
			array( 'woocommerce_order_status_completed', 'wpo_otd_task_created', 'wpo_otd_task_updated', 'wpo_otd_task_moved' ),
			$this->service->register_email_actions( array( 'woocommerce_order_status_completed' ) )
		);
	}

	/**
	 * @dataProvider select_provider
	 */
	public function test_select_fields( $value, array $field, ?string $expected ): void {
		$this->assertSame( $expected, $this->service->format_by_field_type( 'status', $value, $field ) );
	}

	public function select_provider(): array {
		$field = array(
			'options' => array(
				array( 'id' => 1, 'label' => 'Not started' ),
				array( 'id' => 3, 'label' => 'Done' ),
			),
		);

		return array(
			'resolved label wins'           => array( array( 'raw' => 3, 'resolved' => array( 'label' => 'Shipped' ) ), $field, 'Shipped' ),
			'option id'                     => array( 3, $field, 'Done' ),
			'option id as a string'         => array( '1', $field, 'Not started' ),
			'list of ids uses the first'    => array( array( 3, 1 ), $field, 'Done' ),
			'empty list'                    => array( array( 0 ), $field, 'None' ),
			'unknown id is shown as is'     => array( 99, $field, '99' ),
			'no options, plain value'       => array( 5, array(), '5' ),
			'no options, list of values'    => array( array( 5, 6 ), array(), '5' ),
		);
	}

	public function test_priority_is_a_select_field_too(): void {
		$this->assertSame( 'High', $this->service->format_by_field_type( 'priority', 2, array( 'options' => array( array( 'id' => 2, 'label' => 'High' ) ) ) ) );
	}

	/**
	 * @dataProvider date_provider
	 */
	public function test_date_fields( string $slug, $value, string $expected ): void {
		$this->assertSame( $expected, $this->service->format_by_field_type( $slug, $value, array() ) );
	}

	public function date_provider(): array {
		return array(
			'resolved date'            => array( 'due_date', array( 'raw' => 'x', 'resolved' => '2025-03-04T10:00:00+00:00' ), '2025-03-04' ),
			'unparseable resolved'     => array( 'due_date', array( 'resolved' => 'not a date' ), '' ),
			'raw date'                 => array( 'done_date', array( 'raw' => '2025-03-04 10:00:00' ), '2025-03-04' ),
			'empty raw'                => array( 'done_date', array( 'raw' => '' ), '' ),
			'plain string'             => array( 'archived_date', '2025-03-04', '2025-03-04' ),
			'list uses the first'      => array( 'due_date', array( '2025-03-04', '2025-05-06' ), '2025-03-04' ),
			'list with an empty first' => array( 'due_date', array( '' ), '' ),
			'empty'                    => array( 'due_date', null, '' ),
			'unparseable'              => array( 'due_date', 'soon', '' ),
		);
	}

	/**
	 * @dataProvider user_provider
	 */
	public function test_user_fields( $value, string $expected ): void {
		$this->assertSame( $expected, $this->service->format_by_field_type( 'creator', $value, array() ) );
	}

	public function user_provider(): array {
		return array(
			'resolved display name'      => array( array( 'resolved' => array( 'display_name' => 'Ann', 'username' => 'ann' ) ), 'Ann' ),
			'resolved username fallback' => array( array( 'resolved' => array( 'username' => 'ann' ) ), 'ann' ),
			'resolved but empty'         => array( array( 'resolved' => array() ), 'Unassigned' ),
			'raw user id'                => array( array( 'raw' => 7 ), 'Jane Doe' ),
			'plain user id'              => array( 7, 'Jane Doe' ),
			'unknown user'               => array( 8, 'Unassigned' ),
			'list without a raw id'      => array( array(), 'Unassigned' ),
		);
	}

	/**
	 * @dataProvider number_provider
	 */
	public function test_number_fields( $value, string $expected ): void {
		$this->assertSame( $expected, $this->service->format_by_field_type( 'order', $value, array() ) );
	}

	public function number_provider(): array {
		return array(
			'plain'                  => array( 42, '42' ),
			'raw'                    => array( array( 'raw' => 42.0 ), '42' ),
			'raw list'               => array( array( 'raw' => array( 42, 0, 43 ) ), '42, 43' ),
			'plain list drops empty' => array( array( 42, '', 43 ), '42, 43' ),
		);
	}

	public function test_other_fields_use_the_first_value_and_can_be_filtered(): void {
		$field = array( 'slug' => 'notes' );

		Filters\expectApplied( 'wpo_otd_email_task_updated_field_value' )
			->once()
			->with( 'first', 'notes', $field )
			->andReturn( 'filtered' );

		$this->assertSame( 'filtered', $this->service->format_by_field_type( 'notes', array( 'first', 'second' ), $field ) );
	}

	public function test_other_fields_without_a_value_are_null(): void {
		$this->assertNull( $this->service->format_by_field_type( 'notes', null, array() ) );
		$this->assertSame( '12', $this->service->format_by_field_type( 'notes', 12, array() ) );
	}

	public function test_the_field_type_map_can_be_filtered(): void {
		Filters\expectApplied( 'wpo_otd_email_task_updated_field_types' )->once()->andReturnUsing(
			static function ( array $types ) {
				$types['date'][] = 'ship_by';

				return $types;
			}
		);

		$this->assertSame( '2025-03-04', $this->service->format_by_field_type( 'ship_by', '2025-03-04 08:00:00', array() ) );
	}
}
