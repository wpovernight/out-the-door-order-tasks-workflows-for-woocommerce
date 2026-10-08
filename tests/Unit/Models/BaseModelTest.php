<?php

namespace WPO\OTD\Tests\Unit\Models;

use DateTime;
use Mockery;
use WPO\OTD\Models\BaseModel;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Repositories\RepositoryRegistry;
use WPO\OTD\Tests\TestCase;

class BaseModelTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->set_static_property( RepositoryRegistry::class, 'map', array() );
	}

	protected function tearDown(): void {
		$this->set_static_property( RepositoryRegistry::class, 'map', array() );

		parent::tearDown();
	}

	public function test_fill_sets_known_properties_only(): void {
		$model = new Widget( array( 'id' => 3, 'name' => 'Old' ) );

		$model->fill( array( 'name' => 'New', 'unknown' => 'ignored' ) );

		$this->assertSame( 'New', $model->name );
		$this->assertFalse( property_exists( $model, 'unknown' ) );
	}

	public function test_fill_never_changes_guarded_properties(): void {
		$model = new Widget( array( 'id' => 3, 'name' => 'Widget' ) );

		$model->fill( array( 'id' => 99, 'name' => 'Renamed' ) );

		$this->assertSame( 3, $model->id );
		$this->assertSame( 'Renamed', $model->name );
	}

	public function test_fill_cannot_reach_the_models_own_bookkeeping(): void {
		$model = new Widget( array( 'id' => 3, 'name' => 'Widget' ) );

		// Emptying the guard list first would otherwise make the ID writable.
		$model->fill(
			array(
				'guarded'           => array(),
				'non_db_properties' => array(),
				'id'                => 99,
				'secret'            => 'changed',
			)
		);

		$this->assertSame( 3, $model->id );
		$this->assertSame( 'kept', $model->secret_value() );
		$this->assertArrayNotHasKey( 'scratch', $model->to_db_array(), 'The non-DB property list is intact.' );

		$model->fill( array( 'id' => 99 ) );
		$this->assertSame( 3, $model->id, 'The guard list is intact.' );
	}

	public function test_to_array_returns_public_data_with_iso_dates(): void {
		$model = new Widget(
			array(
				'id'         => 3,
				'name'       => 'Widget',
				'created_at' => '2025-01-02 03:04:05',
			)
		);

		$data = $model->to_array();

		$this->assertSame( 3, $data['id'] );
		$this->assertSame( 'Widget', $data['name'] );
		$this->assertSame( ( new DateTime( '2025-01-02 03:04:05' ) )->format( 'c' ), $data['created_at'] );
		$this->assertSame( 'scratch', $data['scratch'], 'Non-DB properties are still part of the array form.' );
		$this->assertArrayNotHasKey( 'guarded', $data );
		$this->assertArrayNotHasKey( 'non_db_properties', $data );
	}

	public function test_to_db_array_drops_the_id_bookkeeping_and_non_db_properties(): void {
		$data = ( new Widget( array( 'id' => 0, 'name' => 'Widget' ) ) )->to_db_array();

		$this->assertArrayNotHasKey( 'id', $data );
		$this->assertArrayNotHasKey( 'scratch', $data );
		$this->assertArrayNotHasKey( 'updated_at', $data, 'The database maintains updated_at.' );
		$this->assertArrayNotHasKey( 'guarded', $data );
		$this->assertArrayNotHasKey( 'non_db_properties', $data );
		$this->assertSame( 'Widget', $data['name'] );
	}

	public function test_to_db_array_keeps_created_at_only_for_new_rows(): void {
		$new      = new Widget( array( 'id' => 0, 'name' => 'New', 'created_at' => '2025-01-02 03:04:05' ) );
		$existing = new Widget( array( 'id' => 7, 'name' => 'Existing', 'created_at' => '2025-01-02 03:04:05' ) );

		$this->assertSame( '2025-01-02 03:04:05', $new->to_db_array()['created_at'] );
		$this->assertArrayNotHasKey( 'created_at', $existing->to_db_array() );
	}

	public function test_to_db_array_formats_other_dates_for_mysql(): void {
		$model            = new Widget( array( 'id' => 7, 'name' => 'Widget' ) );
		$model->published = new DateTime( '2025-06-07 08:09:10' );

		$this->assertSame( '2025-06-07 08:09:10', $model->to_db_array()['published'] );
	}

	public function test_has_many_queries_the_related_repository_by_the_local_key(): void {
		$parts      = array( new Widget( array( 'id' => 1, 'name' => 'Part' ) ) );
		$repository = Mockery::mock( BaseRepository::class );
		$repository->shouldReceive( 'find_all_by' )->once()->with( 'widget_id', 5 )->andReturn( $parts );
		RepositoryRegistry::register( Widget::class, static fn() => $repository );

		$this->assertSame( $parts, ( new Widget( array( 'id' => 5, 'name' => 'Parent' ) ) )->parts() );
	}

	public function test_has_one_queries_the_related_repository_by_the_local_key(): void {
		$related    = new Widget( array( 'id' => 9, 'name' => 'Related' ) );
		$repository = Mockery::mock( BaseRepository::class );
		$repository->shouldReceive( 'find_by' )->once()->with( 'widget_id', 5 )->andReturn( $related );
		RepositoryRegistry::register( Widget::class, static fn() => $repository );

		$this->assertSame( $related, ( new Widget( array( 'id' => 5, 'name' => 'Parent' ) ) )->manual() );
	}

	public function test_belongs_to_one_queries_the_owner_by_the_foreign_key(): void {
		$owner      = new Widget( array( 'id' => 2, 'name' => 'Owner' ) );
		$repository = Mockery::mock( BaseRepository::class );
		$repository->shouldReceive( 'find_by' )->once()->with( 'id', 2 )->andReturn( $owner );
		RepositoryRegistry::register( Widget::class, static fn() => $repository );

		$model            = new Widget( array( 'id' => 5, 'name' => 'Child' ) );
		$model->parent_id = 2;

		$this->assertSame( $owner, $model->parent() );
	}

	public function test_relationships_without_a_key_skip_the_query(): void {
		$repository = Mockery::mock( BaseRepository::class );
		$repository->shouldNotReceive( 'find_by', 'find_all_by' );
		RepositoryRegistry::register( Widget::class, static fn() => $repository );

		$unsaved = new Widget( array( 'id' => 0, 'name' => 'Unsaved' ) );

		$this->assertSame( array(), $unsaved->parts() );
		$this->assertNull( $unsaved->manual() );
		$this->assertNull( $unsaved->parent(), 'parent_id is null.' );
	}
}

/**
 * A minimal concrete model.
 */
class Widget extends BaseModel {
	public int $id;
	public string $name;
	public ?int $parent_id = null;
	public ?DateTime $published = null;
	public string $scratch = 'scratch';
	public DateTime $created_at;
	public DateTime $updated_at;

	protected array $non_db_properties = array( 'scratch' );

	protected string $secret = 'kept';

	public function __construct( array $data ) {
		$this->id         = (int) ( $data['id'] ?? 0 );
		$this->name       = $data['name'];
		$this->created_at = new DateTime( $data['created_at'] ?? 'now' );
		$this->updated_at = new DateTime( 'now' );
	}

	public function secret_value(): string {
		return $this->secret;
	}

	public function parts(): array {
		return $this->has_many( self::class, 'widget_id' );
	}

	public function manual(): ?BaseModel {
		return $this->has_one( self::class, 'widget_id' );
	}

	public function parent(): ?BaseModel {
		return $this->belongs_to_one( self::class, 'parent_id' );
	}
}
