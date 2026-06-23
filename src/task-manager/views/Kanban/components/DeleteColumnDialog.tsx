import React, { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { FieldOption, StatusRoles, Task } from '@shared/types/task';
import { useColumnDeletion } from '@taskManager/hooks/useColumnDeletion';
import { useConfirm } from '@shared/context/DialogContext';
import { useStatusRoles } from '@shared/context/StatusRoleContext';
import { useTasks } from '@shared/context/TaskContext';

// ============================================================================
// Helpers
// ============================================================================

const extractErrorMessage = (error: unknown, fallback: string): string => {
	if (error instanceof Error && error.message) {
		return error.message;
	}
	return fallback;
};

// ============================================================================
// Shared sub-components
// ============================================================================

interface ColumnPickerPhaseProps {
	title: string;
	description: string;
	fieldLabel: string;
	availableColumns: FieldOption[];
	selectedOptionId: number | null;
	onSelectionChange: (id: number | null) => void;
	primaryActionLabel: string;
	secondaryActionLabel: string;
	onPrimaryAction: () => void;
	onSecondaryAction: () => void;
}

/**
 * Shared shape for "pick a column from a dropdown, then continue or back out"
 * — used by both the move-picker and the role-reassign phases.
 */
const ColumnPickerPhase: React.FC<ColumnPickerPhaseProps> = ({
	title,
	description,
	fieldLabel,
	availableColumns,
	selectedOptionId,
	onSelectionChange,
	primaryActionLabel,
	secondaryActionLabel,
	onPrimaryAction,
	onSecondaryAction,
}) => (
	<>
		<div className="dialog-content">
			<h2>{title}</h2>
			<p>{description}</p>
			<label className="dialog-field">
				{fieldLabel}
				<select
					className="wpo-aom-select"
                    id="column-picker"
					value={selectedOptionId ?? ''}
					onChange={(e) =>
						onSelectionChange(
							e.target.value ? Number(e.target.value) : null
						)
					}
				>
					<option value="">
						{__('Select', 'wpo-advanced-order-manager')}
					</option>
					{availableColumns.map((column) => (
						<option key={column.id} value={column.id}>
							{column.label}
						</option>
					))}
				</select>
			</label>
		</div>
		<ul className="dialog-actions dialog-actions-horizontal">
			<li>
				<button
					type="button"
					className="wpo-button"
					onClick={onSecondaryAction}
				>
					{secondaryActionLabel}
				</button>
			</li>
			<li>
				<button
					type="button"
					className="wpo-button wpo-button-primary action-delete"
					onClick={onPrimaryAction}
					disabled={selectedOptionId === null}
				>
					{primaryActionLabel}
				</button>
			</li>
		</ul>
	</>
);

// ============================================================================
// Phase components
// ============================================================================

interface ChooseOperationPhaseProps {
	columnLabel: string;
	onDeleteEverything: () => void;
	onMoveItems: () => void;
	onCancel: () => void;
}

const ChooseOperationPhase: React.FC<ChooseOperationPhaseProps> = ({
	columnLabel,
	onDeleteEverything,
	onMoveItems,
	onCancel,
}) => (
	<>
		<div className="dialog-content">
			<h2>
				{sprintf(
					/* translators: %s: column label */
					__('Delete "%s" column?', 'wpo-advanced-order-manager'),
					columnLabel
				)}
			</h2>
			<p>
				{__(
					'What would you like to do with the items currently inside it?',
					'wpo-advanced-order-manager'
				)}
			</p>
		</div>
		<ul className="dialog-actions dialog-actions-stacked">
			<li>
				<button
					type="button"
					className="wpo-button wpo-button-primary action-delete"
					onClick={onDeleteEverything}
				>
					{__('Delete everything', 'wpo-advanced-order-manager')}
				</button>
			</li>
			<li>
				<button
					type="button"
					className="wpo-button action-move"
					onClick={onMoveItems}
				>
					{__('Move items', 'wpo-advanced-order-manager')}
				</button>
			</li>
			<li>
				<button
					type="button"
					className="wpo-button"
					onClick={onCancel}
				>
					{__('Cancel', 'wpo-advanced-order-manager')}
				</button>
			</li>
		</ul>
	</>
);

interface ProcessingPhaseProps {
	completed: number | null;
	total: number;
	canceling: boolean;
	onCancel: () => void;
}

const ProcessingPhase: React.FC<ProcessingPhaseProps> = ({
	completed,
	total,
	canceling,
	onCancel,
}) => {
	let percent: number;
	if (completed === null) {
		percent = 0;
	} else if (total === 0) {
		percent = 100;
	} else {
		percent = Math.round((completed / total) * 100);
	}

	return (
        <>
        <div className="dialog-content dialog-processing">
            <h2>
                {__(
                    'We are currently moving your items',
                    'wpo-advanced-order-manager'
                )}
            </h2>
            <p>
                {__(
                    'This might take a while, depending on the number of items that need to be moved.',
                    'wpo-advanced-order-manager'
                )}
            </p>
            <label id="delete-progress">
                <progress id="delete-progress" max="100" value={percent}>{percent}%</progress>

                <span className="screen-reader-text">
                    {__( 'Progress', 'wpo-advanced-order-manager' )}
                </span>
                {percent}%
            </label>
        </div>
        <ul className="dialog-actions dialog-actions-horizontal">
            <li>
                <button
                    type="button"
                    className="wpo-button"
                    onClick={onCancel}
                    disabled={canceling}
                >
                    {canceling
                        ? __('Canceling…', 'wpo-advanced-order-manager')
                        : __('Cancel', 'wpo-advanced-order-manager')}
                </button>
            </li>
        </ul>
    </>
    );
};

interface ErrorPhaseProps {
	message: string;
	onRetry: () => void;
	onClose: () => void;
}

const ErrorPhase: React.FC<ErrorPhaseProps> = ({
	message,
	onRetry,
	onClose,
}) => (
	<>
		<div className="dialog-content">
			<h2>{__('Something went wrong', 'wpo-advanced-order-manager')}</h2>
			<p>{message}</p>
		</div>
		<ul className="dialog-actions dialog-actions-horizontal">
			<li>
				<button
					type="button"
					className="wpo-button"
					onClick={onClose}
				>
					{__('Close', 'wpo-advanced-order-manager')}
				</button>
			</li>
			<li>
				<button
					type="button"
					className="wpo-button wpo-button-primary"
					onClick={onRetry}
				>
					{__('Retry', 'wpo-advanced-order-manager')}
				</button>
			</li>
		</ul>
	</>
);

// ============================================================================
// Main component
// ============================================================================

type Phase =
	| 'choose-operation'
	| 'move-picker'
	| 'role-reassign'
	| 'processing'
	| 'error';

type ColumnDeleteOperation = 'delete-everything' | 'move-and-delete';
type LastAttemptOperation = ColumnDeleteOperation | null;

interface DeleteColumnDialogProps {
	column: FieldOption;
	tasks: Task[];
	onClose: () => void;
}

export const DeleteColumnDialog: React.FC<DeleteColumnDialogProps> = ({
	column,
	tasks,
	onClose,
}) => {
	const dialogRef = useRef<HTMLDialogElement>(null);
	const { fieldOptions } = useTasks();
	const { statusRoles, updateStatusRoles } = useStatusRoles();
	const { deleteColumnAndTasks, deleteColumnAndMoveTasks } =
		useColumnDeletion();
	const confirm = useConfirm();

	const [attachedRole] = useState<keyof StatusRoles | null>(() => {
		const match = (
			Object.entries(statusRoles) as [
				keyof StatusRoles,
				number | null,
			][]
		).find(([, optionId]) => optionId === column.id);
		return match ? match[0] : null;
	});
	const needsRoleSelection = attachedRole !== null;

	// Empty + role-assigned columns skip choose-operation and go straight to
	// role-reassign. Captured at mount so an in-flight reassign can't flip it.
	const [directRoleAssignment] = useState(
		() => tasks.length === 0 && needsRoleSelection
	);

    // Drives which screen the dialog renders. The user steps through phases
    // and each setPhase swaps the body via renderPhase().
	const [phase, setPhase] = useState<Phase>(
		directRoleAssignment ? 'role-reassign' : 'choose-operation'
	);

    // Tracks which column the user has selected to move tasks into, if any.
    // Only relevant if they choose move-and-delete.
	const [moveTargetOptionId, setMoveTargetOptionId] = useState<number | null>(
		null
	);
    // Tracks which column the user has selected to reassign the role to, if any.
    // Only relevant if they need to reassign a role.
	const [roleTargetOptionId, setRoleTargetOptionId] = useState<number | null>(
		null
	);

    // Tracks the current operation in case of an error, so we know what to retry.
	const [lastOperationAttempt, setLastOperationAttempt] = useState<LastAttemptOperation>(null);

    // When the user routes through role-reassign, we need to remember what they
    // originally chose so we can run the right operation after the role updates.
    const [pendingOperation, setPendingOperation] =
        useState<ColumnDeleteOperation | null>(
            directRoleAssignment ? 'delete-everything' : null
        );

    // Track progress deletion process.
	const [progress, setProgress] = useState<{
		completed: number | null;
		total: number;
	}>({ completed: null, total: 0 });
	const cancelProcessRef = useRef(false);
	const [processCanceling, setProcessCanceling] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string>('');


	// ----- Derived ---------------------------------------------------------

	const allStatusOptions = fieldOptions.status ?? [];
	const availableColumnsToMove = allStatusOptions.filter(
		(option) => option.id !== column.id
	);
	// Role-reassign targets can't be the column being deleted, and can't be a
	// column already assigned to another role (a column can't play two roles).
	const availableColumnsForRoleReassign = allStatusOptions.filter(
		(option) =>
			option.id !== column.id &&
			option.id !== statusRoles.done &&
			option.id !== statusRoles.undone
	);

	// ----- Dialog open / backdrop handling ---------------------------------

	useEffect(() => {
		const dialog = dialogRef.current;
		if (!dialog) {
			return;
		}
		dialog.showModal();
		return () => {
			if (dialog.open) {
				dialog.close();
			}
		};
	}, []);

	const handleBackdropClick = (
		event: React.MouseEvent<HTMLDialogElement>
	) => {
		const dialog = dialogRef.current;
		if (!dialog || event.target !== dialog) {
			return;
		}

		const rect = dialog.getBoundingClientRect();
		const clickedOutside =
			event.clientX < rect.left ||
			event.clientX > rect.right ||
			event.clientY < rect.top ||
			event.clientY > rect.bottom;
		if (clickedOutside) {
			onClose();
		}
	};

	// ----- Operation runners -----------------------------------------------

	// Resets the per-run processing state. Call right before entering 'processing'.
	const beginProcessing = (attempt: LastAttemptOperation) => {
		setLastOperationAttempt(attempt);
		setProgress({ completed: null, total: 0 });
		cancelProcessRef.current = false;
		setProcessCanceling(false);
		setPhase('processing');
	};

    // Signals the in-flight drain loop to stop before its next task. The running
    // operation then unwinds to its 'canceled' return and closes the dialog.
    const handleCancelProcessing = () => {
		cancelProcessRef.current = true;
		setProcessCanceling(true);
	};

	const runDeleteEverything = async () => {
		beginProcessing('delete-everything');

		try {
			await deleteColumnAndTasks(
				column.field_id,
				column.id,
				(completed, total) => setProgress({ completed, total }),
				() => cancelProcessRef.current
			);
			onClose();
		} catch (error) {
			console.error('Failed to delete column and tasks:', error);
			setErrorMessage(
				extractErrorMessage(
					error,
					__(
						'Failed to delete the column.',
						'wpo-advanced-order-manager'
					)
				)
			);
			setPhase('error');
		}
	};

	const runMoveAndDelete = async () => {
		if (moveTargetOptionId === null) {
			return;
		}

		beginProcessing('move-and-delete');

		try {
			await deleteColumnAndMoveTasks(
				column.field_id,
				column.id,
				moveTargetOptionId,
				(completed, total) => setProgress({ completed, total }),
				() => cancelProcessRef.current
			);
			onClose();
		} catch (error) {
			console.error('Failed to move tasks and delete column:', error);
			setErrorMessage(
				extractErrorMessage(
					error,
					__(
						'Failed to move items and delete the column.',
						'wpo-advanced-order-manager'
					)
				)
			);
			setPhase('error');
		}
	};

	// ----- Phase transition handlers ---------------------------------------

	const handleDeleteEverythingAction = async () => {
		if (needsRoleSelection) {
			setPendingOperation('delete-everything');
			setPhase('role-reassign');
			return;
		}

		const confirmed = await confirm({
			title: __('Delete everything?', 'wpo-advanced-order-manager'),
			message: __(
				'Are you sure you want to delete this column and all the items inside it? This action cannot be undone.',
				'wpo-advanced-order-manager'
			),
			confirmText: __('Delete', 'wpo-advanced-order-manager'),
			cancelText: __('Cancel', 'wpo-advanced-order-manager'),
			action: 'delete',
		});

		if (!confirmed) {
			return;
		}

		await runDeleteEverything();
	};

	const handleMovePickerConfirmation = () => {
		if (moveTargetOptionId === null) {
			return;
		}

		// If the column is role-assigned, route through role-reassign before
		// executing the move + delete.
		if (needsRoleSelection) {
			setPendingOperation('move-and-delete');
			setPhase('role-reassign');
			return;
		}

		void runMoveAndDelete();
	};

	const handleRoleReassignConfirmation = async () => {
		if (
			roleTargetOptionId === null ||
			pendingOperation === null ||
			attachedRole === null
		) {
			return;
		}

		// Capture the narrowed (non-null) role before the awaits below — TS drops
		// the guard's narrowing across `await`, so a local const keeps it usable
		// as the computed key for updateStatusRoles.
		const role = attachedRole;

		beginProcessing(
			pendingOperation === 'delete-everything'
				? 'delete-everything'
				: 'move-and-delete'
		);

		try {
			// Reassign the role to the chosen column first — otherwise the column
			// delete that follows will be rejected by the backend's role-block guard.
			await updateStatusRoles({ [role]: roleTargetOptionId });

			const reportProgress = (completed: number, total: number) =>
				setProgress({ completed, total });
			const isCanceled = () => cancelProcessRef.current;

			// Now run the operation the user originally chose.
			if (pendingOperation === 'delete-everything') {
				await deleteColumnAndTasks(
					column.field_id,
					column.id,
					reportProgress,
					isCanceled
				);
			} else {
				if (moveTargetOptionId === null) {
					throw new Error(
						__(
							'No destination column was selected.',
							'wpo-advanced-order-manager'
						)
					);
				}
				await deleteColumnAndMoveTasks(
					column.field_id,
					column.id,
					moveTargetOptionId,
					reportProgress,
					isCanceled
				);
			}

			onClose();
		} catch (error) {
			console.error(
				'Failed to reassign role and delete column:',
				error
			);
			setErrorMessage(
				extractErrorMessage(
					error,
					__(
						'Failed to reassign the role and delete the column.',
						'wpo-advanced-order-manager'
					)
				)
			);
			setPhase('error');
		}
	};

    const retryLastAttempt = () => {
        // If we got here via the role-reassign flow, retry the whole sequence.
        // updateStatusRoles is idempotent on a re-run with the same target, so
        // it's safe whether the original failure was at the reassign step or
        // the follow-up delete.
        if (pendingOperation !== null) {
            void handleRoleReassignConfirmation();
            return;
        }
        if (lastOperationAttempt === 'delete-everything') {
            void runDeleteEverything();
        } else if (lastOperationAttempt === 'move-and-delete') {
            void runMoveAndDelete();
        }
    };

	// ----- Render ----------------------------------------------------------

	const renderPhase = () => {
		switch (phase) {
			case 'choose-operation':
				return (
					<ChooseOperationPhase
						columnLabel={column.label}
						onDeleteEverything={handleDeleteEverythingAction}
						onMoveItems={() => setPhase('move-picker')}
						onCancel={onClose}
					/>
				);

			case 'move-picker':
				return (
					<ColumnPickerPhase
						title={sprintf(
							/* translators: %s: column label */
							__(
								'Move items from "%s"?',
								'wpo-advanced-order-manager'
							),
							column.label
						)}
						description={sprintf(
							/* translators: %s: column label */
							__(
								'Where would you like to move the items currently in this column? Once the items are reassigned, the "%s" column will be permanently deleted.',
								'wpo-advanced-order-manager'
							),
							column.label
						)}
						fieldLabel={__(
							'Destination',
							'wpo-advanced-order-manager'
						)}
						availableColumns={availableColumnsToMove}
						selectedOptionId={moveTargetOptionId}
						onSelectionChange={setMoveTargetOptionId}
						primaryActionLabel={
							needsRoleSelection
								? __('Continue', 'wpo-advanced-order-manager')
								: __(
										'Move & delete',
										'wpo-advanced-order-manager'
									)
						}
						secondaryActionLabel={__(
							'Cancel',
							'wpo-advanced-order-manager'
						)}
						onPrimaryAction={handleMovePickerConfirmation}
						onSecondaryAction={onClose}
					/>
				);

			case 'role-reassign': {
				// One translatable label per role. Adding a role to StatusRoles
				// means adding one line here — the only role-aware spot left.
				const roleLabels: Record<keyof StatusRoles, string> = {
					done: __('done', 'wpo-advanced-order-manager'),
					undone: __('undone', 'wpo-advanced-order-manager'),
				};
				const roleLabel = attachedRole ? roleLabels[attachedRole] : attachedRole;

				return (
					<ColumnPickerPhase
						title={__(
							'Reassign role?',
							'wpo-advanced-order-manager'
						)}
						description={sprintf(
							/* translators: 1: column label being deleted, 2: role name ("done" or "undone") */
							__(
								'You are deleting your "%1$s" column. Which column should we use to mark tasks as "%2$s" from now on?',
								'wpo-advanced-order-manager'
							),
							column.label,
							roleLabel
						)}
						fieldLabel={__(
							'Column',
							'wpo-advanced-order-manager'
						)}
						availableColumns={availableColumnsForRoleReassign}
						selectedOptionId={roleTargetOptionId}
						onSelectionChange={setRoleTargetOptionId}
						primaryActionLabel={__(
							'Save & delete',
							'wpo-advanced-order-manager'
						)}
						secondaryActionLabel={
							directRoleAssignment
								? __('Cancel', 'wpo-advanced-order-manager')
								: __('Back', 'wpo-advanced-order-manager')
						}
						onPrimaryAction={handleRoleReassignConfirmation}
						onSecondaryAction={
							directRoleAssignment
								? onClose
								: () => setPhase('choose-operation')
						}
					/>
				);
			}

			case 'processing':
				return (
					<ProcessingPhase
						completed={progress.completed}
						total={progress.total}
						canceling={processCanceling}
						onCancel={handleCancelProcessing}
					/>
				);

			case 'error':
				return (
					<ErrorPhase
						message={errorMessage}
						onRetry={retryLastAttempt}
						onClose={onClose}
					/>
				);
		}
	};

	return (
		<dialog
			ref={dialogRef}
			className={`wpo-aom-dialog column-deletion-dialog ${phase === 'processing' ? 'processing-action' : 'action-delete'}`}
			onClose={onClose}
			onClick={handleBackdropClick}
		>
			{renderPhase()}
		</dialog>
	);
};