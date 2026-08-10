import React from 'react';
import {
	LoadingSkeleton,
	SkeletonBox,
	SkeletonLine,
} from '@sdk/components/LoadingSkeleton';

interface CalendarSkeletonProps {
	className?: string;
}

export const CalendarSkeleton: React.FC<CalendarSkeletonProps> = ({
	className = '',
}) => {
	return (
		<LoadingSkeleton count={1} className={className}>
			{/* Date picker */}
			<SkeletonBox
				width="25em"
				height="37.5em"
				className="calendar-sidebar"
			>
				{/* Empty - just the sidebar structure */}
			</SkeletonBox>

			{/* Task list */}
			<div className="task-list-container">
				{/* Title */}
				<SkeletonLine width="12em" height="2em" />
				<SkeletonBox
					className="calendar-task-list"
					height="5em"
				></SkeletonBox>
				<SkeletonBox
					className="calendar-task-list"
					height="5em"
				></SkeletonBox>
			</div>
		</LoadingSkeleton>
	);
};
