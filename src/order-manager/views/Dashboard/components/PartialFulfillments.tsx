import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	EmptyState,
	ErrorState,
	Pager,
	useScrollable,
	FulfillmentOrder,
	FulfillmentItem,
	PaginationMeta,
} from '@sdk';
import { FulfillmentOrderSkeleton } from '@orderManager/views/Dashboard/components/FulfillmentOrderSkeleton';

interface PartialFulfillmentsProps {
	orders: FulfillmentOrder[];
	meta: PaginationMeta | null;
	isLoading: boolean;
	error?: Error | null;
	onPageChange: (page: number) => void;
}

export const PartialFulfillments = ({
	orders,
	meta,
	isLoading,
	error,
	onPageChange,
}: PartialFulfillmentsProps) => {
	const [expandedOrders, setExpandedOrders] = useState<Set<number>>(
		new Set()
	);
	const contentRef = useScrollable<HTMLDivElement>();

	const toggleOrder = (orderId: number) => {
		setExpandedOrders((prev) => {
			const next = new Set(prev);
			if (next.has(orderId)) {
				next.delete(orderId);
			} else {
				next.add(orderId);
			}
			return next;
		});
	};

	const formatLocation = (
		location: FulfillmentOrder['customer_location']
	) => {
		const parts = [location.city, location.state, location.country].filter(
			Boolean
		);
		return parts.join(', ');
	};

	const formatAttributes = (attributes: FulfillmentItem['attributes']) => {
		if (
			!attributes ||
			(Array.isArray(attributes) && attributes.length === 0)
		) {
			return '';
		}

		if (typeof attributes === 'string') {
			return attributes;
		}

		return attributes
			.map((attr) => `${attr.label}: ${attr.value}`)
			.join(' \u2022 ');
	};

	const hasOrders = orders && orders.length > 0;
	const showSkeleton = isLoading && orders.length === 0;

	return (
		<div className="dashboard-widget" id="partial-fulfillments">
			<div className="header">
				<h3>
					{__(
						'Partially shipped fulfillments',
						'advanced-order-manager'
					)}
				</h3>
			</div>
			<div
				className={`content${isLoading ? ' is-loading' : ''}`}
				ref={contentRef}
			>
				{showSkeleton && <FulfillmentOrderSkeleton />}
				{!showSkeleton && error && (
					<ErrorState
						message={
							error.message ||
							__(
								'Error loading fulfillments. Please try again.',
								'advanced-order-manager'
							)
						}
					/>
				)}
				{!showSkeleton && !error && !hasOrders && (
					<EmptyState
						icon="box"
						message={__(
							'No partially shipped orders.',
							'advanced-order-manager'
						)}
					/>
				)}
				{!showSkeleton && !error && hasOrders && (
					<ul className="fulfillment-order-list">
						{orders.map((order) => {
							const isExpanded = expandedOrders.has(
								order.order_id
							);
							const unfulfilled = order.items.filter(
								(item) =>
									item.fulfillment_status !== 'fulfilled'
							);

							return (
								<li
									key={order.order_id}
									className={`fulfillment-order ${isExpanded ? 'expanded' : ''}`}
								>
									<h4>
										<button
											className={`fulfillment-order-toggle ${isExpanded ? 'expanded' : 'collapsed'}`}
											type="button"
											onClick={() =>
												toggleOrder(order.order_id)
											}
										>
											<span className="customer-info">
												<a
													href={order.order_url}
													target="_blank"
													className="customer-name"
													rel="noreferrer"
												>
													{order.customer_name ||
														__(
															'Guest',
															'advanced-order-manager'
														)}{' '}
													&bull;{' '}
													{__(
														'Order',
														'advanced-order-manager'
													)}{' '}
													#{order.order_id}{' '}
													<span className="wpo-count-badge">
														{unfulfilled.length}
													</span>
												</a>
												<span className="customer-location">
													{formatLocation(
														order.customer_location
													)}
												</span>
											</span>
										</button>
									</h4>
									<ul
										className={`fulfillment-order-items ${isExpanded ? 'expanded' : 'collapsed'}`}
									>
										{order.items.map((item) => {
											const progressPercent =
												item.quantity > 0
													? (item.fulfilled_quantity /
															item.quantity) *
														100
													: 0;
											const attrText = formatAttributes(
												item.attributes
											);

											return (
												<li
													key={item.item_id}
													className="fulfillment-item"
												>
													{item.image_url && (
														<img
															src={item.image_url}
															alt={item.name}
														/>
													)}
													<div className="item-details">
														<h5 className="item-name">
															<a
																target="_blank"
																href={
																	order.order_url
																}
																rel="noreferrer"
															>
																{item.name}
															</a>
														</h5>
														{attrText && (
															<span className="item-attributes">
																{attrText}
															</span>
														)}
														<div className="item-progress">
															<div className="progress-bar">
																<div
																	className="progress-fill"
																	style={{
																		width: `${progressPercent}%`,
																	}}
																/>
															</div>
															<span className="progress-text">
																{
																	item.fulfilled_quantity
																}{' '}
																/{' '}
																{item.quantity}{' '}
																{__(
																	'fulfilled',
																	'advanced-order-manager'
																)}
															</span>
														</div>
													</div>
												</li>
											);
										})}
									</ul>
								</li>
							);
						})}
					</ul>
				)}
			</div>
			{hasOrders && meta && meta.last_page > 1 ? (
				<div className="footer">
					<Pager
						currentPage={meta.current_page}
						lastPage={meta.last_page}
						onChange={onPageChange}
						disabled={isLoading}
					/>
				</div>
			) : (
				!isLoading && (
					<div className="footer">
						<a
							href="edit.php?post_type=shop_order"
							className="wpo-button view-all-link"
						>
							{__('View all orders', 'advanced-order-manager')}
						</a>
					</div>
				)
			)}
		</div>
	);
};
