<?php

namespace WPO\AOM\Services;

use WPO\AOM\Models\CustomOrderStatus;
use WPO\AOM\Repositories\CustomOrderStatusRepository;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusService {

	protected CustomOrderStatusRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new CustomOrderStatusRepository();
	}

	/**
	 * Register the service.
	 */
	public function register(): void {
		add_filter( 'wc_order_statuses', array( $this, 'add_to_order_statuses' ) );
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_statuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_to_bulk_action' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_to_bulk_action' ) ); // HPOS support
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dynamic_style' ), 99 );
	}

	/** ================================
	 *   Integration with WooCommerce
	 *  ================================ */

	/**
	 * Add custom order statuses to the list of WooCommerce order statuses.
	 *
	 * @param array $order_statuses
	 *
	 * @return array
	 */
	public function add_to_order_statuses( array $order_statuses ): array {
		$statuses = $this->all();

		foreach ( $statuses as $status ) {
			$order_statuses[ $status->get_prefixed_status_key() ] = esc_html__( $status->label, 'wpo-aom' );
		}

		return $order_statuses;
	}

	/**
	 * Register custom order statuses with WooCommerce.
	 *
	 * @param array $order_statuses
	 *
	 * @return array
	 */
	public function register_order_statuses( array $order_statuses ): array {
		$statuses = $this->all();

		foreach ( $statuses as $status ) {
			$label = esc_html__( $status->label, 'wpo-aom' );

			$order_statuses[ $status->get_prefixed_status_key() ] = array(
				'label'                     => $label,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop(
					$label . ' <span class="count">(%s)</span>',
					$label . ' <span class="count">(%s)</span>',
					'wpo-aom'
				)
			);
		}

		return $order_statuses;
	}

	/**
	 * Add custom order statuses to bulk actions dropdown.
	 *
	 * @param array $bulk_actions
	 *
	 * @return array
	 */
	public function add_to_bulk_action( array $bulk_actions ): array {
		// We can introduce a new setting to toggle this feature if needed.
		$statuses = $this->all();

		foreach ( $statuses as $status ) {
			$bulk_actions[ 'mark_' . $status->get_prefixed_status_key() ] = sprintf( __( 'Change status to %s', 'wpo-aom' ), esc_html__( $status->label, 'wpo-aom' ) );
		}

		return $bulk_actions;
	}

	/**
	 * Add dynamic styles for custom order statuses colors.
	 *
	 * @return void
	 */
	public function add_dynamic_style() {
		$statuses   = $this->all();
		$custom_css = '';

		foreach ( $statuses as $status ) {
			$custom_css .= sprintf(
				'mark.status-%s { background-color: %s; color: %s; }',
				esc_attr( $status->status_key ),
				esc_attr( $status->background ),
				esc_attr( $this->generate_text_color( $status->background ) )
			);
		}

		wp_add_inline_style( 'woocommerce_admin_styles', $custom_css );
	}


	/** ================================
	 *   Helpers
	 *  ================================ */

	/**
	 * Generates an accessible text color based on the background color.
	 *
	 * @param string $hex_bg
	 *
	 * @return string
	 */
	private function generate_text_color( string $hex_bg ): string {
		$hex_bg = ltrim( $hex_bg, '#' );

		// Expand shorthand hex to full (e.g., 'abc' -> 'aabbcc').
		if ( strlen( $hex_bg ) === 3 ) {
			$hex_bg = $hex_bg[0] . $hex_bg[0] . $hex_bg[1] . $hex_bg[1] . $hex_bg[2] . $hex_bg[2];
		}

		// Convert hex to RGB.
		$bg_rgb = array(
			hexdec( substr( $hex_bg, 0, 2 ) ),
			hexdec( substr( $hex_bg, 2, 2 ) ),
			hexdec( substr( $hex_bg, 4, 2 ) ),
		);

		$brightness = ( ( $bg_rgb['0'] * 299 ) + ( $bg_rgb['1'] * 587 ) + ( $bg_rgb['2'] * 114 ) ) / 1000;
		$adjust     = array(
			175, // Red
			175, // Green
			150, // Blue
		);

		if ( $brightness > 125 ) {
			// Light background: darken color.
			$fg_rgb = array(
				max( 0, $bg_rgb['0'] - $adjust[0] ),
				max( 0, $bg_rgb['1'] - $adjust[1] ),
				max( 0, $bg_rgb['2'] - $adjust[2] ),
			);
		} else {
			// Dark background: lighten color.
			$fg_rgb = array(
				min( 255, $bg_rgb['0'] + $adjust[0] ),
				min( 255, $bg_rgb['1'] + $adjust[1] ),
				min( 255, $bg_rgb['2'] + $adjust[2] ),
			);
		}

		// Ensure sufficient contrast.
		if ( ! $this->has_sufficient_contrast( $fg_rgb, $bg_rgb ) ) {
			// Fallback to black or white if contrast is insufficient.
			$fg_rgb = $brightness > 125 ? array( 0, 0, 0 ) : array( 255, 255, 255 );
		}

		// Convert to hex
		return sprintf( '#%02x%02x%02x', $fg_rgb[0], $fg_rgb[1], $fg_rgb[2] );
	}

	/**
	 * Checks whether the contrast ratio between two colors meets WCAG minimum (4.5:1).
	 *
	 * @param array $foreground_rgb
	 * @param array $background_rgb
	 *
	 * @return bool True if contrast is >= 4.5, false otherwise.
	 */
	private function has_sufficient_contrast( array $foreground_rgb, array $background_rgb ): bool {
		$foreground_luminance = $this->relative_luminance( $foreground_rgb );
		$background_luminance = $this->relative_luminance( $background_rgb );

		$contrast_ratio = ( max( $foreground_luminance, $background_luminance ) + 0.05 ) /
		                  ( min( $foreground_luminance, $background_luminance ) + 0.05 );

		return $contrast_ratio >= 4.5;
	}

	/**
	 * Calculates relative luminance for an RGB color.
	 *
	 * @param array<int> $rgb RGB array [R, G, B].
	 *
	 * @return float
	 */
	private function relative_luminance( array $rgb ): float {
		$channels = array_map(
			static function ( int $channel ): float {
				$srgb = $channel / 255;

				return $srgb <= 0.03928 ? $srgb / 12.92 : pow( ( $srgb + 0.055 ) / 1.055, 2.4 );
			},
			$rgb
		);

		// Coefficients based on human perception
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/** ================================
	 *   CRUD Operations
	 *  ================================ */

	/**
	 * Return all statuses.
	 *
	 * @return CustomOrderStatus[]
	 */
	public function all(): array {
		return $this->repository->all();
	}

	/**
	 * Get a custom status by ID.
	 *
	 * @param int $id
	 *
	 * @return CustomOrderStatus|null
	 */
	public function find( int $id ): ?CustomOrderStatus {
		return $this->repository->find_by_id( $id );
	}

	/**
	 * Get a custom status by its key.
	 *
	 * @param string $status_key
	 *
	 * @return CustomOrderStatus|null
	 */
	public function find_by_key( string $status_key ): ?CustomOrderStatus {
		return $this->repository->find_by_key( $status_key );
	}

	/**
	 * Create a new custom order status.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int|false Inserted ID or false on failure
	 */
	public function create( array $data ) {
		$status = new CustomOrderStatus( $data );

		return $this->repository->insert_status( $status );
	}

	/**
	 * Update a custom order status by ID.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data
	 *
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		$existing = $this->repository->find_by_id( $id );
		if ( ! $existing ) {
			return false;
		}

		$model     = new CustomOrderStatus( array_merge( $existing->to_array(), $data ) );
		$model->id = $id;

		return $this->repository->update_status( $model );
	}

	/**
	 * Delete a custom order status by ID.
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->repository->delete_status( $id );
	}

}
