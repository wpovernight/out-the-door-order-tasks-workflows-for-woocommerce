<?php

namespace WPO\OTD\Tests\Unit\Enums;

use WPO\OTD\Enums\NoticeType;
use WPO\OTD\Tests\TestCase;

class NoticeTypeTest extends TestCase {
	public function test_values_are_wordpress_notice_class_suffixes(): void {
		$this->assertSame(
			array( 'error', 'warning', 'success', 'info' ),
			array_map( static fn( NoticeType $type ) => $type->value, NoticeType::cases() )
		);
	}
}
