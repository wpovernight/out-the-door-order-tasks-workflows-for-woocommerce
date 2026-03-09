import React, { useEffect, useRef } from 'react';

export function useOnClickOutside<T extends HTMLElement>(
	ref: React.RefObject<T | null>,
	handler: (event: MouseEvent | TouchEvent) => void
): void {
	const handlerRef = useRef(handler);
	handlerRef.current = handler;

	useEffect(() => {
		const listener = (event: MouseEvent | TouchEvent) => {
			const el = ref?.current;
			if (!el || el.contains(event.target as Node)) {
				return;
			}
			handlerRef.current(event);
		};

		document.addEventListener('mousedown', listener);
		document.addEventListener('touchstart', listener);
		return () => {
			document.removeEventListener('mousedown', listener);
			document.removeEventListener('touchstart', listener);
		};
	}, [ref]);
}
