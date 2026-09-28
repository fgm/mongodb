# PHPUnit

The newly ported modules have some test coverage, which can be checked with
PHPUnit.

[![Coverage Status](https://coveralls.io/repos/github/fgm/mongodb/badge.svg?branch=8.x-2.x)](https://coveralls.io/github/fgm/mongodb?branch=8.x-2.x)


## Writing custom tests

The `mongodb` module provides a `Drupal\Tests\mongodb\Kernel\MongoDbTestBase`
base class on which to build custom kernel tests for [bespoke] modules, as it
provides a per-test database created during test <code>setUp()</code>, and
dropped during <code>tearDown()</code>.

The base class is documented on the [mongodb] documentation page.


## Complete test example

This example show how to write a test using a custom `foo` database for the
eponymous module `foo`, assuming individual tests do not drop the database
instance themselves.

```php
<?php

namespace Drupal\Tests\foo\Kernel;

use Drupal\mongodb\ClientFactory;
use Drupal\mongodb\DatabaseFactory;
use Drupal\mongodb\MongoDb;
use Drupal\Tests\mongodb\Kernel\MongoDbTestBase;
use MongoDB\Database;

/**
 * @coversDefaultClass \Drupal\foo\Foo
 *
 * @group foo
 */
class FooTest extends MongoDbTestBase {

  const DB_FOO_ALIAS = 'foo';

  protected static $modules = [
    MongoDb::MODULE,
    'foo',
  ];

  /**
   * The test database.
   */
  protected ?Database $database = NULL;

  /**
   * Add the custom test database alias to the base class settings.
   */
  protected function getSettingsArray(): array {
    $settings = parent::getSettingsArray();
    $settings['databases'][static::DB_FOO_ALIAS] = [
      static::CLIENT_TEST_ALIAS,
      $this->getTestDatabaseName('foo'),
    ];

    return $settings;
  }

  /**
   * Instantiate the custom database.
   *
   * If the tests do not need a specific database, no setUp()/tearDown() is
   * even needed.
   */
  public function setUp(): void {
    parent::setUp();
    $this->database = (new DatabaseFactory(
      new ClientFactory($this->settings),
      $this->settings
    ))->get(static::DB_FOO_ALIAS);
  }

  /**
   * Drop the custom database.
   *
   * The base class only drops the default test database.
   */
  public function tearDown(): void {
    $this->database?->drop();
    parent::tearDown();
  }

  /**
   * @covers ::whatever
   */
  public function testWhatever() {
    // ... custom test logic...
  }

}
```

In most cases, modules implementing will implement multiple classes, hence have
multiple tests, in which case having a per-module base test class will be
recommended. See `mongodb_storage` or `mongodb_watchdog` tests for examples.

[bespoke]: bespoke.md
[mongodb]: modules/mongodb.md


## Running tests

Tests are run from the PHPUnit command line.

Known issue in 8.x-2.1: on Drupal 10.2 and later,
the functional test `ControllerTest::testLoggerReportsAccess` fails with a 403 on `/admin/help`,
because Drupal 10.2 added the `access help pages` permission, which its test user lacks.
This only affects the test, not sites: Drupal grants the new permission
to existing roles having `access administration pages` when updating to 10.2.
The fix is planned for 8.x-2.2 in [#3625939].

[#3625939]: https://www.drupal.org/project/mongodb/issues/3625939

### Running directly

The typical full command to run tests looks like the next example (`\` is to
avoid too long a line). Assuming a `composer-project` deployment with Drupal in
the `web/` directory, you'll need to run phpunit from the Drupal root, not the
project root:

```bash
cd web
SIMPLETEST_BASE_URL=http://localhost                \
BROWSERTEST_OUTPUT_DIRECTORY=/some/writable/pre-existing/path \
SIMPLETEST_DB=mysql://user:pass@localhost/drupal10  \
MONGODB_URI=mongodb://somemongohost:27017           \
../vendor/bin/phpunit -c $PWD/core/phpunit.xml.dist \
    -v --debug --coverage-clover=/tmp/cover.xml     \
    modules/contrib/mongodb
```

* Functional tests: the `SIMPLETEST_BASE_URL` and `BROWSERTEST_OUTPUT_DIRECTORY`
  variables are needed. Kernel and Unit tests do not need them.
* Optional: `MONGODB_URI` points to a working MongoDB instance. If it is not
  provided, the tests will default to `mongodb://localhost:27017`.

These variables can also be set in the `core/phpunit.xml` custom configuration
file to simplify the command line, as described on Drupal.org [Running PHPUnit tests]
page.

For functional tests to be more apt to catch some URL resolution issues,
your test site should be using a subpath, i.e.:

- Good `http://drupaltest.localhost/somepath`
- Not so good: `http://drupaltest.localhost`

[Running PHPUnit tests]: https://www.drupal.org/docs/automated-testing/phpunit-in-drupal/running-phpunit-tests


### Using a `phpunit.xml` configuration file

The test command can also be simplified using a `phpunit.xml` configuration file:

```bash
phpunit -c core/phpunit.xml
```

Or to generate a coverage report:

```bash
phpunit -c core/phpunit.xml --coverage-html=/some/coverage/path modules/contrib/mongodb
```

In this syntax, `core/phpunit.xml` is a local copy of the default
`mongodb/core.phpunit.xml` configuration file, tweaked for the local
environment.


### Reporting coverage

Coverage is reported to [Coveralls] from local runs,
using the [Coveralls coverage reporter][reporter], e.g. `brew install coverallsapp/coveralls/coveralls`.

1. From the Drupal root, run the tests with a Clover coverage report
   written into the module checkout:
   ```bash
   XDEBUG_MODE=coverage phpunit -c core/phpunit.xml \
       --coverage-clover=modules/contrib/mongodb/coverage.clover \
       modules/contrib/mongodb
   ```
2. From the module checkout, make the file paths relative to it,
   so that Coveralls can match them with the repository:
   ```bash
   cd modules/contrib/mongodb
   sed "s#$(pwd -P)/##g" coverage.clover > coverage.rel.clover
   ```
3. Send the report, with the repository token in `COVERALLS_REPO_TOKEN`:
   ```bash
   coveralls report coverage.rel.clover --format=clover --branch=8.x-2.x
   ```
   The reporter reads the commit from the checkout, and the branch too,
   so `--branch` is only needed when the checkout is on a tag or a detached commit.

When the module is symlinked into the site, PHPUnit records its files under their real path,
not under `modules/contrib/mongodb`:
the `<coverage>` filter in `core/phpunit.xml` must name that real path,
or the report comes out empty.
`pwd -P` in step 2 already resolves it.

Both Clover files are ignored by Git.

[Coveralls]: https://coveralls.io/github/fgm/mongodb
[reporter]: https://github.com/coverallsapp/coverage-reporter
