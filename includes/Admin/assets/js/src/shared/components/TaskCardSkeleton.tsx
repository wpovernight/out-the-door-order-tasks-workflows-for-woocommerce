import React from 'react';
import { LoadingSkeleton, SkeletonBox, SkeletonLine } from './LoadingSkeleton';

interface TaskCardSkeletonProps {
	count?: number;
	showDescription?: boolean;
	className?: string;
}

export const TaskCardSkeleton: React.FC<TaskCardSkeletonProps> = ({
	count = 1,
	showDescription = true,
	className = '',
}) => {
	return (
		<LoadingSkeleton count={count} className={className}>
			<SkeletonBox height="auto" className="task-card-skeleton">
				<div
					style={{
						display: 'flex',
						justifyContent: 'space-between',
						marginBottom: '1em',
					}}
				>
					{/* Task title */}
					<SkeletonLine width="65%" height="1.1em" />

					{/* Action buttons */}
					<div style={{ display: 'flex', gap: '0.5em' }}>
						<SkeletonLine width="1.3em" height="1.3em" />
						<SkeletonLine width="1.3em" height="1.3em" />
						<SkeletonLine width="1.3em" height="1.3em" />
					</div>
				</div>

				{/* Task description (optional) */}
				{showDescription && <SkeletonLine width="100%" height="1em" />}

				<div
					style={{
						display: 'flex',
						justifyContent: 'space-between',
						marginTop: '1em',
					}}
				>
					{/* Task tags */}
					<div style={{ display: 'flex', gap: '0.5em' }}>
						<SkeletonLine width="5em" height="1.5em" />
						<SkeletonLine width="4em" height="1.5em" />
					</div>

					{/* Due date */}
					<SkeletonLine width="7em" height="1em" />
				</div>
			</SkeletonBox>
		</LoadingSkeleton>
	);
};
