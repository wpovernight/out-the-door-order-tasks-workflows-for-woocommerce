import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { TodayTasks } from '@orderManager/views/Dashboard/components/TodayTasks';
import { PartialFulfillments } from '@orderManager/views/Dashboard/components/PartialFulfillments';
import { useTasks } from '@shared/context/TaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { SidebarModalProvider } from '@shared/context/SidebarModalContext';
import { TaskCardSkeleton } from '@shared/components/TaskCardSkeleton';
import { ErrorState, SkeletonLine } from '@shared/components/LoadingSkeleton';
import { fetchFulfillmentOrders } from '@shared/utils/api';
import { FulfillmentOrder } from '@shared/types/fulfillment';

export const DashboardView = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();
	const [fulfillmentOrders, setFulfillmentOrders] = useState<
		FulfillmentOrder[]
	>([]);

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTasks(),
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
			fetchFulfillmentOrders('partially-fulfilled').then(
				setFulfillmentOrders
			),
		]);
	}, [loadTasks, loadFieldOptions]);

	// Lazy load - Prefetch form data after initial data is displayed
	useEffect(() => {
		if (loadingStatus === 'loaded') {
			loadTaskFields();
		}
	}, [loadingStatus, loadTaskFields]);

	if (loadingStatus === 'loading') {
		return (
			<div className="dashboard-view">
				<div className="dashboard-widget" id="today-tasks">
					<div className="header">
						<h3>
							{__("Today's tasks", 'wpo-aom')}{' '}
							<span className="wpo-count-badge">0</span>
						</h3>
					</div>
					<div className="content">
						<TaskCardSkeleton count={3} showDescription={true} />
					</div>
					<div className="footer">
						<span className="wpo-button view-all-link">
							{__('View all tasks', 'wpo-aom')}
						</span>
					</div>
				</div>
				<div className="dashboard-widget" id="partial-fulfillments">
					<div className="header">
						<h3>
							{__('Partially shipped fulfillments', 'wpo-aom')}
						</h3>
					</div>
					<div className="content">
						<ul className="fulfillment-order-list">
							{[1, 2, 3].map((i) => (
								<li key={i} className="fulfillment-order">
									<div
										style={{
											display: 'flex',
											gap: '0.3em',
										}}
									>
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
												gap: '0.3em',
												flex: 1,
											}}
										>
											<SkeletonLine
												width="50%"
												height="1em"
											/>
											<SkeletonLine
												width="30%"
												height="0.8em"
											/>
										</div>
									</div>
								</li>
							))}
						</ul>
					</div>
				</div>
			</div>
		);
	}

	if (loadingStatus === 'error') {
		return (
			<div className="dashboard-view">
				<ErrorState
					message={
						loadingError?.message ||
						__('Error loading tasks. Please try again.', 'wpo-aom')
					}
				/>
			</div>
		);
	}

	return (
		<>
			<h2 className="screenReader">{__('Dashboard', 'wpo-aom')}</h2>
			<SidebarModalProvider>
				<div className="dashboard-view">
					<TodayTasks />
					<PartialFulfillments orders={fulfillmentOrders} />
				</div>
			</SidebarModalProvider>
		</>
	);
};
