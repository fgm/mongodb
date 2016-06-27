INTRODUCTION
============

MongoDB integration for Drupal. This module is a collection of several modules,
allowing to store different Drupal data in MongoDB.

Module                | Information
----------------------|---------------------------------------------------------
mongodb               | Drupal/Drush wrapper around mongodb-php-library.
mongodb_watchdog      | Store logger (watchdog) messages in MongoDB.

This 8.x-2.x is an EXPERIMENT. It will likely crash, destroy your data, and
cause various kinds of pains. Do NOT deploy it on a production machine, or on a
MongoDB instance with useful data.

For most intents and purposes, you should be using the 7.x-1.x or 8.x-1.x branch.


INSTALLATION
============

The MongoDB module and sub-modules need some amount of configuration before they
will properly work. This guide assumes that :

* a [MongoDB][download] 3.0 or later instance is already installed, configured, and available to connect to from the Drupal instance.
* the site will be running [Drupal][drupal] 8.2.x.
* the [mongodb][mongodb] (not [mongo][mongo]) PHP extension version 1.1.7 or later is installed and configured.

[download]: https://www.mongodb.org/downloads
[drupal]: https://www.drupal.org/project/drupal
[php]: http://php.net/downloads.php
[mongo]: http://php.net/mongo
[mongodb]: http://php.net/mongodb

If MongoDB is installed on localhost, you may view the web admin interface:

    http://localhost:28017/

* Download the module package, as per [Installing contributed modules (Drupal 8)][install]
* Copy the relevant section from the `example.settings.local.php` to your
`settings.local.php` file if you use one, or `settings.php` otherwise, and
adapt it to match your MongoDB settings.
* At the root of your site, add a composer requiment:

      composer require mongodb/mongodb "^1.0.0"
* Enable the `mongodb` module. You now have access to the MongoDB services and Drush commands.

[install]: https://www.drupal.org/documentation/install/modules-themes/modules-8


CONFIGURATION
=============
mongodb_watchdog
----------------

The module uses a separate database, using the `logger` alias in MongoDB
settings. Do NOT point that alias to the same database as `default`, because the
module drops the logger database when uninstalling, which would drop all your
other data with it.

* `mongodb.watchdog.items`: the maximum item limit on the capped collection used
  by the module. If not defined, it defaults to 10000. The actual (size-based)
  limit is derived from this variable, assuming 1 kiB per watchdog entry.
* `mongodb.watchdog.limit`: the maximum severity level (0 to 7, per RFC 5424) to save
  into watchdog. Errors below this level (with a higher numerical level) will be
  ignored by the module. If not defined, all events will saved.
* `mongodb.watchdog.items_per_page`: the maximum number of events displayed on
  the event details page.

  See [Drupal\Core\Logger\RfcLogLevel][levels] and [Psr\Log\LogLevel][levelnames]
  for further information about severity levels.

* _Deprecated_ `mongodb_watchdog` used to allow redefining the name of the default watchdog
  collection. With the module now using a dedicated database, this is no longer
  useful and that name is no longer configurable.

[levels]: https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Logger%21RfcLogLevel.php/class/RfcLogLevel/8.2.x
[levelnames]: https://api.drupal.org/api/drupal/vendor%21psr%21log%21Psr%21Log%21LogLevel.php/class/LogLevel/8.2.x


DATABASES / COLLECTIONS
=======================

Module                | Database alias | Collection(s)    | Information
----------------------|----------------|------------------|-------------------------------
`mongodb`             | `default`      | (none)           | Checks alias/client consistency
`mongodb_watchdog`    | `logger`       | `watchdog`       | Event types
&uarr;                | &uarr;         | `watchdog_*`     | Capped collections for events

Earlier versions used to support a collection aliasing mechanism. With this
version generalizing dedicated databases per module, this is no longer needed
and the associated machinery has been removed.


TRADEMARKS
==========

* Drupal is a registered trademark of Dries Buytaert.
* Mongo, MongoDB and the MongoDB  leaf logo are registered trademarks of MongoDB, Inc.

---

__Everything below this line relates to previous versions, and is likely entirely wrong now.__



CONTENTS OF THIS FILE
---------------------

 * Variables
 * Collections
 * Module specific configuration


INTRODUCTION
------------

mongodb_block         | Store block information in MongoDB. Very close to the core block API.
mongodb_cache         | Store cache in MongoDB.
mongodb_field_storage | Store fields in MongoDB.
mongodb_queue         | DrupalQueueInterface implementation using MongoDB.
mongodb_session       | Store sessions in MongoDB.


INSTALLATION
------------

This module additionally provides Drush integration to make queries against the
MongoDB databases used by Drupal.


CONFIGURATION VARIABLES
-----------------------

MongoDB uses several specific configuration variables that are not currently
exposed in the UI. Non-developers should note that all $conf variables should
be placed at the bottom of the sites settings.php file under a #MongoDB comment.

### 1: mongodb_connections

The `mongodb_connections` variable holds the databases available to the module.
The contents are arranged as an associative array holding the name (alias) of
the connection, the database address, and the name of the database. If not
defined, it makes a single default entry..

EXAMPLE:

    $conf['mongodb_connections'] = array(
      // Connection name/alias
      'default' => array(
        // Omit USER:PASS@ if Mongo isn't configured to use authentication.
        'host' => 'mongodb://USER:PASS@localhost',
        // Database name
        'db' => 'drupal_default',
      ),
    );

If `mongodb_connections` is not defined, `default` is automatically created with
the following settings:

    'default' => array(
      'host' => 'localhost',
      'db' => 'drupal'
    )

Also, `connection_options` might be specified to allow for connecting to
replica sets (and any other options listed on [http://php.net/manual/mongoclient.construct.php][construct]

[construct]: (http://php.net/manual/mongoclient.construct.php)

    $conf['mongodb_connections'] = array(
      // Connection name/alias
      'default' => array(
        'host' => 'mongodb://[username:password@]host1[:port1][,host2[:port2:],...]',
        // Database name
        'db' => 'drupal_default',
        'connection_options' => array('replicaSet' => 'replicasetname'),
      ),
    );

Where square brackets indicate `username`, `password`, `port1` etc are optional.

Querying a slave is also possible by creating a new connection alias and
specifying the `slave_ok` option.

    $conf['mongodb_connections'] = array(
      // Connection name/alias.
      'slave' => array(
        'host' => 'slave1',
        // Database name.
        'db' => 'drupal_default',
        'slave_ok' => TRUE,
      ),
    );

As of the mongoDB 1.3 php driver, `slave_ok` has been deprecated and instead
`read preference` should be used when dealing with a replica set. These
preferences can be set at a connection or collection level ; see below for the
collection information.

    $conf['mongodb_connections'] = array(
      // Connection name/alias
      'slave' => array(
        'host' => 'slave1',
        // Database name
        'db' => 'drupal_default',
        'read_preference' => array(
          'preference' => 'secondaryPreferred',
          'tags' => array(
            array('dc' => 'east', 'use' => 'reporting'),
            array('dc' => 'west'),
          ),
        ),
        'connection_options' => array('replicaSet' => 'main'),
      ),
    );

### 2: mongodb_debug

A variable meant primarily for developers, `mongodb_debug` causes a collection
to return a `MongoDebugCollection` and `MongoDebugCursor` instead of their
normal equivalents. If not defined, defaults to `FALSE`.

EXAMPLE:

    $conf['mongodb_debug'] = FALSE;

### 3: mongodb_session

This variable holds the name of the collection used by the mongodb_session
sub-module. If not defined, it defaults to `"session"`.

EXAMPLE:

    $conf['mongodb_session'] = 'anyname';

### 4: mongodb_slave

This variable holds an array of the slaves used for the mongodb field storage
sub-module. If not defined, it defaults to an empty `array()`.

### 8: mongodb_collections

See the COLLECTIONS section below.


COLLECTIONS
-----------

Collections are MongoDB's equivalent of relational database tables. In the
context of this module, they can be used to span data from the multiple
sub-modules into separate databases. This is accomplished by aliasing the
collection name to a connection name (as defined in the `mongodb_connections`
variable). If you want everything in a single database, `mongodb_collections` do
not need to be configured and everything is written to the local default
database using the default collection names (given below). The dot is allowed in
the name of mongodb collections. Before the dot, mongodb_field_storage uses
`"fields_current"` or `"fields_revision"` and puts the entity type after.

MODULE                 | COLLECTION NAMES
-----------------------|-----------------------------------
mongodb_block          | "block"
mongodb_cache          | "cache_bootstrap"
                       | "cache_menu"
                       | ...
mongodb_field_storage  | "fields_current.node",
                       | "fields_current.taxonomy_term"
                       | "fields_revision.user"
                       | ...
mongodb_queue          | "queue."<queue name foo>
                       | "queue."<queue name bar>
                       | ...
mongodb_session        | "session" (variable)


In the following example, the watchdog collection will be handled by
the hypothetical connection alias `logginghost`

EXAMPLE:

    $conf['mongodb_collections'] = array('watchdog' => 'logginghost');

If you are using the 1.3 or more recent drivers you can specify the following
connection options: `db_connection` and `read_preference`. These options allow
you to  control collection-level mongo read preferences and whether any tags
should be used.

    $conf['mongodb_collections'] = array(
      'watchdog' => array(
        'db_connection' => 'logginghost',
        'read_preference' => array(
          'preference' => 'secondaryPreferred',
          'tags' => array(
            array('dc' => 'east', 'use' => 'reporting'),
            array('dc' => 'west'),
          ),
        ),
      ),
    );

If you do not need read_preference you can continue to utilise the existing
array structure for the 1.3 and more recent drivers.


MODULE-SPECIFIC CONFIGURATION
-----------------------------

The following configuration variables are needed to use the features provided
by the the following sub-modules.

### mongodb_cache

EXAMPLE:

    # -- Configure Cache.
    $conf['cache_backends'][]            = 'sites/all/modules/mongodb/mongodb_cache/mongodb_cache.inc';
    $conf['cache_default_class']         = 'DrupalMongoDBCache';

    # -- Don't touch SQL if in Cache.
    $conf['page_cache_without_database'] = TRUE;
    $conf['page_cache_invoke_hooks']     = FALSE;

### mongodb_session

EXAMPLE:

    # Session Caching
    $conf['session_inc']                 = 'sites/all/modules/mongodb/mongodb_session/mongodb_session.inc';
    $conf['cache_session']               = 'DrupalMongoDBCache';

### mongodb_field_storage

EXAMPLE:

    # Field Storage
    $conf['field_storage_default']       = 'mongodb_field_storage';


TROUBLESHOOTING
---------------

If installing mongodb_field_storage from an Install Profile:

* Do not enable the module in the profile `.info` file.
* Do not include the module specific `$conf` variable in `settings.php` during install.
* In the profiles `hook_install()` function, include:

        module_enable(array('mongodb_field_storage'));
        drupal_flush_all_caches();
