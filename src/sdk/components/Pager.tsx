import React from 'react';
import { __ } from '@wordpress/i18n';

interface PagerProps {
	currentPage: number;
	lastPage: number;
	onChange: (page: number) => void;
	disabled?: boolean;
}

type PageToken = number | 'ellipsis';

function range(start: number, end: number): number[] {
	return Array.from(
		{ length: Math.max(end - start + 1, 0) },
		(_, index) => start + index
	);
}

function clamp(value: number, min: number, max: number): number {
	return Math.min(Math.max(value, min), max);
}

/**
 * Builds the page tokens for the pager, mirroring MUI's `usePagination`.
 *
 * A pager is three blocks:
 * the first `boundaryCount` pages,
 * a window of `siblingCount` pages either side of the current page,
 * and the last `boundaryCount` pages
 *
 * Whatever they skip collapsed into an ellipsis:
 *
 *     1 2 … 8 9 10 … 24 25
 *     ^^^   ^^^^^^^   ^^^^^
 *     start  window     end
 */
function buildPageTokens(
	current: number,
	last: number,
	boundaryCount = 1,
	siblingCount = 1
): PageToken[] {
	const windowSize = siblingCount * 2 + 1;

	// The window may only occupy the middle, and never the page directly next to
	// a boundary block — that slot is reserved for the ellipsis, so the pager
	// keeps a constant width instead of resizing as the user pages through.
	const firstMiddle = boundaryCount + 2;
	const lastMiddle = last - boundaryCount - 1;

	// Centre the window on the current page, then slide it back inside the
	// middle when the current page sits near either end.
	const windowStart = clamp(
		current - siblingCount,
		firstMiddle,
		Math.max(lastMiddle - windowSize + 1, firstMiddle)
	);
	const windowEnd = Math.min(windowStart + windowSize - 1, lastMiddle);

	const visible = new Set([
		...range(1, boundaryCount),
		...range(windowStart, windowEnd),
		...range(last - boundaryCount + 1, last),
	]);

	// A reserved slot that ends up hiding a single page is cheaper to spend on
	// that page than on an ellipsis, so claim it back when the window is flush
	// against a boundary block.
	if (windowStart <= firstMiddle) {
		visible.add(boundaryCount + 1);
	}
	if (windowEnd >= lastMiddle) {
		visible.add(last - boundaryCount);
	}

	const pages = [...visible]
		.filter((page) => page >= 1 && page <= last)
		.sort((a, b) => a - b);

	// Second pass: any remaining hole between two visible pages is an ellipsis.
	const tokens: PageToken[] = [];
	let previous = 0;

	for (const page of pages) {
		if (page - previous > 1) {
			tokens.push('ellipsis');
		}

		tokens.push(page);
		previous = page;
	}

	return tokens;
}

/**
 * Prev / Next + numbered page links. Renders nothing when there is a single page.
 */
export const Pager = ({
	currentPage,
	lastPage,
	onChange,
	disabled = false,
}: PagerProps) => {
	if (lastPage <= 1) {
		return null;
	}

	const tokens = buildPageTokens(currentPage, lastPage);
	const isFirst = currentPage <= 1;
	const isLast = currentPage >= lastPage;

	return (
		<nav
			className={`wpo-aom-pager${disabled ? ' is-loading' : ''}`}
		>
			<button
				type="button"
				className="wpo-aom-pager-nav wpo-aom-pager-prev"
				onClick={() => onChange(currentPage - 1)}
				disabled={disabled || isFirst}
			>
				{__('Previous', 'advanced-order-manager-for-woocommerce')}
			</button>

			<div className="wpo-aom-pager-pages">
				{tokens.map((token, index) =>
					token === 'ellipsis' ? (
						<span
							key={`ellipsis-${index}`}
							className="wpo-aom-pager-ellipsis"
						>
							&hellip;
						</span>
					) : (
						<button
							key={token}
							type="button"
							className={`wpo-aom-pager-page${token === currentPage ? ' is-current' : ''}`}
							onClick={() => onChange(token)}
							disabled={disabled || token === currentPage}
						>
							{token}
						</button>
					)
				)}
			</div>

			<button
				type="button"
				className="wpo-aom-pager-nav wpo-aom-pager-next"
				onClick={() => onChange(currentPage + 1)}
				disabled={disabled || isLast}
			>
				{__('Next', 'advanced-order-manager-for-woocommerce')}
			</button>
		</nav>
	);
};
