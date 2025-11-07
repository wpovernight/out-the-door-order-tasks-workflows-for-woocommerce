import React from 'react';
import { useTasks } from '../../../context/TaskContext';

interface TaskFormProps {
	taskId?: number;
	columnId: number;
	onDone?: () => void;
}

export const TaskForm: React.FC<TaskFormProps> = ({
	taskId,
	columnId,
	onDone,
}) => {
	const { saveTask, loadTasks } = useTasks();

	const submit = async (e: React.FormEvent) => {
		e.preventDefault();
		// await saveTask();
		// await loadTasks(true);
		onDone?.();
	};

	return (
		<form onSubmit={submit} className="wpo-aom-task-form">
			<p>Form content goes here!</p>
		</form>
	);
};
