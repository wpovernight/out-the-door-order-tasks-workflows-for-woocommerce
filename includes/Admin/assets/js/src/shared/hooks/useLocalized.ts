import { WPOAOMLocalized } from '../types/globals';

/**
 * Hook to safely access localized data for WPO AOM Task Manager.
 * Provides type-safe access to window.WPO_AOM_TaskManager.
 *
 * @return {WPOAOMLocalized} The localized data object.
 */
export function useLocalized(): WPOAOMLocalized {
	if (!window.WPO_AOM_TaskManager) {
		// eslint-disable-next-line no-console
		console.error(
			'WPO_AOM_TaskManager is not defined. Localized data missing.'
		);

		// Return defaults to prevent crashes
		return {
			apiRoot: '',
			apiNamespace: '',
			nonce: '',
			loading: 'Loading...',
			errorLoading: 'Error loading tasks. Please try again.',
			views: {},
			kanban: {
				addTask: 'Add Task',
				editTask: 'Edit Task',
				options: 'Options',
				create: 'Create',
				dueDateLabel: 'Due date: ',
			},
			form: {
				labels: {
					status: 'Status',
					priority: 'Priority',
					dueDate: 'Due Date',
					title: 'Title',
					description: 'Description',
					associatedOrders: 'Associated Orders',
				},
				placeholders: {
					select: 'Select',
					taskName: 'Write a name for your task.',
					taskDescription: 'Describe the task.',
					searchOrders:
						'Search orders by number, customer, address...',
				},
			},
			actions: {
				edit: 'Edit',
				delete: 'Delete',
				deleteTask: 'Delete task',
				cancel: 'Cancel',
				apply: 'Apply',
				createTask: 'Create Task',
				updateTask: 'Update Task',
			},
		};
	}

	return window.WPO_AOM_TaskManager;
}
