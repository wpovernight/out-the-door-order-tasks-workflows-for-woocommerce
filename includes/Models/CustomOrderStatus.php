<?php

namespace WPO\AOM\Models;

use WPO\AOM\Contracts\ArraySerializableModel;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatus implements ArraySerializableModel {

	public int $id;
	public string $status_key;
	public string $label;
	public string $background;
	public string $foreground;

	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id         = absint( $data['id'] ?? 0 );
		$this->label      = $data['label'] ?? '';
		$this->status_key = ! empty( $data['status_key'] )
			? sanitize_title( $data['status_key'] )
			: sanitize_title( $this->label );
		$this->background = $data['background'] ?? '#ccc';
		$this->foreground = $data['foreground'] ?? $this->get_foreground_color( $this->background );
	}

	/**
	 * Convert the model to an array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'status_key' => $this->status_key,
			'label'      => $this->label,
			'background' => $this->background,
			'foreground' => $this->foreground,
		);
	}

	/**
	 * Convert the model to an array suitable for database storage.
	 *
	 * @return array
	 */
	public function to_db_array(): array {
		return array(
			'id'         => $this->id,
			'status_key' => $this->status_key,
			'label'      => $this->label,
			'background' => $this->background,
		);
	}

	/**
	 * Get the prefixed status key for WooCommerce.
	 *
	 * @return string
	 */
	public function get_prefixed_status_key(): string {
		return 'wc-' . $this->status_key;
	}

	/**
	 * Generates an accessible text color based on the background color.
	 *
	 * @param string $hex_bg
	 *
	 * @return string
	 */
	private function get_foreground_color( string $hex_bg ): string {
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

}
