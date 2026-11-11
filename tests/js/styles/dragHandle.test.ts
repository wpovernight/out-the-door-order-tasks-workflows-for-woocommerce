import { readCss } from './css';

const css = readCss('common.css');

/**
 * The SVG an icon variable holds, decoded from its data URI.
 *
 * @param name The variable, e.g. '--wpo-otd-icon-drag-handle'.
 */
const iconSvg = (name: string): string => {
	const match = new RegExp(
		`${name}:\\s*url\\(data:image/svg\\+xml,([^)]*)\\);`
	).exec(css);

	if (!match) {
		throw new Error(`No ${name} in common.css`);
	}

	return decodeURIComponent(match[1]);
};

describe('drag handle icon', () => {
	const svg = iconSvg('--wpo-otd-icon-drag-handle');

	it('is a 16 by 16 SVG', () => {
		expect(svg).toMatch(
			/^<svg width="16" height="16" viewBox="0 0 16 16" [^>]*>/
		);
		expect(svg).toMatch(/<\/svg>$/);
	});

	it('draws a grip of two columns and three rows of dots', () => {
		const dots = Array.from(
			svg.matchAll(/<circle cx="([\d.]+)" cy="([\d.]+)" r="1.5"/g)
		).map(([, cx, cy]) => `${cx},${cy}`);

		expect(dots).toEqual([
			'5.5,3',
			'10.5,3',
			'5.5,8',
			'10.5,8',
			'5.5,13',
			'10.5,13',
		]);
	});

	it('is filled like the three dots icon', () => {
		const dotsFill = /fill="(#[0-9A-F]+)"\/>/i.exec(
			iconSvg('--wpo-otd-icon-dots-three')
		)?.[1];

		expect(dotsFill).toBeDefined();
		expect(
			Array.from(svg.matchAll(/<circle [^>]*fill="([^"]+)"/g)).map(
				([, fill]) => fill
			)
		).toEqual(Array(6).fill(dotsFill));
	});
});
