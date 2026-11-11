import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import {
	draggable,
	dropTargetForElements,
	monitorForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import {
	attachClosestEdge,
	extractClosestEdge,
} from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge';
import { getReorderDestinationIndex } from '@atlaskit/pragmatic-drag-and-drop-hitbox/util/get-reorder-destination-index';

/**
 * The edge of an item that a dragged item would be dropped against.
 */
export type SortableEdge = 'top' | 'bottom';

const SORTABLE_ITEM = Symbol('wpo-otd-sortable-item');

type SortableItemData = {
	[SORTABLE_ITEM]: true;
	listId: string;
	id: string | number;
	/**
	 * The item's index as the list is shown now, or null once it is gone. Read
	 * at the moment it is needed: the list may change during a drag.
	 */
	currentIndex: () => number | null;
};

const isSortableItemData = (
	data: Record<string | symbol, unknown>
): data is SortableItemData => true === data[SORTABLE_ITEM];

const isSortableEdge = (edge: unknown): edge is SortableEdge =>
	'top' === edge || 'bottom' === edge;

/**
 * A copy of the list with the item at `from` moved to `to`, the index it
 * ends up at. An index outside the list leaves the copy as it was.
 *
 * @param list The list.
 * @param from The item's index now.
 * @param to   The index it ends up at.
 * @return The reordered copy.
 */
export function moveItem<T>(list: readonly T[], from: number, to: number): T[] {
	const moved = [...list];
	const inList = (index: number) =>
		Number.isInteger(index) && index >= 0 && index < list.length;

	if (!inList(from) || !inList(to) || from === to) {
		return moved;
	}

	const [item] = moved.splice(from, 1);
	moved.splice(to, 0, item);

	return moved;
}

export interface UseSortableItemOptions {
	/** The list the item belongs to; items only drop onto their own list. */
	listId: string;
	/** The item's id, unique in its list. */
	id: string | number;
	/** The item's index in the list as shown. */
	index: number;
	/** The element that moves and takes drops, e.g. a table row. */
	element: RefObject<HTMLElement | null>;
	/** The element the item is dragged by; the whole element when left out. */
	handle?: RefObject<HTMLElement | null>;
	/** Neither dragged nor dropped onto while true. */
	disabled?: boolean;
}

export interface SortableItemState {
	/** Whether this item is the one being dragged. */
	isDragging: boolean;
	/**
	 * The edge another item of the list would be dropped against, or null when
	 * none is over this one or dropping it here would not move it.
	 */
	closestEdge: SortableEdge | null;
}

/**
 * Make an element an item of a vertical sortable list: draggable by its
 * handle, and a drop target for the list's other items.
 *
 * Pair it with useSortableMonitor() on the same listId, which says where an
 * item was dropped.
 *
 * @param options          The item.
 * @param options.listId   The list it belongs to.
 * @param options.id       Its id, unique in the list.
 * @param options.index    Its index in the list as shown.
 * @param options.element  The element that moves and takes drops.
 * @param options.handle   The element it is dragged by; the whole one if left out.
 * @param options.disabled Neither dragged nor dropped onto while true.
 * @return Whether it is being dragged, and the edge to show a drop line on.
 */
export function useSortableItem({
	listId,
	id,
	index,
	element,
	handle,
	disabled = false,
}: UseSortableItemOptions): SortableItemState {
	const [isDragging, setIsDragging] = useState(false);
	const [closestEdge, setClosestEdge] = useState<SortableEdge | null>(null);

	// Read when needed, so a move does not set the item up again, and a drag
	// started before the list changed still moves the right item.
	const indexRef = useRef(index);
	indexRef.current = index;
	const mountedRef = useRef(true);

	useEffect(() => {
		mountedRef.current = true;
		return () => {
			mountedRef.current = false;
		};
	}, []);

	useEffect(() => {
		const target = element.current;

		if (disabled || !target) {
			setIsDragging(false);
			setClosestEdge(null);
			return;
		}

		const data = (): SortableItemData => ({
			[SORTABLE_ITEM]: true,
			listId,
			id,
			currentIndex: () => (mountedRef.current ? indexRef.current : null),
		});

		const isOtherOfList = (source: Record<string | symbol, unknown>) =>
			isSortableItemData(source) &&
			source.listId === listId &&
			source.id !== id;

		/**
		 * The edge to show, or null where a drop would leave the source where
		 * it is: right below the item above it, or right above the one below.
		 *
		 * @param source The dragged item.
		 * @param edge   The edge it is nearest to.
		 */
		const shownEdge = (
			source: SortableItemData,
			edge: unknown
		): SortableEdge | null => {
			if (!isSortableEdge(edge)) {
				return null;
			}

			const own = indexRef.current;
			const from = source.currentIndex();
			if (
				null !== from &&
				(('bottom' === edge && own === from - 1) ||
					('top' === edge && own === from + 1))
			) {
				return null;
			}

			return edge;
		};

		const onOver = ({
			source,
			self,
		}: {
			source: { data: Record<string | symbol, unknown> };
			self: { data: Record<string | symbol, unknown> };
		}) => {
			if (isOtherOfList(source.data) && isSortableItemData(source.data)) {
				setClosestEdge(
					shownEdge(source.data, extractClosestEdge(self.data))
				);
			}
		};

		return combine(
			draggable({
				element: target,
				dragHandle: handle?.current ?? undefined,
				getInitialData: data,
				onDragStart: () => setIsDragging(true),
				onDrop: () => setIsDragging(false),
			}),
			dropTargetForElements({
				element: target,
				canDrop: ({ source }) => isOtherOfList(source.data),
				getIsSticky: () => true,
				getData: ({ input }) =>
					attachClosestEdge(data(), {
						element: target,
						input,
						allowedEdges: ['top', 'bottom'],
					}),
				onDragEnter: onOver,
				onDrag: onOver,
				onDragLeave: () => setClosestEdge(null),
				onDrop: () => setClosestEdge(null),
			})
		);
	}, [listId, id, element, handle, disabled]);

	return { isDragging, closestEdge };
}

export interface UseSortableMonitorOptions {
	/** The list to watch; drops in other lists are ignored. */
	listId: string;
	/**
	 * Called when an item is dropped where it moves. Both indexes are of the
	 * list as shown at the drop, even when it changed during the drag; a drop
	 * of an item no longer shown, or onto one, is ignored.
	 *
	 * @param from The item's index before the move.
	 * @param to   The index it ends up at, as moveItem() takes it.
	 */
	onMove: (from: number, to: number) => void;
}

/**
 * Watch a sortable list's drops and say where each dropped item moves.
 *
 * @param options        The list and its move handler.
 * @param options.listId The list to watch.
 * @param options.onMove Called with the from and to index of each move.
 */
export function useSortableMonitor({
	listId,
	onMove,
}: UseSortableMonitorOptions): void {
	const onMoveRef = useRef(onMove);
	onMoveRef.current = onMove;

	useEffect(
		() =>
			monitorForElements({
				canMonitor: ({ source }) =>
					isSortableItemData(source.data) &&
					source.data.listId === listId,
				onDrop: ({ source, location }) => {
					const target = location.current.dropTargets[0];

					if (
						!target ||
						!isSortableItemData(source.data) ||
						!isSortableItemData(target.data) ||
						target.data.listId !== listId
					) {
						return;
					}

					// Both read now: an item added or removed since the drag
					// began has shifted them.
					const from = source.data.currentIndex();
					const over = target.data.currentIndex();

					if (null === from || null === over) {
						return;
					}

					const to = getReorderDestinationIndex({
						startIndex: from,
						indexOfTarget: over,
						closestEdgeOfTarget: extractClosestEdge(target.data),
						axis: 'vertical',
					});

					if (from !== to) {
						onMoveRef.current(from, to);
					}
				},
			}),
		[listId]
	);
}
