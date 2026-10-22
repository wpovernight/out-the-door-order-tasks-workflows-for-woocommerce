<?php

namespace WPO\OTD\Exceptions;

use RuntimeException;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * A task was created and fully saved, but a step after its creation failed.
 *
 * The task is kept, so a caller that retries on this exception creates it a second time. The failure that stopped the
 * later step is the previous exception.
 */
final class TaskCreatedWithErrorException extends RuntimeException {
	private int $task_id;

	/**
	 * Constructor.
	 *
	 * @param int       $task_id  The ID of the task that was created.
	 * @param Throwable $previous The failure after the task was created.
	 */
	public function __construct( int $task_id, Throwable $previous ) {
		$this->task_id = $task_id;

		parent::__construct(
			"Task {$task_id} was created, but a step after its creation failed: " . $previous->getMessage(),
			0,
			$previous
		);
	}

	/**
	 * The ID of the task that was created.
	 *
	 * @return int
	 */
	public function get_task_id(): int {
		return $this->task_id;
	}
}
