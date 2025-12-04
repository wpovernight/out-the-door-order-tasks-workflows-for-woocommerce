import React from 'react';
import { useSidebarModal } from '@shared/context/SidebarModalContext';
import { TaskForm } from '@shared/components/TaskForm';
import { Task } from '@shared/types/task';

interface CreateTaskModalOptions {
	title: string;
	columnId?: number;
	orderId?: number;
	onTaskSaved?: (task: Task) => void;
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
				columnId={options.columnId}
				orderId={options.orderId}
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
