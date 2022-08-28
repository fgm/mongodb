# Bespoke code

Beyond the simple use cases covered by this standard package, most uses of
MongoDB in Drupal projects appear in enteprise-class bespoke developments. Until
this version, this usually meant totally custom code, built either straight from
the legacy `mongo` extension, the current `mongodb` extension, or on top of the
PHP Library or the Doctrine ODM for MongoDB, suffering from a total lack of
integration with the underlying core Drupal CMS.

This module provides a degree of version independence for the API changes in
[PHP library]. Refer to the `Drupal\mongodb\MongoDb` class for an example.

Starting with 8.x-2.0 vrsions,
such a one-off code can be developed on top of the base `mongodb` module:
unlike earlier releases, 8.x-2.x uses the PHP-standard connection methods and options,
without deviation, adding only a thin layer of Drupal adaptation on top of the standard
`mongodb` [extension] and [PHP library].

[extension]: http://php.net/mongodb
[PHP library]: https://docs.mongodb.com/php-library/current


## Example

The familiar Drupal alias mechanism for databases is available to provide easy,
string-referenced access to `Client` and `Database` instances through the
package-provided `ClientFactory` and `DatabaseFactory` services respectively.

Most such code is likely to be service based, so the example given below
demonstrates a service `bar` in module `foo`, using a custom `foo-database`
database aliased as `foodb`, to keep its storage separate from the main database
used by the package modules, and its logic independent of other Drupal modules.


### Per-environment settings

The site local settings file includes the alias definition, binding it to the
actual database credentials, allowing for per-environment configuration:

```php
<?php
// settings.local.php
$settings['mongodb'] = [
  'clients' => [
    // Client alias => constructor parameters.
    'default' => [
      'uri' => 'mongodb://localhost:27017',
      'uriOptions' => [],
      'driverOptions' => [],
    ],
  ],
  'databases' => [
    // Collection alias => [ client_alias, collection_name ]
    'default' => ['default', 'drupal'],
    'logger' => ['default', 'logger'],
    'foodb' => ['default', 'foo-database'],
  ],
];
```

With such a configuration, the `foodb` alias is available to all MongoDB-using
modules in the site, possibly pointing to different databases depending on the
environment (development, staging, production...).


### Service-based module adapter

The `foo.services.yml` service file for the bespoke `foo.module` can then
reference `foodb` to access the database with a constant alias, regardless
of the environment:

```yaml
# modules/custom/Foo/foo.services.yml
services:
  foo.storage:
    class: 'MongoDB\Database'
    factory: ['@mongodb.database_factory', 'get']
    arguments: ['foodb']

  foo.bar:
    class: 'Drupal\foo\Bar'
    arguments: ['@foo.storage', '@logger.channel.foo']

  foo.baz:
    class: 'Drupal\foo\Baz'
    arguments: ['@foo.storage', '@mongodb.logger']
```

This allows services in the module to access the database in both function code
for Drupal hooks, and OO code for component-level logic without having to be
environment-aware.

If the `mongodb_watchdog` module is enabled:

* the `@logger.channel.foo` logger instance passed to the `Bar` constructor will
  be a Drupal-standard `LoggerChannel` instance, dispatching events to all
  active loggers in the site. This is the service most "classic" Drupal
  applications will want to use, as it has no visible dependence on MongoDB. .
* the `@mongodb.logger` logger instance passed to the `Baz` constructor will be
  a PSR-3-standard logger only writing to MongoDB instead of logging through the
  central Drupal logging channel mechanism, but still providing the standard
  Drupal UI to examine the application logs. When using this service, the
  `type` option MUST be set in the message context to appear as a logging
  channel in the Drupal logs UI. This is the service applications written in a
  "decoupled components" style will prefer.
* In both cases, code receiving such a logger service by dependency injection
  should type-hint it to the PSR-3 `LoggerInterface`.


### Component logic

Finally, the component application logic can use the services without receiving
any Drupal-specific dependency. In this example, we can simply assume the
service code is located within the module itself, for simplicity:

```php
<?php
// modules/custom/Foo/src/Bar.php
use MongoDb\Database;
use Psr\Log\LoggerInterface;

public function __construct(Database $database, LoggerInterface $logger) {
  $this->database = $database;
  $this->logger = $logger;
}

public function baz() {
  // Perform some business logic using $this->database.
  // Log it using $this->logger.
}
```

Having the code only receive standard services (like a PSR-3 logger) or
[PHP library] classes allows it to be written as an agnostic component,
that can be brought in using Composer and shared with non-Drupal code.
This is often  useful in bespoke projects, which tend to combine Drupal 9/10
with other parts of the application written in Laravel &ge; 5 or Symfony &ge; 4,
since the code has no Drupal-specific dependency in that case,
only exposing a PSR-3 standard API.


### Tests

The `mongodb` module provides a `MongoDbTestBase` base test class allowing
kernel-based integration tests, as described on the [tests] page.

[tests]: /tests
