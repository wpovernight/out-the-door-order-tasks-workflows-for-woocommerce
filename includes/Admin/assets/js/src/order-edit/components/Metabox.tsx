import React from 'react';
import TaskSection from '@orderEdit/components/tasks/TaskSection';
import FulfillmentSection from '@orderEdit/components/fulfillments/FulfillmentSection';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';

export const MetaBox: React.FC = () => {
	const { isWooFulfillmentsEnabled } = useOrderEditData();

	return (
		<>
			<TaskSection />
			{isWooFulfillmentsEnabled && <FulfillmentSection />}
		</>
	);
};
