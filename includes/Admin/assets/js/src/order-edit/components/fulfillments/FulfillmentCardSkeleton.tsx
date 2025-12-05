import React from 'react';
import {
	LoadingSkeleton,
	SkeletonBox,
	SkeletonLine,
} from '@shared/components/LoadingSkeleton';

interface FulfillmentCardSkeletonProps {
	count?: number;
	className?: string;
}

export const FulfillmentCardSkeleton: React.FC<
	FulfillmentCardSkeletonProps
> = ({ count = 2, className = '' }) => {
	return (
		<LoadingSkeleton count={count} className={className}>
			<SkeletonBox
				height="auto"
				width="100%"
				className="fulfillment-card-skeleton"
			>
				{/* Header */}
				<div
					style={{
						marginBottom: '1em',
					}}
				>
					<SkeletonLine width="50%" height="1.2em" />
				</div>

				{/* Items row */}
				<div style={{ marginBottom: '0.8em' }}>
					<SkeletonLine width="70%" height="1em" />
				</div>

				{/* Provider and tracking row */}
				<div style={{ marginBottom: '0.8em' }}>
					<SkeletonLine width="85%" height="1em" />
				</div>

				{/* Footer with date */}
				<div
					style={{
						display: 'flex',
						justifyContent: 'space-between',
						marginTop: '1em',
					}}
				>
					<SkeletonLine width="40%" height="0.9em" />
				</div>
			</SkeletonBox>
		</LoadingSkeleton>
	);
};
