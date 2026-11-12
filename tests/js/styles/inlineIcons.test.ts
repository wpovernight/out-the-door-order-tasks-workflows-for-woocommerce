import { readCss } from './css';

const css = readCss('common.css');

/**
 * The SVG an icon variable holds, decoded from its data URI.
 *
 * @param name The variable, e.g. '--wpo-otd-icon-info'.
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

describe.each(['--wpo-otd-icon-warning-inline', '--wpo-otd-icon-info'])(
	'%s',
	(name) => {
		const svg = iconSvg(name);

		it('is a 16 by 16 SVG, for use beside a line of text', () => {
			expect(svg).toMatch(
				/^<svg width="16" height="16" viewBox="0 0 16 16" [^>]*>/
			);
			expect(svg).toMatch(/<\/svg>\n?$/);
		});

		it('is one filled shape with holes, so a mask can colour it', () => {
			expect(Array.from(svg.matchAll(/<path /g))).toHaveLength(1);
			expect(svg).toMatch(/<path fill-rule="evenodd" [^>]*fill="#[0-9A-F]{6}"\/>/i);
		});
	}
);
