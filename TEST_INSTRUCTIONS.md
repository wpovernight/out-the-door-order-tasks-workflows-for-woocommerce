# Test Instruction for Task Manager

> ⚠️ Temporary file for testing PR. Will be removed before merge.
> The build files is also not to be included in the final merge.

## Test Instructions

- Create sample tasks using the provided code snippet below.
- Navigate to the `WooCommerce > Task Management` section in the dashboard to see the board.

> The build file is included temporary to ease the testing. However, in case you want to build the files yourself, please refer to the manual build instructions at the bottom of this document.
## Task Creation for Testing

Run the below code snippet to create sample tasks programmatically.

```php

create_sample_tasks( 10 ); // Send number of sample tasks to create.


function create_sample_tasks( int $count 15, bool $reset = true ): void {
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
		$task_field_option_repository->find_by_slug( 'to_do' )->id,
		$task_field_option_repository->find_by_slug( 'in_progress' )->id,
		$task_field_option_repository->find_by_slug( 'completed' )->id,
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
	$task_field_value_repository->get();

	foreach ( $task_field_value_repository->get() as $field_value ) {
		$task_field_value_repository->delete( $field_value->id );
	}

	echo "Sample tasks removed.\n";
}
```

---

## Manual Build Instructions
To build the Task Manager React app, run the following commands:
```bash
cd includes/Admin/assets/js/task-manager
npm install
npm run build
```