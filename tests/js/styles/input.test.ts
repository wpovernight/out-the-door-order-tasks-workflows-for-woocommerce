import { readCss, ruleBody } from './css';

const common = readCss('common.css');
const modal = readCss('sidebar-modal.css');
const input = '#wpcontent .wpo-otd-input';
const datepicker = '.wpo-otd-datepicker-trigger';

describe('shared input', () => {
	it('is drawn from tokens, as tall as the date picker', () => {
		const body = ruleBody(common, input);

		expect(body).toContain(
			'border: 1px solid var(--wpo-otd-border-color);'
		);
		expect(body).toContain('border-radius: var(--wpo-otd-radius-m);');
		expect(body).toContain('min-height: 3.4em;');
		expect(ruleBody(modal, datepicker)).toContain('height: 3.4em;');
	});

	it('focuses like the task form fields', () => {
		expect(ruleBody(common, `${input}:focus`)).toContain(
			'box-shadow: 0 0 0 1px var(--wpo-otd-wp-blue);'
		);
	});

	it('shows a refused value in the error colour, as the date picker does', () => {
		expect(ruleBody(common, `${input}[aria-invalid="true"]`)).toBe(
			'border-color: var(--wpo-otd-error);'
		);
		expect(ruleBody(modal, `${datepicker}.is-invalid`)).toBe(
			'border-color: var(--wpo-otd-error);'
		);
	});

	it('looks the same disabled as the disabled date picker', () => {
		const disabled = ruleBody(common, `${input}:disabled`);
		const pickerDisabled = ruleBody(modal, `${datepicker}:disabled`);

		for (const declaration of [
			'color: var(--wpo-otd-text-gray);',
			'background: var(--wpo-otd-bg-light);',
			'cursor: not-allowed;',
		]) {
			expect(disabled).toContain(declaration);
			expect(pickerDisabled).toContain(declaration);
		}
	});
});
