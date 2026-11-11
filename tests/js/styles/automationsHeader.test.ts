import { readCss, ruleBody } from './css';

const css = readCss('order-manager.css');
const header = '#wpo-otd-order-manager .automations-header';

describe('automations header', () => {
	it('draws a light rule across the header, the controls sitting on it', () => {
		const body = ruleBody(css, header);

		expect(body).toContain(
			'border-bottom: 1px solid var(--wpo-otd-border-light);'
		);
		expect(body).toContain('align-items: flex-end;');
		expect(body).not.toContain('padding-left');
	});

	it('underlines the active sub-tab in purple, over the rule', () => {
		const body = ruleBody(css, `${header} .automations-header-tab`);

		expect(body).toContain(
			'border-bottom: 2px solid var(--wpo-otd-purple-primary);'
		);
		expect(body).toContain('margin-bottom: -1px;');
	});
});
