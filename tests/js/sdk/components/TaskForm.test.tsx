import React from 'react';
import { screen } from '@testing-library/react';
import { envelope, mockFetch, ok, renderWithProviders } from '@otd-test-utils';
import { SidebarModalProvider, TaskForm } from '@sdk';
import type { FieldOption } from '@sdk';

const option = (fieldId: number, slug: string): FieldOption => ({
	id: fieldId * 10,
	field_id: fieldId,
	slug,
	label: slug,
	color: '#ffffff',
	position: 1,
});

/**
 * The task form, once its fields and options have loaded.
 */
const renderForm = async () => {
	mockFetch({
		'GET /tasks/fields': ok(
			envelope({
				status: {
					id: 1,
					label: 'Status',
					type: 'select',
					slug: 'status',
					is_required: true,
					is_editable: true,
					is_protected: true,
				},
			})
		),
		'GET /tasks/fields/status/options': ok(envelope([option(1, 'todo')])),
		'GET /tasks/fields/priority/options': ok(
			envelope([option(2, 'normal')])
		),
	});

	renderWithProviders(
		<SidebarModalProvider>
			<TaskForm />
		</SidebarModalProvider>
	);

	await screen.findByLabelText('Title');
};

describe('TaskForm', () => {
	it("draws its own title and description fields with core's shared field look", async () => {
		await renderForm();

		expect(screen.getByLabelText('Title')).toHaveClass('wpo-otd-input');
		expect(screen.getByLabelText('Description')).toHaveClass(
			'wpo-otd-input'
		);
	});

	it("leaves the order search field, another component's, to the form's scoped styles", async () => {
		await renderForm();

		expect(screen.getByLabelText('Associated Orders')).not.toHaveClass(
			'wpo-otd-input'
		);
	});
});
