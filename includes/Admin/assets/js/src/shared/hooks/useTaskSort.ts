import { useMemo, useState } from 'react';
import { Task } from '@shared/types/task';
import {
	getTaskDateField,
	getFieldObjectValue,
	getFieldRawValues,
	getFieldValue,
} from '@shared/utils/fieldUtils';

export type SortColumn =
	| 'title'
	| 'priority'
	| 'status'
	| 'dueDate'
	| 'completedDate'
	| 'archivedDate'
	| 'orderID'
	| 'customerName';
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
					const aValueOption = getFieldValue(
						a,
						'priority',
						'position',
						1
					);
					const bValueOption = getFieldValue(
						b,
						'priority',
						'position',
						1
					);
					aValue =
						typeof aValueOption === 'number' ? aValueOption : 1;
					bValue =
						typeof bValueOption === 'number' ? bValueOption : 1;
					break;
				}

				case 'status': {
					const aValueOption = getFieldValue(
						a,
						'status',
						'position',
						1
					);
					const bValueOption = getFieldValue(
						b,
						'status',
						'position',
						1
					);
					aValue =
						typeof aValueOption === 'number' ? aValueOption : 1;
					bValue =
						typeof bValueOption === 'number' ? bValueOption : 1;
					break;
				}

				case 'dueDate':
				case 'completedDate':
				case 'archivedDate': {
					const fieldMap: Record<string, string> = {
						dueDate: 'due_date',
						completedDate: 'completed_date',
						archivedDate: 'archived_date',
					};

					const mappedField = fieldMap[sortColumn];

					const aDate = getTaskDateField(a, mappedField);
					const bDate = getTaskDateField(b, mappedField);
					// Handle null dates (put them at the end)
					if (!aDate && !bDate) {
						return 0;
					}
					if (!aDate) {
						return 1;
					}
					if (!bDate) {
						return -1;
					}
					aValue = aDate.getTime();
					bValue = bDate.getTime();
					break;
				}

				case 'orderID': {
					const aOrder = getFieldRawValues(a, 'order')?.[0] ?? 0;
					const bOrder = getFieldRawValues(b, 'order')?.[0] ?? 0;
					aValue = typeof aOrder === 'number' ? aOrder : 0;
					bValue = typeof bOrder === 'number' ? bOrder : 0;
					break;
				}

				case 'customerName': {
					const aOrder = getFieldObjectValue(a, 'order');
					const bOrder = getFieldObjectValue(b, 'order');
					aValue = (aOrder?.full_name as string)?.toLowerCase() ?? '';
					bValue = (bOrder?.full_name as string)?.toLowerCase() ?? '';
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
