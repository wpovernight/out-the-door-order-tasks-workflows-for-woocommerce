<?php

namespace WPO\AOM\CLI;

use WP_CLI;
use WPO\AOM\Services\FulfillmentService;

defined( 'ABSPATH' ) || exit;

final class FulfillmentsClearCommand extends AbstractCommand {
	private readonly FulfillmentService $fulfillment_service;

	/**
	 * Constructor.
	 *
	 * @param FulfillmentService $fulfillment_service
	 */
	public function __construct( FulfillmentService $fulfillment_service ) {
		$this->fulfillment_service = $fulfillment_service;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_command_name(): string {
		return 'fulfillments clear';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Removes all fulfillment data from orders and order items.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_synopsis(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function __invoke( array $arguments, array $options ): void {
		// Honours the global --yes flag; prompts otherwise.
		WP_CLI::confirm( 'Remove ALL fulfillment data from every order? This cannot be undone.', $options );

		$cleared = $this->fulfillment_service->clear_all();

		WP_CLI::success( sprintf( 'Cleared fulfillment data from %d order(s).', $cleared ) );
	}
}