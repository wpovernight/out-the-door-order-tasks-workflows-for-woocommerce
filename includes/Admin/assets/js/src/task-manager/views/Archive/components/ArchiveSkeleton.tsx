import React from 'react';
import { SkeletonLine } from '@shared/components/LoadingSkeleton';

const ITEMS_PER_PAGE = 10;

export const ArchiveSkeleton: React.FC = () => {
	return (
		<div className="archive-view-container">
			<div className="archived-task-table-wrapper">
				<table>
					<thead>
						<tr>
							<th className="task-info">
								<SkeletonLine width="4em" height="1em" />
							</th>
							<th className="task-order-id">
								<SkeletonLine width="4em" height="1em" />
							</th>
							<th className="task-customer">
								<SkeletonLine width="5em" height="1em" />
							</th>
							<th className="task-completed-date">
								<SkeletonLine width="7em" height="1em" />
							</th>
							<th className="task-archived-date">
								<SkeletonLine width="7em" height="1em" />
							</th>
							<th className="task-actions">
								<SkeletonLine width="4em" height="1em" />
							</th>
						</tr>
					</thead>
					<tbody>
						{Array.from({ length: ITEMS_PER_PAGE }).map((_, i) => (
							<tr key={i}>
								<td className="task-info">
									<SkeletonLine width="70%" height="1em" />
								</td>
								<td className="task-order-id">
									<SkeletonLine width="40%" height="1em" />
								</td>
								<td className="task-customer">
									<SkeletonLine width="60%" height="1em" />
								</td>
								<td className="task-completed-date">
									<SkeletonLine width="55%" height="1em" />
								</td>
								<td className="task-archived-date">
									<SkeletonLine width="55%" height="1em" />
								</td>
								<td className="task-actions">
									<SkeletonLine width="2em" height="1em" />
								</td>
							</tr>
						))}
					</tbody>
				</table>
			</div>

			<div className="archive-pagination">
				<SkeletonLine width="5em" height="2em" />
				<SkeletonLine width="6em" height="1em" />
				<SkeletonLine width="5em" height="2em" />
			</div>
		</div>
	);
};