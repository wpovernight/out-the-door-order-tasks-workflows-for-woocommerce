import React from 'react';
import { LoadingSkeleton, SkeletonBox } from '@shared/components/LoadingSkeleton';

interface BoardSkeletonProps {
	count?: number;
	className?: string;
}

export const BoardSkeleton: React.FC<BoardSkeletonProps> = ({
	count = 3,
	className = '',
}) => {
	return (
		<LoadingSkeleton count={count} className={className}>
			<SkeletonBox
				height="calc(100vh - 14em)"
				width="23em"
				className="kanban-board-skeleton"
			>
                {/* Empty - just the column structure */}
			</SkeletonBox>
		</LoadingSkeleton>
	);
};
