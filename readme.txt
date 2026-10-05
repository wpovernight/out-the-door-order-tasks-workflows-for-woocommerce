=== Out the Door – Order Tasks & Workflows for WooCommerce ===
Contributors: wpovernight
Tags: woocommerce, orders, order management, tasks, fulfillment
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 8.2
WC tested up to: 11.1
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A powerful order management plugin for WooCommerce that enhances the order management experience with advanced features.

== Description ==

Out the Door extends WooCommerce's order management with a flexible task system, custom order statuses, and a streamlined admin interface designed for stores that handle complex fulfillment workflows.

**Features**

* Task management system attached to orders
* Fulfillment workflow with a partial fulfillments overview
* Custom order statuses
* Email notifications when tasks are created or updated
* REST API for tasks and order data

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/out-the-door-order-tasks-workflows-for-woocommerce`, or install through the WordPress Plugins screen directly.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Make sure WooCommerce 8.2 or later is installed and active.

== Where is the source code? ==

All PHP ships as human-readable source. The admin interface is written in React/TypeScript and compiled with webpack. The complete, unminified source and the build tooling are available in the public repository at https://github.com/wpovernight/out-the-door-order-tasks-workflows-for-woocommerce.

To build the compiled assets from source:

`npm install`
`npm run build`

This regenerates the files in `assets/js/`. See the repository's readme for full development instructions.

== Changelog ==

= 1.0.0 =
* Initial release.