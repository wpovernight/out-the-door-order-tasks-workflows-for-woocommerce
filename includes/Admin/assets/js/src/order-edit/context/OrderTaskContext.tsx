import React, { useContext, useMemo } from 'react';
import { useTasks } from '@shared/context/TaskContext';
import { getFieldRawValues } from '@shared/utils/fieldUtils';
import { Task } from '@shared/types/task';

// Type for creating/updating tasks via API
export type TaskPayload = Partial<Task> & {
	field_values?: Array<{
		field_id: number;
		field_slug: string;
		value: string | string[] | number;
	}>;
};

interface OrderTaskContextType {
	orderId: number;
	orderTasks: Task[];
	activeTasks: Task[];
	activeCount: number;
	finishedTasks: Task[];
	finishedCount: number;
	createTask: (taskData: TaskPayload) => Promise<Task>;
	updateTask: (taskId: number, taskData: TaskPayload) => Promise<Task>;
	deleteTask: (taskId: number) => Promise<void>;
	toggleTaskCompletion: (taskId: number) => Promise<void>;
}

const OrderTaskContext = React.createContext<OrderTaskContextType | undefined>(
	undefined
);

export const OrderTaskProvider: React.FC<{
	orderId: number;
	children: React.ReactNode;
}> = ({ orderId, children }) => {
	const { tasks, saveTask, deleteTask: deleteTaskFromGlobal } = useTasks();

	const orderTasks = useMemo(() => {
		return tasks.filter((task) => {
			const orderIdValues = getFieldRawValues(task, 'order');
			if (!orderIdValues || orderIdValues.length === 0) {
				return false;
			}

			// Convert both to numbers for comparison since order IDs from API might be strings
			return orderIdValues.some(
				(value) => Number(value) === Number(orderId)
			);
		});
	}, [tasks, orderId]);

	const finishedTaskStatus = 'completed'; // ToDo: make configurable

	const activeTasks = useMemo(() => {
		return orderTasks.filter((task) => task.status !== finishedTaskStatus);
	}, [orderTasks]);

	const finishedTasks = useMemo(() => {
		return orderTasks.filter(
			(task: { status: string }) => task.status === finishedTaskStatus
		);
	}, [orderTasks]);

	// Create a new task for this order
	const createTask = async (taskData: TaskPayload): Promise<Task> => {
		// Automatically add the order ID to field_values if not present
		const existingFieldValues = taskData.field_values || [];

		const taskWithOrder: TaskPayload = {
			...taskData,
			field_values: existingFieldValues,
		};

		return await saveTask(taskWithOrder);
	};

	// Update an existing task
	const updateTask = async (
		taskId: number,
		taskData: TaskPayload
	): Promise<Task> => {
		return await saveTask(taskData, taskId);
	};

	// Delete a task
	const deleteTask = async (taskId: number): Promise<void> => {
		await deleteTaskFromGlobal(taskId);
	};

	// Toggle task completion status
	const toggleTaskCompletion = async (taskId: number): Promise<void> => {
		const task = orderTasks.find((t) => t.id === taskId);
		if (!task) {
			return;
		}

		const newStatus =
			task.status === finishedTaskStatus ? 'pending' : finishedTaskStatus;
		await saveTask({ status: newStatus }, taskId);
	};

	return (
		<OrderTaskContext.Provider
			value={{
				orderId,
				orderTasks,
				activeTasks,
				activeCount: activeTasks.length,
				finishedTasks,
				finishedCount: finishedTasks.length,
				createTask,
				updateTask,
				deleteTask,
				toggleTaskCompletion,
			}}
		>
			{children}
		</OrderTaskContext.Provider>
	);
};

export const useOrderTask = (): OrderTaskContextType => {
	const context = useContext(OrderTaskContext);
	if (!context) {
		throw new Error(
			'useOrderTask must be used within an OrderTaskProvider'
		);
	}
	return context;
};
