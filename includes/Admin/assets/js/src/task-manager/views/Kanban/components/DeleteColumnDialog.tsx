import React, { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { FieldOption, Task } from '@shared/types/task';
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

interface ChooseActionPhaseProps {
	columnLabel: string;
	onDeleteEverything: () => void;
	onMoveItems: () => void;
	onCancel: () => void;
}

const ChooseActionPhase: React.FC<ChooseActionPhaseProps> = ({
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

const ProcessingPhase: React.FC = () => (
	<div className="dialog-content dialog-processing">
		<div className="wpo-aom-spinner" aria-hidden="true" />
		<p>{__('Processing…', 'wpo-advanced-order-manager')}</p>
	</div>
);

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
	| 'choose-action'
	| 'move-picker'
	| 'role-reassign'
	| 'processing'
	| 'error';

type ColumnDeleteOperation = 'delete-everything' | 'move-and-delete';
type LastAttempt = ColumnDeleteOperation | null;

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

	// Role attachment is captured at mount. If the reassign step succeeds but
	// the follow-up delete fails, recomputing from live statusRoles on retry
	// would point at the wrong role and corrupt it.
	const [attachedRole] = useState<'done' | 'undone'>(() =>
		statusRoles.done === column.id ? 'done' : 'undone'
	);
	const [needsRoleSelection] = useState(
		() =>
			statusRoles.done === column.id ||
			statusRoles.undone === column.id
	);

	// Empty + role-assigned columns skip choose-action and go straight to
	// role-reassign. Captured at mount so an in-flight reassign can't flip it.
	const [skipChooseAction] = useState(
		() => tasks.length === 0 && needsRoleSelection
	);

	const [phase, setPhase] = useState<Phase>(
		skipChooseAction ? 'role-reassign' : 'choose-action'
	);
	const [moveTargetOptionId, setMoveTargetOptionId] = useState<number | null>(
		null
	);
	const [roleTargetOptionId, setRoleTargetOptionId] = useState<number | null>(
		null
	);
	const [errorMessage, setErrorMessage] = useState<string>('');
	const [lastAttempt, setLastAttempt] = useState<LastAttempt>(null);
	// When the user routes through role-reassign, we need to remember what they
	// originally chose so we can run the right operation after the role updates.
	const [pendingIntent, setPendingIntent] =
		useState<ColumnDeleteOperation | null>(
			skipChooseAction ? 'delete-everything' : null
		);

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

	const runDeleteEverything = async () => {
		setLastAttempt('delete-everything');
		setPhase('processing');
		try {
			await deleteColumnAndTasks(column.field_id, column.id);
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
		setLastAttempt('move-and-delete');
		setPhase('processing');
		try {
			await deleteColumnAndMoveTasks(
				column.field_id,
				column.id,
				moveTargetOptionId
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

	const retryLastAttempt = () => {
		// If we got here via the role-reassign flow, retry the whole sequence.
		// updateStatusRoles is idempotent on a re-run with the same target, so
		// it's safe whether the original failure was at the reassign step or
		// the follow-up delete.
		if (pendingIntent !== null) {
			void handleRoleReassignConfirmation();
			return;
		}
		if (lastAttempt === 'delete-everything') {
			void runDeleteEverything();
		} else if (lastAttempt === 'move-and-delete') {
			void runMoveAndDelete();
		}
	};

	// ----- Phase transition handlers ---------------------------------------

	const handleDeleteEverythingAction = async () => {
		if (needsRoleSelection) {
			setPendingIntent('delete-everything');
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
			setPendingIntent('move-and-delete');
			setPhase('role-reassign');
			return;
		}

		void runMoveAndDelete();
	};

	const handleRoleReassignConfirmation = async () => {
		if (roleTargetOptionId === null || pendingIntent === null) {
			return;
		}

		setPhase('processing');

		try {
			// Reassign the role to the chosen column first — otherwise the column
			// delete that follows will be rejected by the backend's role-block guard.
			await updateStatusRoles({ [attachedRole]: roleTargetOptionId });

			// Now run the operation the user originally chose.
			if (pendingIntent === 'delete-everything') {
				await deleteColumnAndTasks(column.field_id, column.id);
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
					moveTargetOptionId
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

	// ----- Render ----------------------------------------------------------

	const renderPhase = () => {
		switch (phase) {
			case 'choose-action':
				return (
					<ChooseActionPhase
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
				const roleLabel =
					attachedRole === 'done'
						? __('done', 'wpo-advanced-order-manager')
						: __('undone', 'wpo-advanced-order-manager');

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
							skipChooseAction
								? __('Cancel', 'wpo-advanced-order-manager')
								: __('Back', 'wpo-advanced-order-manager')
						}
						onPrimaryAction={handleRoleReassignConfirmation}
						onSecondaryAction={
							skipChooseAction
								? onClose
								: () => setPhase('choose-action')
						}
					/>
				);
			}

			case 'processing':
				return <ProcessingPhase />;

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