<?php

namespace WPO\OTD\Admin\Notices;

use WPO\OTD\Enums\NoticeType;
use WPO\OTD\OutTheDoor;

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
	 * Only when a requirement is unmet.
	 *
	 * @return bool
	 */
	protected function should_show(): bool {
		return array() !== $this->errors;
	}

	/**
	 * @return string
	 */
	protected function message(): string {
		return '<strong>' . esc_html( OutTheDoor::PLUGIN_NAME ) . '</strong> '
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
