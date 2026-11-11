import { readFileSync } from 'fs';
import { join } from 'path';

/**
 * A stylesheet in assets/css, as text.
 *
 * @param file The file name, e.g. 'common.css'.
 */
export const readCss = (file: string): string =>
	readFileSync(join(__dirname, '../../../assets/css', file), 'utf8');

/**
 * The declarations of the rule whose selector list is exactly this, joined by
 * single spaces; empty when there is no such rule.
 *
 * @param css      The stylesheet.
 * @param selector The rule's selector list, selectors joined by ", ".
 */
export const ruleBody = (css: string, selector: string): string => {
	const withoutComments = css.replace(/\/\*[\s\S]*?\*\//g, '');
	const rule = Array.from(
		withoutComments.matchAll(/([^{}]+)\{([^}]*)\}/g)
	).find(
		([, selectors]) =>
			selectors
				.trim()
				.split(/\s*,\s*/)
				.join(', ') === selector
	);

	return rule ? rule[2].replace(/\s+/g, ' ').trim() : '';
};
