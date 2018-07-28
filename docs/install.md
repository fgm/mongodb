# Installation and Settings
## Prerequisites
The MongoDB module and sub-modules need some configuration to be useful.
This guide assumes that :

* a [MongoDB][download] 3.0 to 4.0 server instance is already installed,
  configured and available to connect to from the Drupal instance.
* the site will be running [Drupal][drupal] 8.[56].x, with [Drush][drush] 8.x.
* the [mongodb][mongodb] (not [mongo][mongo]) PHP extension version 1.1.7 or
  later is installed and configured.
* PHP is version 7.[01].x. At this point, [PHP 7.2.x][php72] might not pass the
  test suite. It should be compatible by the time Drupal 8.6.0 is released.

**NOTE** : There is a plan to support Drush 9.x and it can be tracked [here][drush9]

* In a nutshell, any MongoDB server >=3.6, PHP extension >= 1.3.4,
  MongoDB PHP library >= 1.2.0 and PHP version >= 7.1.
* We recommend using composer for installing this module.
* For details refer [below](#composer-requirements).

For more details about MongoDB extension and library for PHP check [here][PHPMongoDBext]

Some more links for you:

   * [MongoDB Mac installation][MongoDBMac]
   * [MongoDB LINUX installation][MongoDBLinux]
   * [MongoDB Windows installation][MongoDBWindows]

[download]: https://www.mongodb.org/downloads
[drupal]: https://www.drupal.org/project/drupal
[drush]: https://www.drupal.org/project/drush
[php]: http://php.net/downloads.php
[mongo]: http://php.net/mongo
[mongodb]: http://php.net/mongodb
[php72]: https://www.drupal.org/node/2936045
[drush9]: https://www.drupal.org/project/mongodb/issues/2986785
[PHPMongoDBext]: http://php.net/mongodb
[MongoDBMac]: https://docs.mongodb.com/manual/tutorial/install-mongodb-on-os-x/
[MongoDBLinux]: https://docs.mongodb.com/manual/administration/install-on-linux/
[MongoDBWindows]: https://docs.mongodb.com/manual/tutorial/install-mongodb-on-windows/
[removedhttp]: https://docs.mongodb.com/manual/release-notes/3.6-compatibility/#http-interface-and-rest-api

If MongoDB 3.0 to 3.5 is installed on `localhost:27017` and `mongod` was started
with the `–httpinterface` option, you may view the web admin interface:

    http://localhost:28017/

**NOTE**: The above option is removed from 3.6 version as in [here][removedhttp].

## Settings Configuration
* Download the module package, as per[Installing contributed modules (Drupal 8)][install]
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

## Installation
  * Enable the `mongodb_storage` module, e.g. using `drush en mongodb_storage`.
  * Import the existing Key-Value contents from the database, using the Drush
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

![MongoDB on status page](images/mongodb-requirements.png)


### Composer Requirements

* This section is applicable if you are using composer already in your site to
  manage module dependencies. Know more about composer [here][composer].

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

* Either require mongodb/mongodb directly in the root composer.json of the project
* Add below JSON in the root `composer.json`
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
