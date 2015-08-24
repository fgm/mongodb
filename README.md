CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Variables
 * Collections
 * Module specific configuration


INTRODUCTION
------------

MongoDB integration for Drupal. This module is a collection of several modules,
allowing to store different Drupal data in MongoDB.

Module                | Information
----------------------|---------------------------------------------------------
mongodb               | Support library for the other modules.
mongodb_block         | Store block information in MongoDB. Very close to the core block API.
mongodb_cache         | Store cache in MongoDB.
mongodb_field_storage | Store fields in MongoDB.
mongodb_queue         | DrupalQueueInterface implementation using MongoDB.
mongodb_session       | Store sessions in MongoDB.
mongodb_watchdog      | Store watchdog messages in MongoDB.

The module is a "development" version, and as such its configuration, as well of
some of its APIs may change with each -dev release: please read this README with
attention each time you upgrade, to spot changes.

The level of stability of the various components is variable :

* Planned for RC3:
  * Stable and maintained: mongodb, watchdog
  * Stable but less maintained: cache, queue
* Possibly not stable by RC3:
  * Not maintained regularly: block, block_ui, session, field_storage


INSTALLATION
------------

Install as usual, see [http://drupal.org/node/895232][install] for further information.

[install]: http://drupal.org/node/895232

The MongoDB module and sub-modules need some amount of configuration before they
will properly work. This guide assumes that a MongoDB instance is already
installed and configured on localhost or remote server. This module additionally
provides Drush integration to make queries against the MongoDB databases used by
Drupal.

If MongoDB is installed on localhost, you may view the web admin interface:

    http://localhost:28017/


REQUIREMENTS
------------

MongoDB module only supports:

* MongoDB server versions 1.3 or higher.
* `mongo` (not `mongodb`) extension
* PHP 5.1 or higher.
    * PHP 5.3 or higher is required for the cache plugin.


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

### 3: mongodb_write_[non]safe_options

These two configuration variables define the default write concern values for
write operations in "safe" or "non-safe" mode, depending on the array.

* `mongodb_write_safe_options` defaults to `['w' => 1]`
* `mongodb_write_nonsafe_options` defaults to `['w' => 0]`

Driver version          |  safe == TRUE                | safe == FALSE
------------------------|------------------------------|--------------
before 1.3.0            | `['safe' => TRUE]`           | `[]`
1.3.0 to 1.5.0 excluded | `['safe' => TRUE]`           | `mongodb_write_nonsafe_options`
1.5.0 and later         | `mongodb_write_safe_options` | `mongodb_write_nonsafe_options`

These variables are used as defaults, so can be overridden per alias in the
`mongodb_connections`, or per-query.

### 4: mongodb_cache_extra_bins

This optional configuration variable allows the cache plugin to support cache
expiration even for ill-behaved modules using bins which they do not declare in
`hook_flush_caches()`, called "extra bins".

If this variable is not defined, or is set to NULL, meaning unknown,
`mongodb_cache.module` will discover extra bins based on collections named
`cache_*` and not already declared by other modules. This is useful if all ill-
behaved modules only use cache bins named `cache_*`.

If any of these ill-behaved modules use bins not named like `cache_*`, these
bins cannot be discovered that way, and the variable needs to declare all extra
bins explicitly.

EXAMPLE

If module `foo` uses a `foo_bar` bin instead of `cache_foo_bar`, and module
`baz` uses bin `cache_baz`, the extra bins need to be declared like this:

    $conf['mongodb_cache_extra_bins'] = ['foo_bar', 'cache_baz'];

If all modules declare their cache bins, which is the normal case, this feature
is not needed, and the cache plugin can be used on its own, without needing to
enable the `mongodb_cache` module not define this variable.


### 5: mongodb_cache_stampede_delay

This configuration variable defines the number of seconds after an expiration
during which any other expiration request will be ignored, to prevent expire
stampedes. It defaults to 5 seconds.


### 6: mongodb_session

This variable holds the name of the collection used by the mongodb_session
sub-module. If not defined, it defaults to `"session"`.

EXAMPLE:

    $conf['mongodb_session'] = 'anyname';

### 7: mongodb_slave

This variable holds an array of the slaves used for the mongodb field storage
sub-module. If not defined, it defaults to an empty `array()`.

### 8: mongodb_watchdog

This variable holds the name of the collection used by the mongodb_watchdog
module. It defaults to `"watchdog"`.

EXAMPLE:

    $conf['mongodb_watchdog'] = 'drupalogs';

Whatever the name of the main collection defined with this variable, the
per-message-type collections are called `watchdog_(objectId)`.

### 9: mongodb_watchdog_items

This variable defines the maximum item limit on the capped collection used by
the mongodb_watchdog sub-module. If not defined, it defaults to 10000. The
actual (size-based) limit is derived from this variable, assuming 1 kiB per
watchdog entry.

EXAMPLE:

    $conf['mongodb_watchdog_items'] = 15000;

### 10: watchdog_limit

This variable define the maximum severity level to save into watchdog. Errors
below this level will be ignored by watchdog. If not defined, all errors will
saved.

EXAMPLE:

    $conf['watchdog_limit'] = WATCHDOG_CRITICAL;

See [watchdog_severity_levels()][levels] for further information about Watchdog severity levels.

[levels]: http://api.drupal.org/api/drupal/includes--common.inc/function/watchdog_severity_levels/7

### 11: mongodb_collections

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


MODULE-SPECIFIC CONFIGURATION AND STATE
---------------------------------------

The following configuration variables are needed to use the features provided
by the the following sub-modules.

### mongodb_cache

EXAMPLE:

    # -- Configure Cache.
    $conf['cache_backends'][]            = 'sites/all/modules/mongodb/mongodb_cache/mongodb_cache_plugin.php';
    $conf['cache_default_class']         = '\Drupal\mongodb_cache\Cache';

    # -- Don't touch SQL if in Cache.
    $conf['page_cache_without_database'] = TRUE;
    $conf['page_cache_invoke_hooks']     = FALSE;

* The `cache_lifetime` configuration variable applies to all cache bins, and is
  added to the TTL for non-permanent items.
* The `cache_flush_<bin_name>` state variables for each bin contains the
  timestamp of the latest cache garbage collection. Being a state variable
  rebuilt by the MongoDB Cache plugin, it should not be modified in
  `settings.php`, as this would prevent its dynamic maintenance.

If all modules on the site expose their cache bins via `hook_flush_caches()`,
there is no need to enable the mongodb_cache module.

### mongodb_session

EXAMPLE:

    # Session Caching
    $conf['session_inc']                 = 'sites/all/modules/mongodb/mongodb_session/mongodb_session.inc';
    $conf['cache_session']               = '\Drupal\mongodb_cache\Cache';

### mongodb_field_storage

EXAMPLE:

    # Field Storage
    $conf['field_storage_default']       = 'mongodb_field_storage';


RUNNING TESTS
-------------

The cache plugin can run core-equivalent tests : these are the core tests,
wrapped in a `setUp()`/`tearDown()` sequence supporting the use of a non-SQL
cache. Run the tests in the `MongoDB: Cache` group instead of the `Cache` group.

To run tests from the command line via run-tests.sh, use concurrency = 1. The
current core test wrapping does not support concurrent tests.


TROUBLESHOOTING
---------------

If installing mongodb_field_storage from an Install Profile:

* Do not enable the module in the profile `.info` file.
* Do not include the module specific `$conf` variable in `settings.php` during install.
* In the profiles `hook_install()` function, include:

        module_enable(array('mongodb_field_storage'));
        drupal_flush_all_caches();
