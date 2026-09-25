import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TodayTasks } from '@orderManager/views/Dashboard/components/TodayTasks';
import { PartialFulfillments } from '@orderManager/views/Dashboard/components/PartialFulfillments';
import { FulfillmentOrderSkeleton } from '@orderManager/views/Dashboard/components/FulfillmentOrderSkeleton';
import { useFulfillmentOrders } from '@orderManager/views/Dashboard/hooks/useFulfillmentOrders';
import {
	useTasks,
	useAsyncLoader,
	SidebarModalProvider,
	TaskCardSkeleton,
	ErrorState,
} from '@sdk';

export const DashboardView = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();
	const {
		orders: fulfillmentOrders,
		meta: fulfillmentMeta,
		isLoading: fulfillmentLoading,
		error: fulfillmentError,
		goToPage: goToFulfillmentPage,
	} = useFulfillmentOrders();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTasks(),
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
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
							{__("Today's tasks", 'out-the-door-order-tasks-workflows-for-woocommerce')}{' '}
							<span className="wpo-count-badge">0</span>
						</h3>
					</div>
					<div className="content">
						<TaskCardSkeleton count={3} showDescription={true} />
					</div>
					<div className="footer">
						<span className="wpo-button view-all-link">
							{__('View all tasks', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</span>
					</div>
				</div>
				<div className="dashboard-widget" id="partial-fulfillments">
					<div className="header">
						<h3>
							{__(
								'Partially shipped fulfillments',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</h3>
					</div>
					<div className="content">
						<FulfillmentOrderSkeleton />
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
						__(
							'Error loading tasks. Please try again.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)
					}
				/>
			</div>
		);
	}

	return (
		<>
			<h2 className="screen-reader-text">
				{__('Dashboard', 'out-the-door-order-tasks-workflows-for-woocommerce')}
			</h2>
			<SidebarModalProvider>
				<div className="dashboard-view">
					<TodayTasks />
					<PartialFulfillments
						orders={fulfillmentOrders}
						meta={fulfillmentMeta}
						isLoading={fulfillmentLoading}
						error={fulfillmentError}
						onPageChange={goToFulfillmentPage}
					/>
				</div>
			</SidebarModalProvider>
		</>
	);
};
