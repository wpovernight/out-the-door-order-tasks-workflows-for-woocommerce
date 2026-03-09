import React, {
	useContext,
	useMemo,
	useState,
	useCallback,
	useRef,
} from 'react';
import { useTasks } from '@shared/context/TaskContext';
import { getFieldRawValues, isTaskArchived } from '@shared/utils/fieldUtils';
import {
	Task,
	TASK_FINISH_STATUS_SLUG,
	TASK_UNFINISHED_STATUS_SLUG,
} from '@shared/types/task';
import { AsyncLoaderStatus } from '@shared/hooks/useAsyncLoader';
import { __ } from '@wordpress/i18n';

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
	loadingStatus: AsyncLoaderStatus;
	loadingError: Error | null;
	loadTaskData: (force?: boolean) => Promise<void>;
	refreshTasks: () => Promise<void>;
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
	const {
		tasks,
		saveTask,
		deleteTask: deleteTaskFromGlobal,
		loadTasks,
		loadTaskFields,
		loadFieldOptions,
	} = useTasks();

	// Loading state management
	const [loadingStatus, setLoadingStatus] =
		useState<AsyncLoaderStatus>('idle');
	const [loadingError, setLoadingError] = useState<Error | null>(null);
	const [hasLoaded, setHasLoaded] = useState<boolean>(false);
	const isLoadingRef = useRef<boolean>(false);

	const orderTasks = useMemo(() => {
		return tasks.filter((task) => {
			// Exclude archived tasks.
			if (isTaskArchived(task)) {
				return false;
			}

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

	const activeTasks = useMemo(() => {
		return orderTasks.filter(
			(task) => task.status !== TASK_FINISH_STATUS_SLUG
		);
	}, [orderTasks]);

	const finishedTasks = useMemo(() => {
		return orderTasks.filter(
			(task: { status: string }) =>
				task.status === TASK_FINISH_STATUS_SLUG
		);
	}, [orderTasks]);

	// Load all task-related data
	const loadTaskData = useCallback(
		async (force: boolean = false) => {
			// Prevent concurrent calls
			if (isLoadingRef.current || (hasLoaded && !force)) {
				return;
			}

			isLoadingRef.current = true;
			setLoadingStatus('loading');
			setLoadingError(null);

			try {
				await Promise.all([
					loadTasks(force),
					loadTaskFields(force),
					loadFieldOptions('status', force),
				]);

				setHasLoaded(true);
				setLoadingStatus('loaded');
			} catch (err) {
				setLoadingStatus('error');
				setLoadingError(
					err instanceof Error
						? err
						: new Error(__('Failed to load tasks', 'wpo-aom'))
				);
				console.error('Error loading tasks:', err);
			} finally {
				isLoadingRef.current = false;
			}
		},
		[loadTasks, loadTaskFields, loadFieldOptions, hasLoaded]
	);

	const refreshTasks = useCallback(async () => {
		await loadTaskData(true);
	}, [loadTaskData]);

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
			task.status === TASK_FINISH_STATUS_SLUG
				? TASK_UNFINISHED_STATUS_SLUG
				: TASK_FINISH_STATUS_SLUG;
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
				loadingStatus,
				loadingError,
				loadTaskData,
				refreshTasks,
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
