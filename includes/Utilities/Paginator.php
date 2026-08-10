<?php

namespace WPO\AOM\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object describing a single page of results.
 */
final class Paginator {
	private array $items;
	private int $total;
	private int $per_page;
	private int $current_page;

	/**
	 * Constructor.
	 *
	 * @param array<int, mixed> $items        The items on the current page.
	 * @param int               $total        Total number of items across all pages.
	 * @param int               $per_page     Items per page.
	 * @param int               $current_page The current page number (1-based).
	 */
	public function __construct( array $items, int $total, int $per_page, int $current_page ) {
		$this->items        = $items;
		$this->total        = max( 0, $total );
		$this->per_page     = max( 1, $per_page );
		$this->current_page = max( 1, $current_page );
	}

	/**
	 * Wrap a complete, unpaginated list as a single page.
	 *
	 * @param array<int, mixed> $items All items.
	 *
	 * @return self
	 */
	public static function full( array $items ): self {
		return new self( $items, count( $items ), max( 1, count( $items ) ), 1 );
	}

	/**
	 * The items on the current page.
	 *
	 * @return array<int, mixed>
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * Total number of items across all pages.
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->total;
	}

	/**
	 * Number of items per page.
	 *
	 * @return int
	 */
	public function per_page(): int {
		return $this->per_page;
	}

	/**
	 * The current page number (1-based).
	 *
	 * @return int
	 */
	public function current_page(): int {
		return $this->current_page;
	}

	/**
	 * The last (highest) page number; always at least 1.
	 *
	 * @return int
	 */
	public function last_page(): int {
		return (int) max( 1, (int) ceil( $this->total / $this->per_page ) );
	}

	/**
	 * 1-based index of the first item on the current page, or null when empty.
	 *
	 * @return int|null
	 */
	public function from(): ?int {
		if ( 0 === $this->total || empty( $this->items ) ) {
			return null;
		}

		return ( ( $this->current_page - 1 ) * $this->per_page ) + 1;
	}

	/**
	 * 1-based index of the last item on the current page, or null when empty.
	 *
	 * @return int|null
	 */
	public function to(): ?int {
		$from = $this->from();

		return null === $from ? null : $from + count( $this->items ) - 1;
	}
}
