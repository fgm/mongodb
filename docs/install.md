# Installation and Settings
## Prerequisites

The MongoDB module and sub-modules need some configuration to be useful. This
guide assumes that :

* a [MongoDB][download] 3.0 to 4.0 server instance is already installed,
  configured and available for connection from the Drupal instance.
* the site will be running [Drupal][drupal] 8.[56].x, with [Drush][drush] 
  8.x[^1].
* the [mongodb][mongodb] (not [mongo][mongo]) PHP extension version 1.1.7 or
  later is installed and configured.
* PHP is version 7.0.x to 7.2.x.

[^1]: There is a [plan][drush9] to support Drush 9.x.

* We recommend [using Composer](#installing-using-composer) for installing this
  module.

Check out the [MongoDB extension and library for PHP][PHPMongoDBext]

Installing MongoDB itself is best explained in these official resources
maintained by MongoDB Inc.:

   * [MongoDB Mac installation][MongoDBMac]
   * [MongoDB LINUX installation][MongoDBLinux]
   * [MongoDB Windows installation][MongoDBWindows]

[download]: https://www.mongodb.org/downloads
[drupal]: https://www.drupal.org/project/drupal
[drush]: https://www.drupal.org/project/drush
[php]: http://php.net/downloads.php
[mongo]: http://php.net/mongo
[mongodb]: http://php.net/mongodb
[drush9]: https://www.drupal.org/project/mongodb/issues/2986785
[PHPMongoDBext]: http://php.net/mongodb
[MongoDBMac]: https://docs.mongodb.com/manual/tutorial/install-mongodb-on-os-x/
[MongoDBLinux]: https://docs.mongodb.com/manual/administration/install-on-linux/
[MongoDBWindows]: https://docs.mongodb.com/manual/tutorial/install-mongodb-on-windows/
[removedhttp]: https://docs.mongodb.com/manual/release-notes/3.6-compatibility/#http-interface-and-rest-api

If MongoDB 3.0 to 3.5[^2] is installed on `localhost:27017` and the `mongod`
server was started with the `–httpinterface` option, you may view the web admin
interface:

    http://localhost:28017/

[^2]: This option is [deprecated from 3.6 version][removedhttp].


## Settings Configuration

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

Once the module is installed and enabled, you can check its requirements on
`/admin/reports/status`:

![MongoDB on status page](images/mongodb-requirements.png)


## Installing using Composer

* This section is applicable if you are already using [Composer][composer] in
your site to manage module dependencies.

[composer]: https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies

  * At the root of your site
    * If you are using the `drupal-composer/drupal-project` skeleton, just add
      this package:

         `composer require drupal/mongodb "^2.0.0"`

    * Otherwise also add a composer requirement by typing:

        `composer require drupal/mongodb "^2.0.0"`
        `composer require mongodb/mongodb "^1.2.0"`

  * Enable the `mongodb` module. You now have access to the MongoDB services and
  Drush commands for the `mongodb` module.

[install]: https://www.drupal.org/documentation/install/modules-themes/modules-8

Note that there is currently a bug with Composer-based deployment from
packages.drupal.org/8 :
[#2985860: packages.drupal.org/8 serves incorrect composer.json for module mongodb][composer issue].

The workaround is:

* either require `mongodb/mongodb` directly in the root `composer.json` of the
  project,
* or add this JSON fragment in the root `composer.json` to fetch the code from
  Github instead of Drupal:

```json
      "repositories": {
          "type": "vcs",
          "url": "https://github.com/fgm/mongodb.git"
      },
      "require": {
          "drupal/mongodb":"dev-8.x-2.x"
      }
```
[composer issue]: https://www.drupal.org/project/project_composer/issues/2985860
