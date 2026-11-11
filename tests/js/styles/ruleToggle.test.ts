import { readCss, ruleBody } from './css';

const css = readCss('order-manager.css');
const toggle = '#wpo-otd-order-manager .automations-rule-toggle';

describe('rule toggle', () => {
	it('shows a pointer, and not-allowed only when disabled', () => {
		expect(ruleBody(css, toggle)).toContain('cursor: pointer;');
		expect(ruleBody(css, toggle)).not.toContain('not-allowed');
		expect(
			ruleBody(css, `${toggle}:disabled, ${toggle}[aria-disabled="true"]`)
		).toBe('cursor: not-allowed;');
	});

	it('fills with the primary purple and moves the knob when on', () => {
		expect(ruleBody(css, `${toggle}[aria-checked="true"]`)).toBe(
			'background: var(--wpo-otd-purple-primary);'
		);
		expect(ruleBody(css, `${toggle}[aria-checked="true"]::after`)).toBe(
			'transform: translateX(14px);'
		);
	});

	it('fades a disabled toggle that is on', () => {
		expect(
			ruleBody(
				css,
				`${toggle}[aria-checked="true"]:disabled, ${toggle}[aria-checked="true"][aria-disabled="true"]`
			)
		).toBe('opacity: 0.5;');
	});

	it('rings the toggle on keyboard focus and shows progress while busy', () => {
		expect(ruleBody(css, `${toggle}:focus-visible`)).toBe(
			'outline: 2px solid var(--wpo-otd-purple-primary); outline-offset: 2px;'
		);
		expect(ruleBody(css, `${toggle}[aria-busy="true"]`)).toBe(
			'cursor: progress;'
		);
	});
});
