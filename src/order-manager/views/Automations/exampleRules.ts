import { __ } from '@wordpress/i18n';

export interface ExampleRule {
	id: string;
	title: string;
	trigger: string;
	conditions: string[];
	actions: string[];
	/** Actions the rule has beyond those shown. */
	moreActions: number;
}

/**
 * The example rules the preview shows, each something Pro can do.
 *
 * Built on call, so the strings are translated once translations are loaded.
 *
 * @return The example rules, in display order.
 */
export const getExampleRules = (): ExampleRule[] => [
	{
		id: 'client-orders',
		title: __(
			'Flag orders from one client',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		trigger: __(
			'Order created',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		conditions: [
			__(
				'Company is "School A"',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
		],
		actions: [
			__(
				'Add note: School A order',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
		],
		moreActions: 0,
	},
	{
		id: 'embroidery',
		title: __(
			'Route embroidery to the right team',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		trigger: __(
			'Order created',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		conditions: [
			__(
				'Any item in category Embroidery',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
		],
		actions: [
			__(
				'Create task: Embroidery',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
		],
		moreActions: 1,
	},
	{
		id: 'board-status',
		title: __(
			'Keep order status in step with your board',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		trigger: __(
			'Task moved to In progress',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		conditions: [],
		actions: [
			__(
				'Set order status: Processing',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
		],
		moreActions: 0,
	},
];
