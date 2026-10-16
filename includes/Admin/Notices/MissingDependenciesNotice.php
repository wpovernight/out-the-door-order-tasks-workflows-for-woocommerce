<?php

namespace WPO\OTD\Admin\Notices;

use WPO\OTD\Enums\NoticeType;

defined( 'ABSPATH' ) || exit;

/**
 * Tells users who can manage plugins why the plugin cannot run, listing each unmet requirement.
 */
final class MissingDependenciesNotice extends AdminNotice {
	/**
	 * @var string[]
	 */
	private readonly array $errors;

	/**
	 * Constructor.
	 *
	 * @param string[] $errors Each unmet requirement, as plain text.
	 */
	public function __construct( array $errors ) {
		$this->errors = $errors;
	}

	/**
	 * @return NoticeType
	 */
	protected function type(): NoticeType {
		return NoticeType::ERROR;
	}

	/**
	 * @return string|null
	 */
	protected function message(): ?string {
		if ( array() === $this->errors ) {
			return null;
		}

		return '<strong>' . esc_html__( 'Out the Door – Order Tasks & Workflows for WooCommerce', 'out-the-door-order-tasks-workflows-for-woocommerce' ) . '</strong> '
			. esc_html__( 'can’t run because:', 'out-the-door-order-tasks-workflows-for-woocommerce' );
	}

	/**
	 * @return string
	 */
	protected function details(): string {
		return '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $this->errors ) ) . '</li></ul>';
	}

	/**
	 * Only users who can install or update plugins can meet the requirements.
	 *
	 * @return string
	 */
	protected function capability(): string {
		return 'activate_plugins';
	}
}
