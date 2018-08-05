The newly ported modules have some test coverage, which can be checked with
PHPUnit.

[![Coverage Status](https://coveralls.io/repos/github/fgm/mongodb/badge.svg?branch=8.x-2.x)](https://coveralls.io/github/fgm/mongodb?branch=8.x-2.x)

# PHPUnit
## Writing custom tests

The `mongodb` module provides a `Drupal\Tests\mongodb\Kernel\MongoDbTestBase` base
class on which to build custom kernel tests for [bespoke] modules, as
it provides a per-test database created during test <code>setUp()</code>, and
dropped during <code>tearDown()</code>.

The base class is documented on the [mongodb] documentation page.


## Complete test example

This example show how to write a test using a custom `foo` database for the
eponymous module `foo`, assuming individual tests do not drop the database
instance themselves.

    <?php

    namespace Drupal\foo\Tests;

    use Drupal\mongodb\Tests\MongoDbTestBase;

    /**
     * @coversDefaultClass \Drupal\foo\Foo
     * @group foo
     */
    class FooTest extends MongoDbTestBase {
      const MODULE = 'foo';

      /**
       * The test database.
       */
      protected $database;

      /**
       * Override getSettingsArray method to include custom test database
       */
      public function getSettingsArray(): array {
        $settings = parent::getSettingsArray();
        $this->settings['databases'][static::MODULE] = [
          static::CLIENT_TEST_ALIAS,
          $this->getTestDatabaseName(static::MODULE),
        ];
      }

      /**
       * Add a custom alias to settings and instantiate a custom database.
       */
      public function setUp() {
        parent::setUp();
        $this->database = new DatabaseFactory(
          new ClientFactory($this->settings),
          $this->settings
        )->get(static::MODULE);
      }

      /**
       * Drop the custom database.
       */
      public function tearDown() {
        $this->database->drop();
        parent::tearDown();
      }

      /**
       * @covers ::whatever
       */
      public function testWhatever() {
        // ... custom test logic...
      }

    }


[bespoke]: /bespoke
[mongodb]: /modules/mongodb


## Running tests

With the Simpletest UI apparently going away in Drupal 8.2 (cf [#2566767],
[#2750461]), tests should be run from the command line.

[#2566767]: https://www.drupal.org/node/2566767
[#2750461]: https://www.drupal.org/node/2750461

### Running directly

The typical full command to run tests looks like this (`\` is to avoid too long a line):

    MONGODB_URI=mongodb://somemongohost:27017 \
    PHPUNIT_OPTS="-c phpunit.xml -v --debug --coverage-clover=modules/contrib/$MODULE_NAME/$COVERAGE_FILE"

* Optional: `MONGODB_URI` points to a working MongoDB instance. This variable is optional:
  if it is not provided, the tests will default to `mongodb://localhost:27017`.

Above variable can be set in the `core/phpunit.xml` custom configuration file.


### Using a `phpunit.xml` configuration file

The test command can also be simplified using a `phpunit.xml` configuration file:

    phpunit -c core/phpunit.xml

Or to generate a coverage report:

    phpunit -c core/phpunit.xml --coverage-html=/some/coverage/path modules/contrib/mongodb

* `core/phpunit.xml` is a local copy of the default `core/phpunit.xml.dist`
  configuration file, tweaked to only test the minimum set of files needed by
  the test suite.
* It can look like this, to obtain a coverage report not including the whole
  Drupal tree, but just the MongoDB package itself:

        <?xml version="1.0" encoding="UTF-8"?>

        <phpunit ...snip...>
          <php>
            <env name="MONGODB_URI" value="mongodb://somemongohost:27017" />
          </php>
          <testsuites ...snip...>...snip...</testsuites>
          <listeners>...snip...</listener>
          </listeners>
          <filter>
            <whitelist
              addUncoveredFilesFromWhitelist="true"
              processUncoveredFilesFromWhitelist="true">
              <directory>../modules/contrib/mongodb</directory>
              <!-- By definition test classes have no tests. -->
              <exclude>
                <file>../modules/contrib/mongodb/example.settings.local.php</file>
                <directory suffix="Test.php">../modules/contrib/mongodb</directory>
                <directory suffix="TestBase.php">../modules/contrib/mongodb</directory>
                <!-- There is a remaining legacy test for reference in watchdog for now -->
                <directory suffix=".test">../modules/contrib/mongodb</directory>
              </exclude>
            </whitelist>
          </filter>
        </phpunit>
