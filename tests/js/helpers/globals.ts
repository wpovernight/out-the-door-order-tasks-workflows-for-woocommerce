import * as element from '@wordpress/element';
import * as hooks from '@wordpress/hooks';
import * as i18n from '@wordpress/i18n';
import * as router from 'react-router-dom';
import * as sdk from '@sdk';

type GlobalWindow = Window & {
	wp?: Record<string, unknown>;
	wpo?: { otd?: Record<string, unknown> };
};

/**
 * Put the globals WordPress and core publish on the page onto window:
 * wp.element, wp.hooks, wp.i18n, wpo.otd.sdk and wpo.otd.router.
 *
 * They are the same module instances the tests import, so code that reads a
 * global and code that imports the package share one state.
 *
 * @return A function that removes them again.
 */
export function installWpGlobals(): () => void {
	const win = window as GlobalWindow;
	const previous = { wp: win.wp, wpo: win.wpo };

	win.wp = { ...win.wp, element, hooks, i18n };
	win.wpo = { ...win.wpo, otd: { ...win.wpo?.otd, sdk, router } };

	return () => {
		win.wp = previous.wp;
		win.wpo = previous.wpo;
	};
}
