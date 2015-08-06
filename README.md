This 8.x-2.x is an EXPERIMENT. It will likely crash, destroy your data, and
cause various kinds of pains. Do NOT deploy it on a production machine, or on a
MongoDB instance with useful data.

For most intents and purposes, you should be using the 8.x-1.x branch.

CONTENTS OF THIS FILE
---------------------

 * Introduction


INTRODUCTION
------------

MongoDB integration for Drupal. This module is a collection of several modules,
allowing to store different Drupal data in MongoDB.

Module                | Information
----------------------|---------------------------------------------------------
mongodb               | Support library for the other modules.

INSTALLATION
------------

The MongoDB module and sub-modules need some amount of configuration before they
will properly work. This guide assumes that :

* a [MongoDB][download] 3.0 or later instance is already installed, configured, and available to connect to from the Drupal instance.
* the site will be running [Drupal][drupal] 8.0-beta14 or later.
* the interpreter will be [PHP][php] 5.5.* or 5.6.*.
* the [mongo][mongo] (not [mongodb][mongodb]) PHP extension version 1.5.0 or later is installed and configured. 

[download]: https://www.mongodb.org/downloads
[drupal]: https://www.drupal.org/project/drupal
[php]: http://php.net/downloads.php
[mongo]: http://php.net/mongo
[mongodb]: http://php.net/mongodb

If MongoDB is installed on localhost, you may view the web admin interface:

    http://localhost:28017/

Upload the module package, as per [Installing contributed modules (Drupal 8)][install],
then copy the relevant section from the `example.settings.local.php` to your
`settings.local.php` file if you use one, or `settings.php` otherwise, and
adapt it to match your MongoDB settings.

[install]: https://www.drupal.org/documentation/install/modules-themes/modules-8


TRADEMARKS
----------

* Drupal is a registered trademark of Dries Buytaert.
* Mongo, MongoDB and the MongoDB  leaf logo are registered trademarks of MongoDB, Inc.

---

Everything below this line is wrong, leftover from previous versions.



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
mongodb_watchdog      | Store watchdog messages in MongoDB.


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

### 5: mongodb_watchdog

This variable holds the name of the collection used by the mongodb_watchdog
module. It defaults to `"watchdog"`.

EXAMPLE:

    $conf['mongodb_watchdog'] = 'drupalogs';

Whatever the name of the main collection defined with this variable, the
per-message-type collections are called `watchdog_(objectId)`.

### 6: mongodb_watchdog_items

This variable defines the maximum item limit on the capped collection used by
the mongodb_watchdog sub-module. If not defined, it defaults to 10000. The
actual (size-based) limit is derived from this variable, assuming 1 kiB per
watchdog entry.

EXAMPLE:

    $conf['mongodb_watchdog_items'] = 15000;

### 7: watchdog_limit

This variable define the maximum severity level to save into watchdog. Errors
below this level will be ignored by watchdog. If not defined, all errors will
saved.

EXAMPLE:

    $conf['watchdog_limit'] = WATCHDOG_CRITICAL;

See [watchdog_severity_levels()][levels] for further information about Watchdog severity levels.

[levels]: http://api.drupal.org/api/drupal/includes--common.inc/function/watchdog_severity_levels/7

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
mongodb_watchdog       | "watchdog" (variable)


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
