import * as React from 'react';
import {draggable} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';

type Props = {
	id: number;
	title: string;
};

const TaskCard: React.FC<Props> = ({id, title}) => {
	const ref = React.useRef<HTMLDivElement>(null);

	React.useEffect(() => {
		const element = ref.current;
		if (!element) return;
		return draggable({
			element: element,
			getInitialData: () => ({taskId: id}),
		});
	}, [id]);

	return (
		<div
			ref={ref}
			className="card"
		>
			{title}
		</div>
	);
};

export default TaskCard;
