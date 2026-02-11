import React from 'react';
import { SkeletonBox, SkeletonLine } from '@shared/components/LoadingSkeleton';

export const TaskFormSkeleton: React.FC = () => {
	return (
		<div className="wpo-aom-task-form-skeleton">
			{/* Status, Priority, Due Date row */}
			<div className="skeleton-field-group">
				<div>
					<SkeletonLine width="60%" height="1em" />
					<SkeletonBox height="2.5em" />
				</div>
				<div>
					<SkeletonLine width="60%" height="1em" />
					<SkeletonBox height="2.5em" />
				</div>
				<div>
					<SkeletonLine width="60%" height="1em" />
					<SkeletonBox height="2.5em" />
				</div>
			</div>

			{/* Title field */}
			<div className="skeleton-field-group">
				<div>
					<SkeletonLine width="40%" height="1em" />
					<SkeletonBox height="2.5em" />
				</div>
			</div>

			{/* Associated Orders field */}
			<div className="skeleton-field-group">
				<div>
					<SkeletonLine width="50%" height="1em" />
					<SkeletonBox height="2.5em" />
				</div>
			</div>

			{/* Description field */}
			<div className="skeleton-field-group">
				<div>
					<SkeletonLine width="40%" height="1em" />
					<SkeletonBox height="6em" />
				</div>
			</div>

			{/* Action buttons */}
			<div className="skeleton-action-group">
				<SkeletonBox width="100%" height="2.5em" />
				<SkeletonBox width="100%" height="2.5em" />
			</div>
		</div>
	);
};
