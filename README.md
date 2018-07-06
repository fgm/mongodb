INTRODUCTION
============

MongoDB integration for Drupal, version 8.x-2.0-dev.

[![Build Status](https://travis-ci.org/fgm/mongodb.svg?branch=8.x-2.x)](https://travis-ci.org/fgm/mongodb)
[![Coverage Status](https://coveralls.io/repos/github/fgm/mongodb/badge.svg?branch=8.x-2.x)](https://coveralls.io/github/fgm/mongodb?branch=8.x-2.x)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fgm/mongodb/badges/quality-score.png?b=8.x-2.x)](https://scrutinizer-ci.com/g/fgm/mongodb/?branch=8.x-2.x)

This package is a collection of several modules, allowing Drupal sites to store
various data in MongoDB, either using the provided modules, or writing their own
on top of the main `mongodb` module to take .

Module                | Information
----------------------|---------------------------------------------------------
mongodb               | Drupal/Drush wrapper around mongodb-php-library.
mongodb_storage       | Key-value storage in MongoDB
mongodb_watchdog      | Store logger (watchdog) messages in MongoDB.

Full documentation is available on [Github pages]

[Github pages]: https://fgm.github.io/mongodb/


Table of contents
-----------------

* Introduction
* Installation and settings
* Exportable configuration
* Writing custom code
* Databases and collections reference
* Contributing
* Legal


INSTALLATION AND SETTINGS
=========================

The MongoDB module and sub-modules need some configuration to be useful. This
guide assumes that :

* a [MongoDB][download] 3.0 to 3.6 instance is already installed, configured, and
  available to connect to from the Drupal instance.
* the site will be running [Drupal][drupal] 8.[56].x, with [Drush][drush] 8.x.
* the [mongodb][mongodb] (not [mongo][mongo]) PHP extension version 1.1.7 or
  later is installed and configured.
* PHP is version 7.[01].x. At this point, [PHP 7.2.x][php72] might not pass the
  test suite. It should be compatible by the time Drupal 8.6.0 is released.

[download]: https://www.mongodb.org/downloads
[drupal]: https://www.drupal.org/project/drupal
[drush]: https://www.drupal.org/project/drush
[php]: http://php.net/downloads.php
[mongo]: http://php.net/mongo
[mongodb]: http://php.net/mongodb
[php72]: https://www.drupal.org/node/2936045

If MongoDB 3.0 to 3.5 is installed on `localhost:27017` and `mongod` was started
with the `–httpinterface` option, you may view the web admin interface:

    http://localhost:28017/

* Download the module package, as per
  [Installing contributed modules (Drupal 8)][install]
* Copy the relevant section from the `mongodb/example.settings.local.php` to
  your `settings.local.php` file if you use one, or `settings.php` otherwise,
  and adapt it to match your MongoDB settings. These settings are used by the
  `mongodb` module to connect to your MongoDB servers, with the `default` server
   being the one started in previous steps.
  * The `clients` key contains an associative array of connection by
    connection alias, with the default connection parameters being under the
    `default` key, and additional keys allowing the use of other
    servers/clusters.
  * The `databases` key contains an associative array of server/database pairs
    by database alias, with the default Drupal database being under the
    `default` key, and additional keys allowing modules to use their own
    database to avoid stepping on each other's toes. This is especially useful
    for bespoke modules created for the needs of a specific site, which can thus
    use their own databases, possibly located on other MongoDB clusters.
    For example, with the following settings:

```php
// In sites/default/settings.local.php.
$settings['mongodb'] = [
  'clients' => [
    // Client alias => connection constructor parameters.
    'default' => [
      'uri' => 'mongodb://localhost:27017',
      'uriOptions' => [],
      'driverOptions' => [],
    ],
  ],
  'databases' => [
    // Database alias => [ client_alias, database_name ]
    'default' => ['default', 'drupal'],
    'keyvalue' => ['default', 'keyvalue'],
    'logger' => ['default', 'logger'],
  ],
];
```
  * With these settings:
    * the `default` database alias will handle collections in the `drupal`
      database on the `default` MongoDB server installed in earlier steps
    * the `keyvalue` database alias will store key-value collections on the
      same `default` MongoDB server, but in a separate `keyvalue` database.
    * the `logger` database alias will store logger collections on the same
      `default` MongoDB server, but in a separate `logger` database.
* To use the MongoDB Key-Value (Expirable) storage:
  * ensure there is a `keyvalue` database alias in `settings.local.php`, like
    in the previous lines.
  * declare MongoDB as the default keyvalue storage implementation by editing
    the existing declarations in the `sites/default/services.yml` file:

```yaml
# In sites/default/services.yml.
factory.keyvalue:
  default: keyvalue.mongodb
factory.keyvalue.expirable:
  keyvalue_expirable_default: keyvalue.expirable.mongodb
```

  * enable the `mongodb_storage` module, e.g. using `drush en mongodb_storage`.
  * import the existing Key-Value contents from the database, using the Drush
    `mongodb_storage-import-keyvalue` command: `drush most-ikv`. It will output
    the list of imported keys, for your information, like:

```yaml
key_value
  config.entity.key_store.action
    uuid:054e62b3-1c40-4f22-aa17-c092bd796ee8
    uuid:0cfd15f5-c01a-4912-991c-ad10e934f86e
(...lots of line, then...)
key_value_expire
  update_available_releases
    drupal
```

  * rebuild the container to take these changes into account using `drush cr`.

Once the module is installed and enabled, you can check its requirements on
`/admin/reports/status`:

![MongoDB on tatus page](modules/mongodb/images/mongodb-requirements.png)


COMPOSER REQUIREMENTS
---------------------

* Commands below are for those who are using composer already in your site to
  manage module dependencies. To know more about composer [here][composer].

[composer]: https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies

* At the root of your site
  * If you are using the `drupal-composer/drupal-project` skeleton, just add
    this package:

        composer require drupal/mongodb "^2.0.0"
  * Otherwise also add a composer requirement by typing:

        composer require drupal/mongodb "^2.0.0"
        composer require mongodb/mongodb "^1.2.0"
* If this is the first Composer dependency on the project, from the site root,
  run:

        composer install
* Enable the `mongodb` module. You now have access to the MongoDB services and
  Drush commands.

[install]: https://www.drupal.org/documentation/install/modules-themes/modules-8


EXPORTABLE CONFIGURATION
========================

The base `mongodb` and the `mongodb_storage` modules use no exportable
configuration, only settings and service parameters.


mongodb_watchdog
----------------

The module uses a separate database, using the `logger` database alias in
settings. Do NOT point that alias to the same database as `default`, because the
module drops the logger database when uninstalling, which would drop all your
other data with it.

* `mongodb.watchdog.items`: the maximum item limit on the capped collection used
  by the module. If not defined, it defaults to 10000. The actual (size-based)
  limit is derived from this variable, assuming 1 kiB per watchdog entry.
* `mongodb.watchdog.limit`: the maximum severity level (0 to 7, per RFC 5424) to
  save into watchdog. Errors below this level (with a higher numerical level)
  will be ignored by the module. If not defined, all events will saved.
* `mongodb.watchdog.items_per_page`: the maximum number of events displayed on
  the event details page.
* `mongodb_watchdog.request_tracking`: if true, enable the per-request event
   tracking. If not defined, it defaults to false because its cost is not
   entirely negligible. This feature requires the use of `mod_unique_id` in
   Apache 2.x
* `mongodb_watchdog.requests`: if request tracking is enabled, this setting
  defined the maximum requests limit on the capped collection used by the
  module. If not defined, it defaults to 100000. The actual (size-based) limit
  is derived from this variable, assuming 1 kiB per tracker entry.

See [Drupal\Core\Logger\RfcLogLevel][levels] and [Psr\Log\LogLevel][levelnames]
for further information about severity levels.

[levels]: https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Logger%21RfcLogLevel.php/class/RfcLogLevel/8.2.x
[levelnames]: https://api.drupal.org/api/drupal/vendor%21psr%21log%21Psr%21Log%21LogLevel.php/class/LogLevel/8.2.x


WRITING CUSTOM CODE
===================

To write your own custom modules on top of MongoDB while avoiding compatibility
issues with this suite, you probably want to write on top of the `mongodb`
module, using the `mongodb.client_factory` and/or `mongodb.database_factory`
services instead of accessing the PHP library or MongoDB extension directly.

Using module-specific database aliases will allow your module to take advantage
of the features provided by the `mongodb` module while keeping your custom data
in an entirely separate database.


DATABASES AND COLLECTIONS REFERENCE
===================================

Module              | DB alias   | Collection(s)      | Information
--------------------|------------|--------------------|--------------------------
`mongodb`           | `default`  | (none)             | Alias/client consistency
`mongodb_storage`   | `keyvalue` | `kve_*`            | Expirable collections
&uarr;              | &uarr;     | `kvp_*`            | Persistent collections
`mongodb_watchdog`  | `logger`   | `watchdog`         | Event types
&uarr;              | &uarr;     | `watchdog_tracker` | Requests (capped)
&uarr;              | &uarr;     | `watchdog_*`       | Events (capped)

Earlier versions used to support a collection aliasing mechanism. With this
version generalizing dedicated databases per module, this is no longer needed
and the associated machinery has been removed.


CONTRIBUTING
============

Starting with 8.x-2.0-alpha1, use the drupal.org issue queue for issue
discussion, but send pull requests on [Github] rather than drupal.org patches.

[Github]: https://github.com/fgm/mongodb

Since the project also tracks obsolete module versions like 6.x-1.x and 8.x-1.x,
use this URL to issues for supported branches and components: https://goo.gl/5KrYkG


LEGAL
=====

Like any Drupal module, this package is licensed under the [General Public
License, version 2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
or later.

* Drupal is a registered trademark of Dries Buytaert.
* Github is a registered trademark of GitHub, Inc.
* Mongo, MongoDB and the MongoDB  leaf logo are registered trademarks of
  MongoDB, Inc.
