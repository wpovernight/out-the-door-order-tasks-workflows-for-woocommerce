import { readCss, ruleBody } from './css';

const common = readCss('common.css');
const skeleton = readCss('skeleton.css');

describe('rules empty-state illustration', () => {
	it('is an 80 by 80 SVG of two pills in one grey', () => {
		const match =
			/--wpo-otd-icon-rules-empty:\s*url\(data:image\/svg\+xml,([^)]*)\);/.exec(
				common
			);

		expect(match).not.toBeNull();

		const svg = decodeURIComponent(match![1]);

		expect(svg).toMatch(
			/^<svg width="80" height="80" viewBox="0 0 80 80" [^>]*>/
		);
		expect(
			Array.from(svg.matchAll(/<path [^>]*fill="([^"]+)"/g)).map(
				([, fill]) => fill
			)
		).toEqual(['#717680', '#717680']);
		expect(svg).toMatch(/<path opacity="0.3" /);
	});

	it('is drawn as a full-strength background, keeping its two tones', () => {
		const body = ruleBody(skeleton, '.wpo-otd-empty-state-icon--rules');

		expect(body).toContain(
			'background-image: var(--wpo-otd-icon-rules-empty);'
		);
		expect(body).toContain('opacity: 1;');
		expect(body).not.toContain('mask');
	});

	it('sizes a primary or secondary action like the automations header buttons', () => {
		expect(ruleBody(skeleton, '.wpo-otd-empty-state > .wpo-button')).toBe(
			'min-height: 40px; padding: 0 16px; border-radius: var(--wpo-otd-radius-m); ' +
				'font-size: 1.08em; font-weight: 600;'
		);
	});

	it('shows a title in the text colour and its description in grey', () => {
		expect(ruleBody(skeleton, '.wpo-otd-empty-state-title')).toContain(
			'color: var(--wpo-otd-text-primary);'
		);
		expect(
			ruleBody(skeleton, '.wpo-otd-empty-state-description')
		).toContain('color: var(--wpo-otd-text-gray);');
	});
});
