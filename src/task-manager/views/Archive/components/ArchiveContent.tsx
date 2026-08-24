import React, { useEffect, useState } from 'react';
import { useViewTasks } from '@taskManager/views/Archive/context/ViewTaskContext';
import { __, sprintf } from '@wordpress/i18n';
import { useTaskSort } from '@sdk/hooks/useTaskSort';
import { SortIcon } from '@sdk/components/SortIcon';
import TaskRow from '@taskManager/views/Archive/components/TaskRow';

const ITEMS_PER_PAGE = 10; // ToDo: Make this user-configurable in settings.

export const ArchiveContent: React.FC = () => {
	const { archivedTasks } = useViewTasks();
	const { sortColumn, sortDirection, handleSort, sortedTasks } = useTaskSort(
		archivedTasks,
		'archivedDate',
		'desc'
	);

	const [currentPage, setCurrentPage] = useState(1);

	useEffect(() => {
		setCurrentPage(1);
	}, [sortColumn, sortDirection, archivedTasks.length]);

	const totalPages = Math.max(
		1,
		Math.ceil(sortedTasks.length / ITEMS_PER_PAGE)
	);
	const paginatedTasks = sortedTasks.slice(
		(currentPage - 1) * ITEMS_PER_PAGE,
		currentPage * ITEMS_PER_PAGE
	);

	return (
		<div className="archive-view-container">
			{archivedTasks.length === 0 && (
				<div className="no-tasks">
					<p>
						{__(
							'No archived tasks found.',
							'advanced-order-manager'
						)}
					</p>
				</div>
			)}

			{archivedTasks.length > 0 && (
				<>
					<div className="archived-task-table-wrapper">
						<table>
							<thead>
								<tr>
									<th
										className="task-info th-sortable"
										onClick={() => handleSort('title')}
									>
										{__('Task', 'advanced-order-manager')}{' '}
										<SortIcon
											column="title"
											sortColumn={sortColumn}
											sortDirection={sortDirection}
										/>
									</th>
									<th
										className="task-order-id th-sortable"
										onClick={() => handleSort('orderID')}
									>
										{__(
											'Order ID',
											'advanced-order-manager'
										)}{' '}
										<SortIcon
											column="orderID"
											sortColumn={sortColumn}
											sortDirection={sortDirection}
										/>
									</th>
									<th
										className="task-customer th-sortable"
										onClick={() =>
											handleSort('customerName')
										}
									>
										{__(
											'Customer',
											'advanced-order-manager'
										)}{' '}
										<SortIcon
											column="customerName"
											sortColumn={sortColumn}
											sortDirection={sortDirection}
										/>
									</th>
									<th
										className="task-done-date th-sortable"
										onClick={() => handleSort('doneDate')}
									>
										{__('Done', 'advanced-order-manager')}{' '}
										<SortIcon
											column="doneDate"
											sortColumn={sortColumn}
											sortDirection={sortDirection}
										/>
									</th>
									<th
										className="task-archived-date th-sortable"
										onClick={() =>
											handleSort('archivedDate')
										}
									>
										{__(
											'Archived Date',
											'advanced-order-manager'
										)}{' '}
										<SortIcon
											column="archivedDate"
											sortColumn={sortColumn}
											sortDirection={sortDirection}
										/>
									</th>
									<th className="task-actions">
										{__(
											'Actions',
											'advanced-order-manager'
										)}
									</th>
								</tr>
							</thead>
							<tbody>
								{paginatedTasks.map((task) => (
									<TaskRow key={task.id} task={task} />
								))}
							</tbody>
						</table>
					</div>

					<div className="archive-pagination">
						<button
							className="wpo-button"
							onClick={() => setCurrentPage((p) => p - 1)}
							disabled={currentPage === 1}
						>
							{__('Previous', 'advanced-order-manager')}
						</button>
						<span>
							{sprintf(
								/* translators: 1: current page, 2: total pages */
								__(
									'Page %1$d of %2$d',
									'advanced-order-manager'
								),
								currentPage,
								totalPages
							)}
						</span>
						<button
							className="wpo-button"
							onClick={() => setCurrentPage((page) => page + 1)}
							disabled={currentPage === totalPages}
						>
							{__('Next', 'advanced-order-manager')}
						</button>
					</div>
				</>
			)}
		</div>
	);
};
