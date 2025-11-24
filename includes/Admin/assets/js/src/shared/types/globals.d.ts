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
		delete: string;
		deleteTask: string;
		cancel: string;
		apply: string;
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
