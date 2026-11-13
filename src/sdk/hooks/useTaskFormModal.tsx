import React from 'react';
import { useSidebarModal } from '@sdk/context/SidebarModalContext';
import {
	TaskForm,
	TaskFormInitialValues,
	TaskSettingsMode,
} from '@sdk/components/TaskForm';
import { Task } from '@sdk/types/task';

interface CreateTaskModalOptions {
	title: string;
	initialValues?: TaskFormInitialValues;
	onTaskSaved?: (task: Task) => void;
}

interface TaskSettingsModalOptions extends TaskSettingsMode {
	title: string;
}

interface EditTaskModalOptions {
	task: Task;
	title: string;
	onTaskSaved?: (task: Task) => void;
}

export const useTaskCreation = () => {
	const { openSidebar, closeSidebar } = useSidebarModal();

	const openCreateTaskModal = (options: CreateTaskModalOptions) => {
		openSidebar(
			<TaskForm
				initialValues={options.initialValues}
				onDone={closeSidebar}
				onTaskSaved={options.onTaskSaved}
			/>,
			{ title: options.title }
		);
	};

	/**
	 * Opens the form in settings mode: it saves no task, only hands its values
	 * to `onSave`; read-only, it shows them with only Close.
	 *
	 * @param options       The panel's title and the settings mode's props.
	 * @param options.title The panel's title.
	 */
	const openTaskSettings = ({
		title,
		...settings
	}: TaskSettingsModalOptions) => {
		openSidebar(<TaskForm settings={settings} onDone={closeSidebar} />, {
			title,
		});
	};

	return { openCreateTaskModal, openTaskSettings, closeModal: closeSidebar };
};

export const useTaskEdit = () => {
	const { openSidebar, closeSidebar } = useSidebarModal();

	const openEditTaskModal = (options: EditTaskModalOptions) => {
		openSidebar(
			<TaskForm
				task={options.task}
				onDone={closeSidebar}
				onTaskSaved={options.onTaskSaved}
			/>,
			{ title: options.title }
		);
	};

	return { openEditTaskModal, closeModal: closeSidebar };
};
