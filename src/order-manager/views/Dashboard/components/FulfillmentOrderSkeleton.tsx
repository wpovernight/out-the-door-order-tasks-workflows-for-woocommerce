import React from 'react';
import { SkeletonLine } from '@sdk';

interface FulfillmentOrderSkeletonProps {
	count?: number;
}

export const FulfillmentOrderSkeleton = ({
	count = 5,
}: FulfillmentOrderSkeletonProps) => (
	<ul className="fulfillment-order-list">
		{Array.from({ length: count }).map((_, index) => (
			<li key={index} className="fulfillment-order">
				<div style={{ display: 'flex', gap: '0.3em' }}>
					<SkeletonLine
						width="0.9em"
						height="0.9em"
						style={{
							borderRadius: '50%',
							flexShrink: 0,
						}}
					/>
					<div
						style={{
							display: 'flex',
							flexDirection: 'column',
							gap: '1.2em',
							flex: 1,
						}}
					>
						<SkeletonLine width="50%" height="1em" />
						<SkeletonLine width="30%" height="0.8em" />
					</div>
				</div>
			</li>
		))}
	</ul>
);
