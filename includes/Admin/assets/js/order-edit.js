/**
 * Toggle between view and edit mode for a fulfillment cell.
 *
 * @param {HTMLElement} fulfillmentCell - The fulfillment cell element to toggle. (TD)
 * @param {string} mode - The mode to switch to: 'edit' or 'view'.
 */
function toggleFulfillmentMode(fulfillmentCell, mode) {
	if (!fulfillmentCell) {
		return;
	}

	const viewDiv = fulfillmentCell.querySelector('.view');
	const editDiv = fulfillmentCell.querySelector('.edit');

	if (!viewDiv || !editDiv) {
		return;
	}

	const actionsContainer = editDiv.querySelector('.wpo-aom-fulfillment-actions');

	if (mode === "edit") {
		editDiv.style.display = 'flex';
		viewDiv.style.display = 'none';

		if (actionsContainer) {
			actionsContainer.style.display = 'flex';
		}
	} else if (mode === "view") {
		viewDiv.style.display = 'flex';
		editDiv.style.display = 'none';

		if (actionsContainer) {
			actionsContainer.style.display = 'none';
		}
	}
}

document.addEventListener('DOMContentLoaded', function () {
	// Toggle edit mode when edit button is clicked.
	const editButtons = document.querySelectorAll('.wpo-aom-edit-fulfillment');
	editButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			const fulfillmentCell = button.closest('.wpo-aom-fulfillment');
			toggleFulfillmentMode(fulfillmentCell, 'edit');
		});
	});

	// Toggle edit mode off when cancel button is clicked.
	const cancelButtons = document.querySelectorAll('.wpo-aom-cancel-fulfillment');
	cancelButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			const fulfillmentCell = button.closest('.wpo-aom-fulfillment');
			toggleFulfillmentMode(fulfillmentCell, 'view');
		});
	});

	// Save fulfillment when save button is clicked.
	const saveButtons = document.querySelectorAll('.wpo-aom-save-fulfillment');
	saveButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			const fulfillmentCell = button.closest('.wpo-aom-fulfillment');
			const itemId = button.getAttribute('data-item-id');
			const fulfillmentId = button.getAttribute('data-fulfillment-id');
			const quantityInput = fulfillmentCell.querySelector('.wpo-aom-fulfillment-quantity');

			if (!quantityInput) {
				return;
			}

			const quantity = quantityInput.value;

			// Send AJAX request
			const formData = new FormData();
			formData.append('action', 'wpo_aom_save_fulfillment');
			formData.append('nonce', WPO_AOM_OrderEdit.nonce);
			formData.append('item_id', itemId);
			formData.append('fulfillment_id', fulfillmentId);
			formData.append('quantity', quantity);

			// Disable button during request
			button.disabled = true;

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					if (data.success) {
						// Update view mode with new quantity
						const viewDiv = fulfillmentCell.querySelector('.view');
						if (viewDiv && data.data.html) {
							const statusTag = viewDiv.querySelector('.wpo-aom-tag');
							if (statusTag) {
								statusTag.outerHTML = data.data.html;
							}
						}

						// Toggle back to view mode
						toggleFulfillmentMode(fulfillmentCell, 'view');

						// Update quantity input value
						quantityInput.value = quantity;
					} else {
						alert(data.data.message || 'Error saving fulfillment');
					}
				})
				.catch(function (error) {
					console.error('Error:', error);
					alert('Error saving fulfillment');
				})
				.finally(function () {
					button.disabled = false;
				});
		});
	});
});