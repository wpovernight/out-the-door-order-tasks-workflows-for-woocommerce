=== Advanced Order Manager for WooCommerce ===
Contributors: wpovernight
Tags: woocommerce, orders, order management, tasks, fulfillment
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0-beta.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A powerful order management plugin for WooCommerce that enhances the order management experience with advanced features.

== Description ==

Advanced Order Manager extends WooCommerce's order management with a flexible task system, custom order statuses, and a streamlined admin interface designed for stores that handle complex fulfillment workflows.

**Features**

* Task management system attached to orders
* Fulfillment workflow
* Custom order statuses
* REST API for tasks and order data
* Built on a layered, service-oriented architecture

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wpo-advanced-order-manager`, or install through the WordPress Plugins screen directly.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Make sure WooCommerce 8.2 or later is installed and active.

== Changelog ==

= 1.0.0-beta.2 =
* Added Kanban column management: create, rename, reorder, and delete columns from the board.
* Added task status roles to map columns to done/undone states.
* Added task creation from the column header and from the bottom of each column.
* Improved custom order status deletion with asynchronous batch processing, progress feedback, and cancellation support.
* Reworked the plugin core onto a PSR-11 service container with dependency injection.
* Standardized the REST API response format.
* Added a `max` field validation rule.
* Re-enabled the Associated Orders field in the task form for linking orders to a task.
* Raised the minimum PHP version to 8.1.
* Various UI, styling, error handling, and stability improvements.

= 1.0.0-beta.1 =
* Initial release.