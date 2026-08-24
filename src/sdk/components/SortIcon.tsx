import React from 'react';
import { SortColumn, SortDirection } from '@sdk/hooks/useTaskSort';

interface SortIconProps {
	column: SortColumn;
	sortColumn: SortColumn;
	sortDirection: SortDirection;
}

export const SortIcon: React.FC<SortIconProps> = ({
	column,
	sortColumn,
	sortDirection,
}) => {
	if (sortColumn !== column) {
		return <span className="sort-icon unsorted" />;
	}
	return (
		<span
			className={`sort-icon sorted ${sortDirection === 'asc' ? 'asc' : 'desc'}`}
		/>
	);
};
