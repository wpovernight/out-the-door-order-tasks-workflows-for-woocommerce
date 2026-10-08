# Testing

The plugin has two PHPUnit 9.6 suites:

| Suite | Config | Runs against | Use it for |
|---|---|---|---|
| **Unit** | `phpunit-unit.xml` | Plain PHP. WordPress functions are stubbed with [Brain Monkey](https://giuseppe-mazzapica.gitbook.io/brain-monkey/), objects mocked with Mockery. | Pure logic: models, enums, the container and kernel, the query builder's SQL, validation rules, formatting. |
| **Integration** | `phpunit.xml.dist` | WordPress + WooCommerce + MySQL, through [`wp-phpunit/wp-phpunit`](https://github.com/wp-phpunit/wp-phpunit). | Anything that touches the database, WooCommerce orders, hooks, REST routes, emails or admin screens. |

## Running the tests

```bash
composer install

composer test               # both suites
composer test:unit          # unit only (about a second)
composer test:integration   # integration only (about 15 seconds)

vendor/bin/phpunit -c phpunit-unit.xml tests/Unit/Models/TaskTest.php
vendor/bin/phpunit -c phpunit.xml.dist --filter test_moving_between_two_tasks
```

Both suites run in random order. PHPUnit prints the seed it used; re-run a failing order with `--random-order-seed=<seed>`.

### What the integration suite needs

**PHP 8.1+ with `mysqli`, and a MySQL/MariaDB server.** The suite creates its database (`wpo_otd_tests`) on first use.

**WordPress and WooCommerce**, from one of:

1. **Your dev site.** When this plugin sits in a site's `wp-content/plugins/` next to WooCommerce, the suite boots that site's WordPress (against its own database, so the site's data is never touched). Nothing to install.
2. **A standalone copy.** Anywhere else, for example a plain clone, run once:

   ```bash
   composer test:install
   ```

   This downloads WordPress (the version matching the test library) and the latest WooCommerce into `build/wordpress/`. Set `WOOCOMMERCE_VERSION=9.8.5` to pin WooCommerce, and pass `-- --force` to download again.

The bootstrap prints which one it uses: `WordPress 7.1.1 (standalone copy), WooCommerce 11.2.0, orders in posts.`

### Settings

Everything is read from environment variables (see `tests/environment.php`):

| Variable | Default | |
|---|---|---|
| `WP_TESTS_DB_NAME` | `wpo_otd_tests` | Must contain "test": the suite empties it on every run. |
| `WP_TESTS_DB_USER` | `root` | |
| `WP_TESTS_DB_PASSWORD` | *(empty)* | |
| `WP_TESTS_DB_HOST` | `127.0.0.1` | `host:port` and `host:/socket` work too. |
| `WP_TESTS_STANDALONE` | off | `1` uses the standalone copy even inside a site. |
| `WP_TESTS_ABSPATH` | | Use the WordPress install at this path, with WooCommerce in its `wp-content/plugins/`. |
| `WP_TESTS_HPOS` | off | `1` stores orders in WooCommerce's HPOS tables instead of posts. |

For example, on a machine with a MySQL password: `WP_TESTS_DB_PASSWORD=secret composer test:integration`.

### Safety checks

The bootstrap stops with a message when:

- the database name does not contain "test";
- another test run is using the same database (a named lock is held for the whole run, so two runs cannot wipe each other's tables);
- the dev site has a `wp-content/db.php` or `object-cache.php` drop-in, which would load into the tests (use `WP_TESTS_STANDALONE=1` instead);
- WordPress or WooCommerce is missing.

Every run starts from an empty database: all tables with the test prefix are dropped, then WordPress is installed and WooCommerce and the plugin are activated for real. External HTTP is blocked and the site's must-use plugins are left out.

## Coverage

```bash
composer test:coverage
```

This runs both suites with coverage and merges them with `phpcov` into `build/coverage/` (an HTML report in `build/coverage/html/` and `clover.xml`), printing a summary at the end. It needs a coverage driver, Xdebug or PCOV, in the PHP that runs Composer. With Laragon, PHP 8.2 ships Xdebug:

```bash
XDEBUG_MODE=coverage C:/laragon/bin/php/php-8.2.11-Win32-vs16-x64/php.exe C:/composer/composer.phar test:coverage
```

`includes/CLI/` and `CliServiceProvider` are excluded from coverage: they are WP-CLI dev tooling and are not shipped.

## Layout

```
tests/
├── bootstrap-unit.php          Unit bootstrap: autoloader and the constants plugin files expect.
├── bootstrap-integration.php   Prepares the database, boots WordPress with WooCommerce and the plugin, activates both.
├── environment.php             Where WordPress, WooCommerce and the database are (environment variables).
├── wp-tests-config.php         WordPress test-suite configuration, built from environment.php.
├── TestCase.php                Base class for unit tests.
├── IntegrationTestCase.php     Base class for integration tests.
├── bin/install-wordpress.php   `composer test:install`.
├── Support/                    Helpers for the integration bootstrap.
├── Unit/                       Mirrors includes/.
└── Integration/                Mirrors includes/; REST/RestTestCase.php is the base for REST tests.
```

## Writing unit tests

Extend `WPO\OTD\Tests\TestCase`. It starts Brain Monkey and stubs the translation, escaping, `absint()` and `sanitize_title()` functions for every test; stub anything else a test touches:

```php
use Brain\Monkey\Functions;

Functions\when( 'get_option' )->justReturn( array( 'view' => 'list' ) );
Functions\expect( 'update_option' )->once()->with( 'wpo_otd_task_manager_settings', Mockery::type( 'array' ) );
```

`set_static_property()` resets singletons and static caches (`Logger`, `RepositoryRegistry`, `BaseRepository`); reset them in `tearDown()` too.

Exceptions thrown with `esc_html()` messages carry HTML entities (`&quot;`), as they do in WordPress.

## Writing integration tests

Extend `WPO\OTD\Tests\IntegrationTestCase` (a `WP_UnitTestCase`).

**Isolation.** WordPress runs each test in a transaction and rolls it back. The plugin's repositories open their own transactions, and in MySQL a new `START TRANSACTION` would commit WordPress's. While a test runs, the base class rewrites the plugin's `START TRANSACTION` / `BEGIN` / `COMMIT` / `ROLLBACK` into savepoints, so the plugin keeps its all-or-nothing behavior and everything a test writes, including WooCommerce orders and options, is still rolled back. Any other statement that would commit the test's transaction (a stray `COMMIT`, `ALTER`, `TRUNCATE`, `SET autocommit`, ...) fails the test with an explanation instead of silently leaking data into later tests. The repositories' static model caches are emptied around every test.

**Services.** Resolve services with `$this->make( SomeService::class )`. It uses a container that is fresh for each test, so no settings or status lists cached by an earlier test leak in. The plugin's own container lives for the whole run, as do the hooks and REST routes it registered at boot.

**Helpers.**

| Helper | Does |
|---|---|
| `status_option_id( 'done' )`, `priority_option_id( 'high' )`, `option_id( $field, $slug )` | IDs of seeded options. Never hard-code IDs: rolled-back inserts still consume auto-increment values. |
| `field_value( $task_id, $field_id )`, `field_values(...)`, `count_rows( 'tasks' )`, `table( 'tasks' )` | Read what is stored. |
| `create_order( array( 2, 1 ) )` | An order with one product line per quantity. |
| `act_as( 'shop_manager' )` | Log in as a new user with that role. |
| `fail_queries( '/^INSERT INTO `\w+wpo_otd_tasks`/' )` | Make matching queries fail, as if the database rejected them. |
| `throw_on_queries( '/wpo_otd_tasks\b/', new \LogicException() )` | Throw from `$wpdb` on matching queries. |
| `allow_real_ddl()` | Let a test run real DDL, which commits. The test must restore the database itself (see `InstallerTest`). |

**REST.** Extend `Integration\REST\RestTestCase`. It registers the routes with controllers from the test's container on a fresh REST server and logs in as a shop manager. Use `$this->request( 'POST', '/tasks', $body )`, `$this->json( $response )` and `$this->assertResponse( 400, $response, 'invalid_data' )`.

**AJAX.** See `Integration\Admin\OrderEditAjaxTest`, which extends `WP_Ajax_UnitTestCase`.

**Order storage.** Tests must pass with orders in posts and in HPOS tables; CI runs both. Run locally with `WP_TESTS_HPOS=1` when touching order code.

**Gotchas.**
- Values passed through `wp_localize_script()` arrive as strings when they are top-level scalars.
- Constructing a `WC_Email` hooks its `trigger()`; build test data first, then the email.
- Tests marked incomplete document known bugs. Remove the `markTestIncomplete()` call once the bug is fixed.

## Continuous integration

`.github/workflows/tests.yml` runs on pull requests that touch PHP, Composer or test files:

- **Unit** on PHP 8.1–8.5.
- **Integration** on PHP 8.1 and 8.5, each with orders in posts and in HPOS tables, against MySQL 8.0 and the standalone copy from `composer test:install`.
- **Coverage** on PHP 8.3 with PCOV. The summary goes to the job summary, and the HTML report is uploaded as the `coverage-html` artifact.
