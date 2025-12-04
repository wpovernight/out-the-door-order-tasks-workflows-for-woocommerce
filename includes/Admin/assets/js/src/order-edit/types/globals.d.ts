/**
 * Global type declarations for the WPO AOM Order Edit metabox.
 */

export interface WpoAomOrderEditData {
    apiRoot: string;
    apiNamespace: string;
    nonce: string;
    orderId: number;
    i18n: {
        loading: string;
        errorLoading: string;
        confirmationText: string;
        tasks: {
            sectionTitle: string;
            addTask: string;
            editTask: string;
            deleteTask: string;
            noTasks: string;
            active: string;
            viewFinished: string;
            hideFinished: string;
            activeTasksHeading: string;
            finishedTasksHeading: string;
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
    };
}

declare global {
	interface Window {
		WPO_AOM_OrderEdit: WpoAomOrderEditData;
	}
}

// This export is necessary to make this a module
export {};
