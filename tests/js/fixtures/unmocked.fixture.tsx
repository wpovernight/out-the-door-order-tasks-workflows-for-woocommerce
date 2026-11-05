/* eslint-disable jest/expect-expect -- The parent test checks these intentional failures. */
import React from 'react';
import { render } from '@testing-library/react';

function FetchOnUnmount() {
	React.useEffect(
		() => () => {
			void fetch('http://localhost/unmount').catch(() => undefined);
		},
		[]
	);
	return null;
}

test('a caught unmocked request still fails the test', async () => {
	await fetch('http://localhost/direct').catch(() => undefined);
});

test('an unmocked request during unmount still fails the test', () => {
	render(<FetchOnUnmount />);
});
