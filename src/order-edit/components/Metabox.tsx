import React from 'react';
import TaskSection from '@orderEdit/components/tasks/TaskSection';
import WooFulfillmentSection from '@orderEdit/components/fulfillments/WooFulfillmentSection';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';

export const MetaBox: React.FC = () => {
	const { isWooFulfillmentsEnabled } = useOrderEditData();

	return (
		<>
			<TaskSection />
			{isWooFulfillmentsEnabled && <WooFulfillmentSection />}
		</>
	);
};
