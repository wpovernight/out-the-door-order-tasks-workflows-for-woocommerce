# Test Helpers

> ⚠️ This is a temporary file for test and will be removed before the release.


## Automatic Task Creation

Run the below code snippet to create sample tasks programmatically.

```php

create_sample_tasks( 10 ); // Send number of sample tasks to create.


function create_sample_tasks( int $count = 15, bool $reset = true ): void {
	if ( $reset ) {
		remove_sample_tasks();
	}

	$task_repository              = new \WPO\AOM\Repositories\TaskRepository();
	$task_field_repository        = new \WPO\AOM\Repositories\TaskFieldRepository();
	$task_field_option_repository = new \WPO\AOM\Repositories\TaskFieldOptionRepository();
	$value_repository             = new \WPO\AOM\Repositories\TaskFieldValueRepository();

	// Default field IDs.
	$fields = array(
		'status'   => $task_field_repository->find_by_slug( 'status' )->id,
		'position' => $task_field_repository->find_by_slug( 'position' )->id,
		'creator'  => $task_field_repository->find_by_slug( 'creator' )->id,
		'order'    => $task_field_repository->find_by_slug( 'order' )->id,
		'priority' => $task_field_repository->find_by_slug( 'priority' )->id,
		'due_date' => $task_field_repository->find_by_slug( 'due_date' )->id,
	);

	$user_id  = 1;
	$order_id = 1885; // Update as you want

	$statuses = array(
		$task_field_option_repository->find_by_slug( 'not_started' )->id,
		$task_field_option_repository->find_by_slug( 'in_progress' )->id,
		$task_field_option_repository->find_by_slug( 'done' )->id,
	);
	$priorities = array(
		$task_field_option_repository->find_by_slug( 'low' )->id,
		$task_field_option_repository->find_by_slug( 'medium' )->id,
		$task_field_option_repository->find_by_slug( 'high' )->id,
		$task_field_option_repository->find_by_slug( 'critical' )->id,
	);

	// Track the position count per status.
	$status_positions = array_fill_keys( $statuses, 0 );

	for ( $i = 1; $i <= $count; $i++ ) {
		$title       = sprintf( 'Sample Task %d', $i );
		$description = sprintf( 'This is sample task number %d.', $i );

		// Randomly select status and priority.
		$status   = $statuses[ array_rand( $statuses ) ];
		$priority = $priorities[ array_rand( $priorities ) ];

		// Increment position for the selected status.
		$status_positions[ $status ]++;

		$values = array(
			'status'   => $status,
			'position' => $status_positions[ $status ],
			'creator'  => $user_id,
			'order'    => $order_id,
			'priority' => $priority,
			'due_date' => date( 'Y-m-d', strtotime( sprintf( '+%d days', rand( 2, 10 ) ) ) ),
		);

		create_task( $task_repository, $value_repository, $fields, $title, $description, $values );
	}

	printf( "%d sample tasks created.\n", $count );
}

function create_task(
	\WPO\AOM\Repositories\TaskRepository $task_repo,
	\WPO\AOM\Repositories\TaskFieldValueRepository $value_repo,
	array $fields,
	string $title,
	string $description,
	array $values
): void {
	$task_id = $task_repo->insert(
		array(
			'title'       => $title,
			'description' => $description,
		)
	);

	foreach ( $values as $slug => $value ) {
		if ( isset( $fields[ $slug ] ) ) {
			$value_repo->insert(
				array(
					'task_id'  => $task_id,
					'field_id' => $fields[ $slug ],
					'value'    => $value,
				)
			);
		}
	}
}

function remove_sample_tasks(): void {
	$task_repository = new \WPO\AOM\Repositories\TaskRepository();
	$tasks           = $task_repository->get();

	foreach ( $tasks as $task ) {
		$task_repository->delete( $task->id );
	}

	// Also remove associated field values.
	$task_field_value_repository = new \WPO\AOM\Repositories\TaskFieldValueRepository();

	foreach ( $task_field_value_repository->get() as $field_value ) {
		$task_field_value_repository->delete( $field_value->id );
	}

	echo "Sample tasks removed.\n";
}

```

## Reinstall Database Schema

Run the below code snippet to drop existing AOM tables and reinstall the database schema.

```php
function reinstall_database_schema(): void {
	// Remove tables if they exist.
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'wpo_aom_task_field_values',
		$wpdb->prefix . 'wpo_aom_task_field_options',
		$wpdb->prefix . 'wpo_aom_task_fields',
		$wpdb->prefix . 'wpo_aom_tasks',
		$wpdb->prefix . 'wpo_aom_custom_statuses',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
	echo "Existing AOM tables dropped.\n";

	// Remove version option.
	delete_option( 'wpo_aom_version' );
	echo "AOM version option removed.\n";

	// Run plugin installation to recreate tables and insert default data.
	\WPO\AOM\Core\Install::instance()->install();
	echo "AOM database schema updated.\n";
}

```

---

## Manual Build Instructions

To build the JavaScript assets for the AOM plugin, follow the steps below:

```bash
cd includes/Admin/assets/js/src
npm install
npm run build
```