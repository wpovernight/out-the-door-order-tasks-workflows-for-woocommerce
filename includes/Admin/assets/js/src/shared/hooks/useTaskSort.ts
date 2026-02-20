import { useMemo, useState } from 'react';
import { isFieldOption, Task } from '@shared/types/task';
import {
	getCompletedDate,
	getFieldValue,
	getTaskDueDate,
} from '@shared/utils/fieldUtils';

export type SortColumn =
	| 'title'
	| 'priority'
	| 'status'
	| 'dueDate'
	| 'completedDate';
export type SortDirection = 'asc' | 'desc';

interface UseTaskSortResult {
	sortColumn: SortColumn;
	sortDirection: SortDirection;
	handleSort: (column: SortColumn) => void;
	sortedTasks: Task[];
}

export const useTaskSort = (
	tasks: Task[],
	defaultSortColumn: SortColumn = 'dueDate',
	defaultSortDirection: SortDirection = 'asc'
): UseTaskSortResult => {
	const [sortColumn, setSortColumn] = useState<SortColumn>(defaultSortColumn);
	const [sortDirection, setSortDirection] =
		useState<SortDirection>(defaultSortDirection);

	const handleSort = (column: SortColumn) => {
		if (sortColumn === column) {
			setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
		} else {
			setSortColumn(column);
			setSortDirection('asc');
		}
	};

	const sortedTasks = useMemo(() => {
		return [...tasks].sort((a, b) => {
			let aValue: any;
			let bValue: any;

			switch (sortColumn) {
				case 'title':
					aValue = a.title.toLowerCase();
					bValue = b.title.toLowerCase();
					break;

				case 'priority': {
					const aPriority = getFieldValue(a, 'priority');
					const bPriority = getFieldValue(b, 'priority');
					aValue = isFieldOption(aPriority) ? aPriority.position : 1;
					bValue = isFieldOption(bPriority) ? bPriority.position : 1;
					break;
				}

				case 'status': {
					const aStatus = getFieldValue(a, 'status');
					const bStatus = getFieldValue(b, 'status');
					aValue = isFieldOption(aStatus) ? aStatus.position : 1;
					bValue = isFieldOption(bStatus) ? bStatus.position : 1;
					break;
				}

				case 'dueDate': {
					const aDueDate = getTaskDueDate(a);
					const bDueDate = getTaskDueDate(b);
					// Handle null dates (put them at the end)
					if (!aDueDate && !bDueDate) {
						return 0;
					}
					if (!aDueDate) {
						return 1;
					}
					if (!bDueDate) {
						return -1;
					}
					aValue = aDueDate.getTime();
					bValue = bDueDate.getTime();
					break;
				}

				case 'completedDate': {
					const aCompletedDate = getCompletedDate(a);
					const bCompletedDate = getCompletedDate(b);
					// Handle null dates (put them at the end)
					if (!aCompletedDate && !bCompletedDate) {
						return 0;
					}
					if (!aCompletedDate) {
						return 1;
					}
					if (!bCompletedDate) {
						return -1;
					}
					aValue = aCompletedDate.getTime();
					bValue = bCompletedDate.getTime();
					break;
				}

				default:
					return 0;
			}

			if (aValue < bValue) {
				return sortDirection === 'asc' ? -1 : 1;
			}
			if (aValue > bValue) {
				return sortDirection === 'asc' ? 1 : -1;
			}
			return 0;
		});
	}, [tasks, sortColumn, sortDirection]);

	return { sortColumn, sortDirection, handleSort, sortedTasks };
};
