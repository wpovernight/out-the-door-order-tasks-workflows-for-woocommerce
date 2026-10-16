<?php

namespace WPO\OTD\Admin\Notices;

use Throwable;
use WPO\OTD\Core\Logger;
use WPO\OTD\Enums\NoticeType;

defined( 'ABSPATH' ) || exit;

/**
 * An admin notice. A subclass says what the notice says and whether it has anything to say; this class shows it
 * with the other admin notices, only to users with its capability and only on its screens, prints it in WordPress's
 * markup, and never lets it break an admin screen.
 */
abstract class AdminNotice {
	/**
	 * Show the notice with the other admin notices.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'show' ) );
	}

	/**
	 * Print the notice when the current user may see it, the current screen is one of its screens, and it has
	 * something to say.
	 *
	 * Never throws, since it runs on admin screens: anything that goes wrong is logged and shows nothing.
	 *
	 * @return void
	 */
	final public function show(): void {
		try {
			if ( ! current_user_can( $this->capability() ) || ! $this->is_on_its_screen() ) {
				return;
			}

			$message = $this->message();

			if ( null === $message ) {
				return;
			}

			printf(
				'<div class="notice notice-%1$s"><p>%2$s</p>%3$s</div>',
				esc_attr( $this->type()->value ),
				wp_kses_post( $message ),
				wp_kses_post( $this->details() )
			);
		} catch ( Throwable $exception ) {
			$this->log_failure( $exception );
		}
	}

	/**
	 * The kind of notice, which sets its colour.
	 *
	 * @return NoticeType
	 */
	abstract protected function type(): NoticeType;

	/**
	 * The notice's paragraph, or null when there is nothing to say and no notice is shown.
	 *
	 * Called only for a user and screen that may see the notice, so a costly check belongs here. HTML that passes
	 * through wp_kses_post(): escape the plain text in it.
	 *
	 * @return string|null
	 */
	abstract protected function message(): ?string;

	/**
	 * HTML below the paragraph, such as a list. Called after message(), and passes through wp_kses_post().
	 *
	 * @return string
	 */
	protected function details(): string {
		return '';
	}

	/**
	 * The capability a user needs to see the notice.
	 *
	 * @return string
	 */
	protected function capability(): string {
		return 'manage_woocommerce';
	}

	/**
	 * The IDs of the admin screens the notice shows on; none means every admin screen.
	 *
	 * @return string[]
	 */
	protected function screens(): array {
		return array();
	}

	/**
	 * Whether the current admin screen is one the notice shows on.
	 *
	 * @return bool
	 */
	private function is_on_its_screen(): bool {
		$screens = $this->screens();

		if ( array() === $screens ) {
			return true;
		}

		$screen = get_current_screen();

		return null !== $screen && in_array( $screen->id, $screens, true );
	}

	/**
	 * Log why the notice could not be shown, naming an exception without a message by its class. Never throws: the log
	 * may be what failed, such as while WooCommerce is not loaded.
	 *
	 * @param Throwable $exception What went wrong.
	 *
	 * @return void
	 */
	private function log_failure( Throwable $exception ): void {
		$error = '' !== $exception->getMessage() ? $exception->getMessage() : $exception::class;

		try {
			Logger::error( sprintf( 'Out the Door could not show the %1$s admin notice (%2$s).', static::class, $error ) );
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Nothing is left to report it to.
		}
	}
}
