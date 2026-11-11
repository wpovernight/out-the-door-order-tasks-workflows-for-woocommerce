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

	it('is a 24 by 24 SVG', () => {
		expect(svg).toMatch(
			/^<svg width="24" height="24" viewBox="0 0 24 24" [^>]*>/
		);
		expect(svg).toMatch(/<\/svg>\n?$/);
	});

	it('draws a grip of two columns and three rows of dots', () => {
		// Each dot starts at its bottom point: its centre's x, then y + 2.
		const dots = Array.from(svg.matchAll(/M([\d.]+) ([\d.]+)C/g)).map(
			([, x, y]) => `${x},${Number(y) - 2}`
		);

		expect(dots).toEqual(['9,18', '15,18', '9,12', '15,12', '9,6', '15,6']);
	});

	it('is one filled shape, so a mask can colour it', () => {
		expect(Array.from(svg.matchAll(/<path /g))).toHaveLength(1);
		expect(svg).toMatch(/<path [^>]*fill="#[0-9A-F]{6}"\/>/i);
	});
});
