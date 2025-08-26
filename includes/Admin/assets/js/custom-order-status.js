jQuery( function( $ ) {

	/**
	 * Updates the preview span with the current label and background color values.
	 */
	function updatePreview() {
		const $labelInput   = $( '#wpo_aom_custom_order_status_label' );
		const $bgColorInput = $( '#wpo_aom_custom_order_status_background' );
		const $previewSpan  = $( '#wpo_aom_custom_order_status_preview' );

		if ( ! $labelInput.length || ! $bgColorInput.length || ! $previewSpan.length ) {
			return;
		}

		const label   = $labelInput.val() || 'Preview';
		const bgColor = $bgColorInput.val() || '#ffffff';
		const fgColor = getTextColor( bgColor );

		$previewSpan
			.text( label )
			.css( {
				backgroundColor: bgColor,
				color:           fgColor
			} );
	}

	/**
	 * Determines the appropriate text color (black or white) based on background color contrast.
	 *
	 * @param {string} hexcolor - The background color in hex format.
	 *
	 * @returns {string} - '#000' for black text or '#fff' for white text.
	 */
	function getTextColor( hexcolor ) {
		const hex = hexcolor.replace( '#', '' );
		const r   = parseInt( hex.substr( 0, 2 ), 16 );
		const g   = parseInt( hex.substr( 2, 2 ), 16 );
		const b   = parseInt( hex.substr( 4, 2 ), 16 );
		const yiq = ( r * 299 + g * 587 + b * 114 ) / 1000;

		return yiq >= 128 ? '#000' : '#fff';
	}

	const $labelInput   = $( '#wpo_aom_custom_order_status_label' );
	const $bgColorInput = $( '#wpo_aom_custom_order_status_background' );

	// Attach input listeners.
	$labelInput.on( 'input', updatePreview );
	$bgColorInput.on( 'input change', updatePreview );

	// Hook into Iris color picker events.
	if ( $.fn.iris ) {
		$bgColorInput.on( 'change.iris move.iris', function () {
			updatePreview();
		} );
	}

	// Trigger an initial preview update on load.
	updatePreview();

} );
