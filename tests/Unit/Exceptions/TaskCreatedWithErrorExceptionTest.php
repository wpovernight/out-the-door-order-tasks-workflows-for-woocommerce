<?php

namespace WPO\OTD\Tests\Unit\Exceptions;

use WPO\OTD\Exceptions\TaskCreatedWithErrorException;
use WPO\OTD\Tests\TestCase;

class TaskCreatedWithErrorExceptionTest extends TestCase {
	public function test_it_names_the_created_task_and_keeps_the_original_failure(): void {
		$previous  = new \LogicException( 'Listener failed', 7 );
		$exception = new TaskCreatedWithErrorException( 42, $previous );

		$this->assertSame( 42, $exception->get_task_id() );
		$this->assertSame( $previous, $exception->getPrevious() );
		$this->assertSame( 'Task 42 was created, but a step after its creation failed: Listener failed', $exception->getMessage() );
		$this->assertInstanceOf( \RuntimeException::class, $exception );
	}
}
