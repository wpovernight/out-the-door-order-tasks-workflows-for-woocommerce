/**
 * Global type declarations for the WPO AOM Task Manager plugin.
 */

export interface WPOAOMLocalized {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
	loading: string;
	errorLoading: string;
	views: Record<string, string>;
	calendar: {
		task: string;
		tasks: string;
		priority: string;
		status: string;
		dueDate: string;
		description: string;
		noTasksFound: string;
		viewModes: {
			byDay: string;
			byWeek: string;
			byMonth: string;
		};
		dateRangePresets: {
			today: string;
			yesterday: string;
			currentWeek: string;
			lastWeek: string;
			currentMonth: string;
			lastMonth: string;
			custom: string;
		};
		selectDate: string;
		previousMonth: string;
		nextMonth: string;
	};
	kanban: {
		addTask: string;
		editTask: string;
		options: string;
		create: string;
		dueDateLabel: string;
	};
	form: {
		labels: {
			status: string;
			priority: string;
			dueDate: string;
			title: string;
			description: string;
			associatedOrders: string;
		};
		placeholders: {
			select: string;
			taskName: string;
			taskDescription: string;
			searchOrders: string;
		};
	};
	actions: {
		edit: string;
		editTask: string;
		delete: string;
		deleteTask: string;
		cancel: string;
		clear: string;
		apply: string;
		actions: string;
		createTask: string;
		updateTask: string;
	};
}

declare global {
	interface Window {
		WPO_AOM_TaskManager: WPOAOMLocalized;
	}
}

// This export is necessary to make this a module
export {};
