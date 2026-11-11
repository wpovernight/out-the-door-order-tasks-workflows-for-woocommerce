import React, { useRef } from 'react';
import { act, render } from '@testing-library/react';
import { moveItem, useSortableItem, useSortableMonitor } from '@sdk';
import type { SortableItemState } from '@sdk';

type Data = Record<string | symbol, unknown>;
type Args = Record<string, (...args: never[]) => unknown> & {
	element?: HTMLElement;
	dragHandle?: HTMLElement;
};

const mockRegistered: {
	draggables: Args[];
	targets: Args[];
	monitors: Args[];
} = { draggables: [], targets: [], monitors: [] };
const registered = mockRegistered;

// The adapter needs real drag events, which jsdom does not fire: it is
// replaced by one that keeps what each hook registers, so a test can play
// the adapter's part.
jest.mock('@atlaskit/pragmatic-drag-and-drop/element/adapter', () => {
	const register =
		(list: 'draggables' | 'targets' | 'monitors') => (args: Args) => {
			mockRegistered[list].push(args);
			return () =>
				mockRegistered[list].splice(
					mockRegistered[list].indexOf(args),
					1
				);
		};

	return {
		draggable: register('draggables'),
		dropTargetForElements: register('targets'),
		monitorForElements: register('monitors'),
	};
});

const ROW_HEIGHT = 40;

/**
 * Give an element a row's box at this index, for the edge it is nearest to.
 *
 * @param element The element.
 * @param index   Its index in the list.
 */
const placeRow = (element: HTMLElement, index: number) => {
	const top = index * ROW_HEIGHT;
	element.getBoundingClientRect = () =>
		({
			top,
			bottom: top + ROW_HEIGHT,
			left: 0,
			right: 100,
			height: ROW_HEIGHT,
			width: 100,
			x: 0,
			y: top,
		}) as DOMRect;
};

const states: Record<string, SortableItemState> = {};

const Item = ({
	listId,
	id,
	index,
	disabled,
	withHandle,
}: {
	listId: string;
	id: string;
	index: number;
	disabled?: boolean;
	withHandle?: boolean;
}) => {
	const element = useRef<HTMLLIElement>(null);
	const handle = useRef<HTMLSpanElement>(null);
	states[id] = useSortableItem({
		listId,
		id,
		index,
		element,
		handle: withHandle ? handle : undefined,
		disabled,
	});

	return (
		<li ref={element} data-id={id}>
			{withHandle && <span ref={handle} data-handle={id} />}
			{id}
		</li>
	);
};

const List = ({
	listId = 'rules',
	ids,
	disabled,
	withHandle,
	onMove = () => undefined,
}: {
	listId?: string;
	ids: string[];
	disabled?: boolean;
	withHandle?: boolean;
	onMove?: (from: number, to: number) => void;
}) => {
	useSortableMonitor({ listId, onMove });

	return (
		<ul>
			{ids.map((id, index) => (
				<Item
					key={id}
					listId={listId}
					id={id}
					index={index}
					disabled={disabled}
					withHandle={withHandle}
				/>
			))}
		</ul>
	);
};

const draggableOf = (id: string): Args => {
	const found = registered.draggables.find(
		(args) => args.element?.dataset.id === id
	);
	if (!found) {
		throw new Error(`${id} is not draggable`);
	}
	return found;
};

const targetOf = (id: string): Args => {
	const found = registered.targets.find(
		(args) => args.element?.dataset.id === id
	);
	if (!found) {
		throw new Error(`${id} takes no drops`);
	}
	return found;
};

/**
 * Play a drag of one item onto another's top or bottom edge, as the adapter
 * would: start, move over the target, drop.
 *
 * @param sourceId The dragged item.
 * @param targetId The item it is dropped on.
 * @param edge     The edge of the target it is nearest to.
 * @return The drop target's data.
 */
const drag = (sourceId: string, targetId: string, edge: 'top' | 'bottom') => {
	const source = { data: draggableOf(sourceId).getInitialData() as Data };
	const target = targetOf(targetId);
	const element = target.element as HTMLElement;
	const box = element.getBoundingClientRect();
	const input = {
		clientX: 50,
		clientY: 'top' === edge ? box.top + 2 : box.bottom - 2,
	};
	const self = {
		data: (target.getData as (args: unknown) => Data)({
			input,
			element,
		}),
	};
	const location = { current: { dropTargets: [self] } };

	act(() => {
		draggableOf(sourceId).onDragStart();
	});
	act(() => {
		target.onDragEnter({ source, self } as never);
	});
	const over = { ...states };
	act(() => {
		for (const monitor of registered.monitors) {
			if (monitor.canMonitor({ source } as never)) {
				monitor.onDrop({ source, location } as never);
			}
		}
		target.onDrop({ source, self } as never);
		draggableOf(sourceId).onDrop();
	});

	return over;
};

const renderList = (props: React.ComponentProps<typeof List>) => {
	const result = render(<List {...props} />);
	document
		.querySelectorAll<HTMLElement>('li[data-id]')
		.forEach((element, index) => placeRow(element, index));
	return result;
};

beforeEach(() => {
	for (const id of Object.keys(states)) {
		delete states[id];
	}
});

describe('moveItem', () => {
	const list = ['a', 'b', 'c', 'd'];

	it.each([
		[0, 3, ['b', 'c', 'd', 'a']],
		[3, 0, ['d', 'a', 'b', 'c']],
		[1, 2, ['a', 'c', 'b', 'd']],
		[2, 1, ['a', 'c', 'b', 'd']],
	])('moves the item at %i to %i', (from, to, expected) => {
		expect(moveItem(list, from, to)).toEqual(expected);
	});

	it.each([
		['the same index', 2, 2],
		['a negative from', -1, 2],
		['a from past the end', 4, 0],
		['a to past the end', 0, 4],
		['a negative to', 1, -1],
		['a fraction', 0.5, 2],
	])('returns an unchanged copy for %s', (_name, from, to) => {
		const moved = moveItem(list, from, to);

		expect(moved).toEqual(list);
		expect(moved).not.toBe(list);
	});

	it('handles an empty and a one-item list', () => {
		expect(moveItem([], 0, 0)).toEqual([]);
		expect(moveItem(['a'], 0, 0)).toEqual(['a']);
	});

	it('never changes the list given', () => {
		const given = ['a', 'b', 'c'];
		moveItem(given, 0, 2);

		expect(given).toEqual(['a', 'b', 'c']);
	});
});

describe('useSortableItem and useSortableMonitor', () => {
	it.each([
		['down onto the bottom of the last', 'a', 'd', 'bottom', 0, 3],
		['down onto the top of a later one', 'a', 'c', 'top', 0, 1],
		['down onto the bottom of a later one', 'a', 'c', 'bottom', 0, 2],
		['up onto the top of the first', 'd', 'a', 'top', 3, 0],
		['up onto the bottom of an earlier one', 'd', 'a', 'bottom', 3, 1],
		['up onto the top of an earlier one', 'c', 'b', 'top', 2, 1],
	] as const)(
		'moves an item dragged %s',
		(_name, source, target, edge, from, to) => {
			const onMove = jest.fn();
			renderList({ ids: ['a', 'b', 'c', 'd'], onMove });

			const over = drag(source, target, edge);

			expect(onMove).toHaveBeenCalledTimes(1);
			expect(onMove).toHaveBeenCalledWith(from, to);
			expect(over[target].closestEdge).toBe(edge);
		}
	);

	it.each([
		['bottom of the item above it', 'b', 'a', 'bottom'],
		['top of the item below it', 'b', 'c', 'top'],
	] as const)(
		'neither moves nor shows a line for a drop on the %s',
		(_name, source, target, edge) => {
			const onMove = jest.fn();
			renderList({ ids: ['a', 'b', 'c'], onMove });

			const over = drag(source, target, edge);

			expect(onMove).not.toHaveBeenCalled();
			expect(over[target].closestEdge).toBeNull();
		}
	);

	it('marks the dragged item while it is dragged, and clears the line on drop', () => {
		renderList({ ids: ['a', 'b'] });

		act(() => {
			draggableOf('a').onDragStart();
		});
		expect(states.a.isDragging).toBe(true);
		expect(states.b.isDragging).toBe(false);

		drag('a', 'b', 'bottom');
		expect(states.a.isDragging).toBe(false);
		expect(states.b.closestEdge).toBeNull();
	});

	it('takes a drop from its own list only, never onto itself', () => {
		renderList({ ids: ['a', 'b'] });
		const ownSource = { data: draggableOf('a').getInitialData() as Data };

		expect(targetOf('b').canDrop({ source: ownSource } as never)).toBe(
			true
		);
		expect(targetOf('a').canDrop({ source: ownSource } as never)).toBe(
			false
		);

		const otherList = {
			data: { ...ownSource.data, listId: 'tasks', id: 'x' },
		};
		expect(targetOf('b').canDrop({ source: otherList } as never)).toBe(
			false
		);
		const { a: _a, ...plain } = { a: 1, listId: 'rules', id: 'x' };
		expect(
			targetOf('b').canDrop({ source: { data: plain } } as never)
		).toBe(false);
		expect(targetOf('b').canDrop({ source: { data: {} } } as never)).toBe(
			false
		);
	});

	it('leaves a drop in another list to that list', () => {
		const onRules = jest.fn();
		const onTasks = jest.fn();
		render(
			<>
				<List listId="rules" ids={['a', 'b']} onMove={onRules} />
				<List listId="tasks" ids={['x', 'y']} onMove={onTasks} />
			</>
		);
		document
			.querySelectorAll<HTMLElement>('li[data-id]')
			.forEach((element, index) => placeRow(element, index % 2));

		drag('x', 'y', 'bottom');

		expect(onTasks).toHaveBeenCalledWith(0, 1);
		expect(onRules).not.toHaveBeenCalled();
		expect(
			registered.monitors.filter((monitor) =>
				monitor.canMonitor({
					source: { data: draggableOf('x').getInitialData() },
				} as never)
			)
		).toHaveLength(1);
	});

	it('sets nothing up while disabled, and again once enabled', () => {
		const { rerender } = renderList({ ids: ['a', 'b'], disabled: true });

		expect(registered.draggables).toHaveLength(0);
		expect(registered.targets).toHaveLength(0);

		rerender(<List ids={['a', 'b']} />);
		expect(registered.draggables).toHaveLength(2);
		expect(registered.targets).toHaveLength(2);

		rerender(<List ids={['a', 'b']} disabled />);
		expect(registered.draggables).toHaveLength(0);
		expect(registered.targets).toHaveLength(0);
	});

	it('drags by the handle when one is given', () => {
		renderList({ ids: ['a'], withHandle: true });

		expect(draggableOf('a').dragHandle).toBe(
			document.querySelector('[data-handle="a"]')
		);
	});

	it('reads the index of each drag, so a moved item drags from where it is', () => {
		const onMove = jest.fn();
		const { rerender } = renderList({ ids: ['a', 'b', 'c'], onMove });

		rerender(<List ids={['c', 'a', 'b']} onMove={onMove} />);
		document
			.querySelectorAll<HTMLElement>('li[data-id]')
			.forEach((element, index) => placeRow(element, index));

		drag('a', 'b', 'bottom');
		expect(onMove).toHaveBeenCalledWith(1, 2);
	});

	it('stops watching and dragging once unmounted', () => {
		const { unmount } = renderList({ ids: ['a'] });
		unmount();

		expect(registered.draggables).toHaveLength(0);
		expect(registered.targets).toHaveLength(0);
		expect(registered.monitors).toHaveLength(0);
	});

	it('calls the newest move handler without watching again', () => {
		const first = jest.fn();
		const second = jest.fn();
		const { rerender } = renderList({ ids: ['a', 'b'], onMove: first });
		const monitors = [...registered.monitors];

		rerender(<List ids={['a', 'b']} onMove={second} />);
		expect(registered.monitors).toEqual(monitors);

		drag('a', 'b', 'bottom');
		expect(second).toHaveBeenCalledWith(0, 1);
		expect(first).not.toHaveBeenCalled();
	});
});
