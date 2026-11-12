import { readCss, ruleBody } from './css';

const common = readCss('common.css');
const modal = readCss('sidebar-modal.css');
const input = '#wpcontent .wpo-otd-input';
const datepicker = '.wpo-otd-datepicker-trigger';

describe('shared input', () => {
	it('is drawn from tokens, at the height and colour the browser gives a field', () => {
		const body = ruleBody(common, input);

		expect(body).toContain(
			'border: 1px solid var(--wpo-otd-border-color);'
		);
		expect(body).toContain('border-radius: var(--wpo-otd-radius-m);');
		expect(body).toContain('padding: 0.63em 0.8em;');
		expect(body).not.toContain('height');
		expect(body).not.toMatch(/(^|; )color:/);
	});

	it("leaves the task form's own fields to it, and styles only other components' fields in the form", () => {
		const scoped = (state: string) =>
			['input', 'select', 'textarea']
				.map(
					(tag) =>
						`.wpo-otd-task-form .field-group ${tag}:not(.wpo-otd-input)${state}`
				)
				.join(', ');

		expect(ruleBody(modal, scoped(''))).toContain('padding: 0.63em 0.8em;');
		expect(ruleBody(modal, scoped(':focus'))).toContain(
			'box-shadow: 0 0 0 1px var(--wpo-otd-wp-blue);'
		);
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
