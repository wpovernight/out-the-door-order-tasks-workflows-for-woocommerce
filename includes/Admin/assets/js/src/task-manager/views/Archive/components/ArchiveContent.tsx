import React, { useEffect, useState } from 'react';
import { useViewTasks } from '@taskManager/views/Archive/context/ViewTaskContext';
import { __ } from '@wordpress/i18n';
import { useTaskSort } from '@shared/hooks/useTaskSort';
import SortIcon from '@shared/components/SortIcon';
import TaskRow from '@taskManager/views/Archive/components/TaskRow';

const ITEMS_PER_PAGE = 3; // ToDo: Make this user-configurable in settings.

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
					<p>{__('No archived tasks found.', 'wpo-aom')}</p>
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
										{__('Task', 'wpo-aom')}{' '}
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
										{__('Order ID', 'wpo-aom')}{' '}
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
										{__('Customer', 'wpo-aom')}{' '}
										<SortIcon
											column="customerName"
											sortColumn={sortColumn}
											sortDirection={sortDirection}
										/>
									</th>
									<th
										className="task-completed-date th-sortable"
										onClick={() =>
											handleSort('completedDate')
										}
									>
										{__('Completed Date', 'wpo-aom')}{' '}
										<SortIcon
											column="completedDate"
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
										{__('Archived Date', 'wpo-aom')}{' '}
										<SortIcon
											column="archivedDate"
											sortColumn={sortColumn}
											sortDirection={sortDirection}
										/>
									</th>
									<th className="task-actions">
										{__('Actions', 'wpo-aom')}
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
							{__('Previous', 'wpo-aom')}
						</button>
						<span>
							{__(
								/* translators: 1: current page, 2: total pages */
								`Page ${currentPage} of ${totalPages}`,
								'wpo-aom'
							)}
						</span>
						<button
							className="wpo-button"
							onClick={() => setCurrentPage((page) => page + 1)}
							disabled={currentPage === totalPages}
						>
							{__('Next', 'wpo-aom')}
						</button>
					</div>
				</>
			)}
		</div>
	);
};
