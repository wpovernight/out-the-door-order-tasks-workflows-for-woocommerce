import React, { useState, useCallback, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { Task } from '@shared/types/task';
import { TaskCard } from '@shared/components/TaskCard';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useTaskEdit, useTaskCreation } from '@shared/hooks/useTaskFormModal';
import { EmptyState, ErrorState } from '@shared/components/LoadingSkeleton';
import { TaskCardSkeleton } from '@shared/components/TaskCardSkeleton';
import { useTasks } from '@shared/context/TaskContext';

const ARCHIVE_OVERLAY_DURATION = 5000;

const ActiveTasks: React.FC = () => {
	const {
		activeTasks,
		deleteTask,
		loadingStatus,
		loadingError,
		refreshTasks,
	} = useOrderTask();
	const { archivePageUrl } = useOrderEditData();
	const { openEditTaskModal } = useTaskEdit();
	const { archiveTask, unarchiveTask } = useTasks();

	// Track recently archived tasks to show overlay before removing
	const [recentlyArchived, setRecentlyArchived] = useState<Map<number, Task>>(
		new Map()
	);
	const [fadingOut, setFadingOut] = useState<Set<number>>(new Set());
	const timersRef = useRef<Map<number, ReturnType<typeof setTimeout>>>(
		new Map()
	);

	// Start fade-out process for a task, which will eventually remove it from the recently archived list.
	const startFadeOut = useCallback((taskId: number) => {
		setFadingOut((prev) => new Set(prev).add(taskId));

		// Remove after fade animation completes (300ms)
		setTimeout(() => {
			setRecentlyArchived((prev) => {
				const next = new Map(prev);
				next.delete(taskId);
				return next;
			});
			setFadingOut((prev) => {
				const next = new Set(prev);
				next.delete(taskId);
				return next;
			});
		}, 300);
	}, []);

	const handleArchiveClick = useCallback(
		async (taskId: number): Promise<boolean> => {
			const task = activeTasks.find((t) => t.id === taskId);
			if (!task) {
				return false;
			}

			// Snapshot the task for the overlay, then call the API immediately
			setRecentlyArchived((prev) => new Map(prev).set(taskId, task));

			const result = await archiveTask(taskId);

			if (!result) {
				// Archive failed, remove overlay so the task reappears normally
				setRecentlyArchived((prev) => {
					const next = new Map(prev);
					next.delete(taskId);
					return next;
				});
				return false;
			}

			// Start fade-out timer for the overlay
			const timer = setTimeout(() => {
				startFadeOut(taskId);
				timersRef.current.delete(taskId);
			}, ARCHIVE_OVERLAY_DURATION);
			timersRef.current.set(taskId, timer);

			return true;
		},
		[activeTasks, archiveTask, startFadeOut]
	);

	const handleUndoArchive = useCallback(
		async (taskId: number) => {
			// Clear the fade-out timer
			const timer = timersRef.current.get(taskId);
			if (timer) {
				clearTimeout(timer);
				timersRef.current.delete(taskId);
			}

			// Remove overlay immediately
			setRecentlyArchived((prev) => {
				const next = new Map(prev);
				next.delete(taskId);
				return next;
			});
			setFadingOut((prev) => {
				const next = new Set(prev);
				next.delete(taskId);
				return next;
			});

			// Reverse the archive via API
			await unarchiveTask(taskId);
		},
		[unarchiveTask]
	);

	const handleEditClick = (taskId: number) => {
		const task = activeTasks.find((t) => t.id === taskId);
		if (!task) {
			return;
		}

		openEditTaskModal({
			task,
			title: __('Edit task', 'wpo-aom'),
		});
	};

	const handleDeleteClick = async (taskId: number) => {
		// ToDo: Update to use custom modal
		// eslint-disable-next-line no-alert
		if (!window.confirm(__('Are you sure?', 'wpo-aom'))) {
			return;
		}

		try {
			await deleteTask(taskId);
		} catch (error) {
			console.error('Failed to delete task:', error);
		}
	};

	// Show loading state
	if (loadingStatus === 'loading') {
		return <TaskCardSkeleton count={1} showDescription={true} />;
	}

	// Show error state
	if (loadingStatus === 'error') {
		return (
			<ErrorState
				message={
					loadingError?.message ||
					__('Error loading data. Please try again.', 'wpo-aom')
				}
				onRetry={refreshTasks}
			/>
		);
	}

	const hasVisibleTasks = activeTasks.length > 0 || recentlyArchived.size > 0;

	// Show empty state only after data is loaded
	if (loadingStatus === 'loaded' && !hasVisibleTasks) {
		return (
			<EmptyState
				icon="note"
				message={__('No tasks found.', 'wpo-aom')}
				actionText={__('Add Task', 'wpo-aom')}
			/>
		);
	}

	return (
		<div className="task-list-container active-tasks-container">
			<h4 className="screenReader">{__('Active Tasks', 'wpo-aom')}</h4>
			<ul className="task-list">
				{activeTasks
					.filter((task) => !recentlyArchived.has(task.id))
					.map((task) => (
						<li key={task.id}>
							<TaskCard
								key={task.id}
								task={task}
								onEditClick={handleEditClick}
								onDeleteClick={handleDeleteClick}
								onArchiveClick={handleArchiveClick}
								headingLevel="h5"
								showDescription={true}
								descriptionMaxLength={150}
								showOrder={false}
							/>
						</li>
					))}
				{Array.from(recentlyArchived.entries()).map(
					([taskId, task]) => (
						<li
							key={`archived-${taskId}`}
							className={`task-archived-item${fadingOut.has(taskId) ? ' task-archived-fadeout' : ''}`}
						>
							<TaskCard
								task={task}
								headingLevel="h5"
								showDescription={true}
								descriptionMaxLength={150}
								showOrder={false}
								IncludedActions={[]}
							/>
							<div className="task-archived-overlay">
								<div className="task-archived-overlay-content">
									<span className="task-archived-message">
										{__(
											'You archived this task',
											'wpo-aom'
										)}
									</span>
									<a
										href={archivePageUrl}
										className="task-archived-link"
									>
										{__('Go to archive', 'wpo-aom')}
										{' \u2192'}
									</a>
								</div>
								<button
									type="button"
									className="wpo-button task-archived-undo"
									onClick={() => handleUndoArchive(taskId)}
								>
									{__('Undo', 'wpo-aom')}
								</button>
							</div>
						</li>
					)
				)}
			</ul>
		</div>
	);
};

export default ActiveTasks;
