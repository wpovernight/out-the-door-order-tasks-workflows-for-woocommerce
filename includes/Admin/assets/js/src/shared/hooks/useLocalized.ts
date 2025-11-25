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
			calendar: {
				todaysTasks: "Today's tasks",
				task: 'Task',
				priority: 'Priority',
				status: 'Status',
				dueDate: 'Due date',
				description: 'Description',
				noTasksFound: 'No tasks found for the selected date range',
				viewModes: {
					byDay: 'By day',
					byWeek: 'By week',
					byMonth: 'By month',
				},
				dateRangePresets: {
					today: 'Today',
					yesterday: 'Yesterday',
					currentWeek: 'Current Week',
					lastWeek: 'Last Week',
					currentMonth: 'Current Month',
					lastMonth: 'Last Month',
					custom: 'Custom',
				},
				selectDate: 'Select a date',
				previousMonth: 'Previous month',
				nextMonth: 'Next month',
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
				editTask: 'Edit task',
				delete: 'Delete',
				deleteTask: 'Delete task',
				cancel: 'Cancel',
				clear: 'Clear',
				apply: 'Apply',
				actions: 'Actions',
				createTask: 'Create Task',
				updateTask: 'Update Task',
			},
		};
	}

	return window.WPO_AOM_TaskManager;
}
