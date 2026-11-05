import { truncateText } from '@sdk/utils/textUtils';

describe('truncateText', () => {
	it('returns text at or under the limit unchanged', () => {
		expect(truncateText('short', 5)).toBe('short');
		expect(truncateText('abc', 10)).toBe('abc');
	});

	it('returns empty text unchanged', () => {
		expect(truncateText('', 3)).toBe('');
	});

	it('cuts at the last space within the limit and adds an ellipsis', () => {
		expect(truncateText('one two three four', 10)).toBe('one two...');
	});

	it('cuts mid-word when the limit holds no space', () => {
		expect(truncateText('abcdefghij', 4)).toBe('abcd...');
	});

	it('does not cut at a space in first place', () => {
		expect(truncateText(' abcdefgh', 4)).toBe('abc...');
	});

	it('defaults to a limit of 140 characters', () => {
		const word = 'a'.repeat(9);
		const text = Array(20).fill(word).join(' ');

		const result = truncateText(text);

		expect(result.endsWith('...')).toBe(true);
		expect(result.length).toBeLessThanOrEqual(143);
		expect(truncateText('x'.repeat(140))).toBe('x'.repeat(140));
	});
});
