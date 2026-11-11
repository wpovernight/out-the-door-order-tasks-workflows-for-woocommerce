import { readCss, ruleBody } from './css';

const css = readCss('common.css');

/**
 * The SVG an icon variable holds, decoded from its data URI.
 *
 * @param name The variable, e.g. '--wpo-otd-icon-copy'.
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

describe('row action icons', () => {
	it('holds the copy icon as an SVG data URI', () => {
		const svg = iconSvg('--wpo-otd-icon-copy');

		expect(svg).toMatch(/^<svg [^>]*viewBox="0 0 18 18"/);
		expect(svg).toContain('<path d="M6.75 13.5');
		expect(svg).toMatch(/<\/svg>$/);
	});

	it.each([
		['wpo-otd-edit-button', '--wpo-otd-icon-edit'],
		['wpo-otd-copy-button', '--wpo-otd-icon-copy'],
		['wpo-otd-delete-button', '--wpo-otd-icon-delete'],
	])('masks the %s with %s', (button, icon) => {
		expect(
			ruleBody(
				css,
				`.wpo-otd-row-actions > li > button.${button}::before`
			)
		).toBe(`mask-image: var(${icon}); -webkit-mask-image: var(${icon});`);
	});
});
