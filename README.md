# Out the Door – Order Tasks & Workflows for WooCommerce

A WordPress plugin that extends WooCommerce's order management with advanced features, such as a task system, custom order statuses, and a modern admin UI.

## Requirements

- PHP 8.1+
- WordPress 6.7+
- WooCommerce 8.2+

## Development

```bash
# PHP dependencies
composer install

# Frontend (React + TypeScript)
npm install
npm run dev      # watch mode
npm run build    # production build
```

### WP-CLI

The plugin registers commands under the `wp otd` namespace:

```bash
wp otd install              # Install DB tables and default data
wp otd tasks generate       # Generate sample tasks (dev/testing)
wp otd tasks remove         # Remove all tasks and their field values
wp otd fulfillments clear   # Remove all fulfillment data
wp otd options clear        # Remove all plugin wp_options entries
```

## License

GPLv3 — see [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html).