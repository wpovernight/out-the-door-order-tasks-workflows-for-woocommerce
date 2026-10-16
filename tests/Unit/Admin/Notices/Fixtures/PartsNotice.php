<?php

namespace WPO\OTD\Tests\Unit\Admin\Notices\Fixtures;

use Closure;
use WPO\OTD\Admin\Notices\AdminNotice;
use WPO\OTD\Enums\NoticeType;

/**
 * A notice whose parts are given. A part given as a closure is called each time it is asked for; a part left out
 * keeps the base class's default.
 */
final class PartsNotice extends AdminNotice {
	/**
	 * @param array<string, mixed> $parts type, message, details, capability and screens.
	 */
	public function __construct( private readonly array $parts ) {}

	protected function type(): NoticeType {
		return $this->parts['type'];
	}

	protected function message(): ?string {
		return $this->part( 'message' );
	}

	protected function details(): string {
		return array_key_exists( 'details', $this->parts ) ? $this->part( 'details' ) : parent::details();
	}

	protected function capability(): string {
		return $this->parts['capability'] ?? parent::capability();
	}

	protected function screens(): array {
		return $this->parts['screens'] ?? parent::screens();
	}

	/**
	 * A part, calling it when it is a closure.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	private function part( string $name ): mixed {
		return $this->parts[ $name ] instanceof Closure ? ( $this->parts[ $name ] )() : $this->parts[ $name ];
	}
}
