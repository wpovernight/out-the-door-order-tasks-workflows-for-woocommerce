import { useEffect, useRef } from 'react';

/**
 * Toggles `is-scrollable` class on the element when its content overflows vertically.
 * This allows CSS to conditionally apply padding only when the content overflows,
 * so the scrollbar doesn't overlap the content.
 */
export function useScrollable<T extends HTMLElement>(): React.RefObject<T | null> {
	const ref = useRef<T | null>(null);

	useEffect(() => {
		const el = ref.current;
		if (!el) return;

		const check = () => {
			el.classList.toggle(
				'is-scrollable',
				el.scrollHeight > el.clientHeight
			);
		};

		const observer = new ResizeObserver(check);
		observer.observe(el);
		check();

		return () => observer.disconnect();
	}, []);

	return ref;
}