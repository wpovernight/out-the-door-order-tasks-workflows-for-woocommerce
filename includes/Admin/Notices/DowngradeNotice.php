<?php

namespace WPO\OTD\Admin\Notices;

use WPO\OTD\Core\Installer;
use WPO\OTD\Enums\NoticeType;
use WPO\OTD\OutTheDoor;

defined( 'ABSPATH' ) || exit;

/**
 * Warns users who can manage plugins that the data was written by a newer release this one may not handle.
 */
final class DowngradeNotice extends AdminNotice {
	/**
	 * @return NoticeType
	 */
	protected function type(): NoticeType {
		return NoticeType::WARNING;
	}

	/**
	 * @return string|null
	 */
	protected function message(): ?string {
		if ( ! Installer::is_downgraded() ) {
			return null;
		}

		return '<strong>' . esc_html( OutTheDoor::PLUGIN_NAME ) . '</strong> '
			. esc_html__( 'was downgraded. Its data was saved by a newer version, which this version may not handle correctly. Update to the latest version, or restore a backup taken before the update.', 'out-the-door-order-tasks-workflows-for-woocommerce' );
	}

	/**
	 * Only users who can install or update plugins can fix it.
	 *
	 * @return string
	 */
	protected function capability(): string {
		return 'activate_plugins';
	}
}
