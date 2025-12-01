import React from 'react';
import { useSidebarModal } from '@shared/context/SidebarModalContext';
import { TaskForm } from '@shared/components/TaskForm';
import { Task } from '@shared/types/task';

interface CreateTaskModalOptions {
	columnId?: number;
	onTaskSaved: (task: Task) => void;
	title: string;
}

interface EditTaskModalOptions {
	task: Task;
	onTaskSaved: (task: Task) => void;
	title: string;
}

export const useTaskCreation = () => {
	const { openSidebar, closeSidebar } = useSidebarModal();

	const openCreateTaskModal = (options: CreateTaskModalOptions) => {
		openSidebar(
			<TaskForm
				columnId={options.columnId}
				onDone={closeSidebar}
				onTaskSaved={options.onTaskSaved}
			/>,
			{ title: options.title }
		);
	};

	return { openCreateTaskModal, closeModal: closeSidebar };
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
