import React from 'react';
import { SortColumn, SortDirection } from '@shared/hooks/useTaskSort';

interface SortIconProps {
	column: SortColumn;
	sortColumn: SortColumn;
	sortDirection: SortDirection;
}

const SortIcon: React.FC<SortIconProps> = ({
	column,
	sortColumn,
	sortDirection,
}) => {
	if (sortColumn !== column) {
		return <span className="sort-icon">↕</span>;
	}
	return (
		<span className="sort-icon sort-icon-active">
			{sortDirection === 'asc' ? '↑' : '↓'}
		</span>
	);
};

export default SortIcon;
