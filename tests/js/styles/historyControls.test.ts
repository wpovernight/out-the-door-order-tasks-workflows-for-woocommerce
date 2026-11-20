import { readCss, ruleBody } from './css';

const css = readCss('common.css');

/**
 * The SVG an icon variable holds, decoded from its data URI.
 *
 * @param name The variable, e.g. '--wpo-otd-icon-view'.
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

describe('checkbox and view icons', () => {
	it.each([
		[
			'--wpo-otd-icon-checkbox-check',
			'M14.6668 6.5L8.25016 12.9167L5.3335 10',
		],
		['--wpo-otd-icon-checkbox-dash', 'M5.9165 10H14.0832'],
	])(
		'%s is the mark alone, on the 20 by 20 frame of the checkbox',
		(name, path) => {
			const svg = iconSvg(name);

			expect(svg).toMatch(
				/^<svg width="20" height="20" viewBox="0 0 20 20" [^>]*>/
			);
			expect(svg).toContain(`<path d="${path}"`);
			expect(svg).not.toContain('<rect');
			expect(svg).toMatch(/<\/svg>$/);
		}
	);

	it('holds the view icon: an eye with its pupil', () => {
		const svg = iconSvg('--wpo-otd-icon-view');

		expect(svg).toMatch(
			/^<svg width="16" height="14" viewBox="0 0 16 14" /
		);
		expect(svg).toContain('<path d="M0.75 7C2.25 3.9');
		expect(svg).toContain('<circle cx="8" cy="7" r="2.25"');
	});

	it('masks the view button with the view icon', () => {
		expect(
			ruleBody(
				css,
				'.wpo-otd-row-actions > li > button.wpo-otd-view-button::before'
			)
		).toBe(
			'mask-image: var(--wpo-otd-icon-view); -webkit-mask-image: var(--wpo-otd-icon-view);'
		);
	});
});

describe('.wpo-otd-checkbox', () => {
	it('is a rounded square in the border colour, without the browser look', () => {
		const body = ruleBody(css, 'input[type="checkbox"].wpo-otd-checkbox');

		expect(body).toContain('appearance: none;');
		expect(body).toContain(
			'border: 1px solid var(--wpo-otd-border-color);'
		);
		expect(body).toContain('border-radius: var(--wpo-otd-radius-m);');
	});

	it('shows the focus ring of core fields when reached by keyboard', () => {
		expect(
			ruleBody(
				css,
				'input[type="checkbox"].wpo-otd-checkbox:focus-visible'
			)
		).toBe(
			'border-color: var(--wpo-otd-wp-blue); box-shadow: 0 0 0 1px var(--wpo-otd-wp-blue); outline: 2px solid transparent;'
		);
		// Placed after the ticked look, which sets a purple border.
		expect(
			css.indexOf('input[type="checkbox"].wpo-otd-checkbox:focus-visible')
		).toBeGreaterThan(
			css.indexOf(
				'input[type="checkbox"].wpo-otd-checkbox:indeterminate::before {'
			)
		);
	});

	it('is purple when ticked or indeterminate', () => {
		expect(
			ruleBody(
				css,
				'input[type="checkbox"].wpo-otd-checkbox:checked, input[type="checkbox"].wpo-otd-checkbox:indeterminate'
			)
		).toBe(
			'border-color: var(--wpo-otd-purple-primary); background: var(--wpo-otd-purple-bg);'
		);
	});

	it('draws the tick, and the dash when indeterminate, as masks', () => {
		const mark = ruleBody(
			css,
			'input[type="checkbox"].wpo-otd-checkbox:checked::before, input[type="checkbox"].wpo-otd-checkbox:indeterminate::before'
		);

		expect(mark).toContain("content: '';");
		expect(mark).toContain(
			'background-color: var(--wpo-otd-purple-primary);'
		);
		expect(mark).toContain(
			'mask-image: var(--wpo-otd-icon-checkbox-check);'
		);
		expect(
			ruleBody(
				css,
				'input[type="checkbox"].wpo-otd-checkbox:indeterminate::before'
			)
		).toBe(
			'mask-image: var(--wpo-otd-icon-checkbox-dash); -webkit-mask-image: var(--wpo-otd-icon-checkbox-dash);'
		);
	});
});

describe('.wpo-otd-segmented', () => {
	it('is one bordered control with rounded outer corners', () => {
		const body = ruleBody(css, '.wpo-otd-segmented');

		expect(body).toContain(
			'border: 1px solid var(--wpo-otd-border-color);'
		);
		expect(body).toContain('border-radius: var(--wpo-otd-radius-m);');
		expect(body).toContain('overflow: hidden;');
	});

	it('fills the pressed button grey, in the text colour', () => {
		expect(
			ruleBody(
				css,
				'.wpo-otd-segmented > .wpo-button[aria-pressed="true"]'
			)
		).toBe(
			'background: var(--wpo-otd-bg-gray-medium); color: var(--wpo-otd-text-primary);'
		);
	});
});

describe('.wpo-otd-select', () => {
	it('hides the native arrow of its select', () => {
		const body = ruleBody(
			css,
			'#wpcontent .wpo-otd-select > select.wpo-otd-input'
		);

		expect(body).toContain('appearance: none;');
		expect(body).toContain('background-image: none;');
	});

	it('draws core chevron after it', () => {
		const body = ruleBody(css, '.wpo-otd-select::after');

		expect(body).toContain('mask-image: var(--wpo-otd-icon-chevron-down);');
		expect(body).toContain('pointer-events: none;');
	});
});
