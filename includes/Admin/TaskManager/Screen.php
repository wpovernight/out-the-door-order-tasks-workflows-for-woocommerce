<?php

namespace WPO\AOM\Admin\TaskManager;

defined( 'ABSPATH' ) || exit;

final class Screen {
	/**
	 * Register settings tab and actions.
	 *
	 * @return void
	 */
	public function register(): void {
		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add a sub menu item under WooCommerce settings.
		add_action( 'admin_menu', array( $this, 'add_settings_tab' ), 99 );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'woocommerce_page_wpo_aom_task_manager' !== $screen->id ) {
			return;
		}

		wp_enqueue_script(
			'wpo-aom-admin-task-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/task-manager.js',
			array( 'wp-element', 'wp-components' ),
			WPO_AOM_VERSION,
			true
		);

		wp_localize_script(
			'wpo-aom-admin-task-manager',
			'WPO_AOM_TaskManager',
			array(
				'apiRoot'      => esc_url_raw( rest_url( '/wc/v3' ) ),
				'apiNamespace' => 'wpo/aom',
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'i18n'         => array(
					'loading'          => esc_html__( 'Loading...', 'wpo-aom' ),
					'errorLoading'     => esc_html__( 'Error loading tasks. Please try again.', 'wpo-aom' ),
					'confirmationText' => esc_html__( 'Are you sure?' ),
					'views'            => array(
						'kanban'   => esc_html__( 'Kanban', 'wpo-aom' ),
						'calendar' => esc_html__( 'Calendar', 'wpo-aom' ),
					),
					'kanban'           => array(
						'addTask'  => esc_html__( 'Add Task', 'wpo-aom' ),
						'editTask' => esc_html__( 'Edit Task', 'wpo-aom' ),
						'options'  => esc_html__( 'Options', 'wpo-aom' ),
						'create'   => esc_html__( 'Create', 'wpo-aom' ),
					),
					'calendar'         => array(
						'task'             => esc_html__( 'Task', 'wpo-aom' ),
						'tasks'            => esc_html__( 'Tasks', 'wpo-aom' ),
						'priority'         => esc_html__( 'Priority', 'wpo-aom' ),
						'status'           => esc_html__( 'Status', 'wpo-aom' ),
						'dueDate'          => esc_html__( 'Due date', 'wpo-aom' ),
						'description'      => esc_html__( 'Description', 'wpo-aom' ),
						'noTasksFound'     => esc_html__( 'No tasks found for the selected date range', 'wpo-aom' ),
						'viewModes'        => array(
							'byDay'   => esc_html__( 'By day', 'wpo-aom' ),
							'byWeek'  => esc_html__( 'By week', 'wpo-aom' ),
							'byMonth' => esc_html__( 'By month', 'wpo-aom' ),
						),
						'dateRangePresets' => array(
							'today'        => esc_html__( 'Today', 'wpo-aom' ),
							'tomorrow'     => esc_html__( 'Tomorrow', 'wpo-aom' ),
							'yesterday'    => esc_html__( 'Yesterday', 'wpo-aom' ),
							'currentWeek'  => esc_html__( 'Current Week', 'wpo-aom' ),
							'nextWeek'     => esc_html__( 'Next Week', 'wpo-aom' ),
							'lastWeek'     => esc_html__( 'Last Week', 'wpo-aom' ),
							'currentMonth' => esc_html__( 'Current Month', 'wpo-aom' ),
							'nextMonth'    => esc_html__( 'Next Month', 'wpo-aom' ),
							'lastMonth'    => esc_html__( 'Last Month', 'wpo-aom' ),
							'custom'       => esc_html__( 'Custom', 'wpo-aom' ),
						),
						'selectDate'       => esc_html__( 'Select a date', 'wpo-aom' ),
						'previousMonth'    => esc_html__( 'Previous month', 'wpo-aom' ),
						'nextMonth'        => esc_html__( 'Next month', 'wpo-aom' ),
					),
					'form'             => array(
						'labels'       => array(
							'status'           => esc_html__( 'Status', 'wpo-aom' ),
							'priority'         => esc_html__( 'Priority', 'wpo-aom' ),
							'dueDate'          => esc_html__( 'Due Date', 'wpo-aom' ),
							'title'            => esc_html__( 'Title', 'wpo-aom' ),
							'description'      => esc_html__( 'Description', 'wpo-aom' ),
							'associatedOrders' => esc_html__( 'Associated Orders', 'wpo-aom' ),
						),
						'placeholders' => array(
							'select'          => esc_html__( 'Select', 'wpo-aom' ),
							'taskName'        => esc_html__( 'Write a name for your task.', 'wpo-aom' ),
							'taskDescription' => esc_html__( 'Describe the task.', 'wpo-aom' ),
							'searchOrders'    => esc_html__( 'Search orders by number, customer, address...', 'wpo-aom' ),
						),
					),
					'actions'          => array(
						'edit'           => esc_html__( 'Edit', 'wpo-aom' ),
						'editTask'       => esc_html__( 'Edit task', 'wpo-aom' ),
						'delete'         => esc_html__( 'Delete', 'wpo-aom' ),
						'deleteTask'     => esc_html__( 'Delete task', 'wpo-aom' ),
						'cancel'         => esc_html__( 'Cancel', 'wpo-aom' ),
						'clear'          => esc_html__( 'Clear', 'wpo-aom' ),
						'apply'          => esc_html__( 'Apply', 'wpo-aom' ),
						'actions'        => esc_html__( 'Actions', 'wpo-aom' ),
						'createTask'     => esc_html__( 'Create Task', 'wpo-aom' ),
						'updateTask'     => esc_html__( 'Update Task', 'wpo-aom' ),
						'markFinished'   => esc_html__( 'Mark as Completed', 'wpo-aom' ), // ToDo: Finished status should be dynamic.
						'markUnfinished' => esc_html__( 'Mark as In Progress', 'wpo-aom' ), // ToDo: Finished status should be dynamic.
					),
				),
			)
		);

		wp_enqueue_style(
			'wpo-aom-admin-common',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/common.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-card',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/task-card.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-sidebar-modal',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/sidebar-modal.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/task-manager.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-skeleton',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/skeleton.css',
			array(),
			WPO_AOM_VERSION
		);
	}

	/**
	 * Add settings tab under WooCommerce menu.
	 *
	 * @return void
	 */
	public function add_settings_tab(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Task Management', 'wpo-aom' ),
			__( 'Task Management', 'wpo-aom' ),
			'manage_woocommerce',
			'wpo_aom_task_manager',
			array( $this, 'render_settings_tab' )
		);
	}

	/**
	 * Render settings tab content.
	 *
	 * @return void
	 */
	public function render_settings_tab(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div id="wpo-aom-task-management">
			<h1>', esc_html__( 'Task Management', 'wpo-aom' ), '</h1>
			<div id="wpo-aom-task-manager-container"></div>
			</div>';
	}
}
