CONTENTS OF THIS FILE
=====================


 * Introduction
 * Installation
 * Variables
 * Collections
 * Troubleshooting
 * Hidden features


INTRODUCTION
============

MongoDB integration for Drupal. This module is a collection of several modules,
allowing to store different Drupal data in MongoDB.

mongodb                Support library for the other modules.
mongodb_watchdog       Store watchdog messages in MongoDB.

Modules have been in existence at some point on this 6.x branch to support
Cache, Queue, and Session storage. However, they were not maintained and have
since been removed from the release. See the section about "hidden features" 
about them.


INSTALLATION & CONFIGURATION
============================

Install as usual, see http://drupal.org/node/895232 for further information.

The MongoDB module and sub-modules need some amount of configuration before they
will properly work. This guide assumes that a MongoDB instance is already
installed and configured on localhost or remote server. This module additionally
provides Drush integration to make queries against the MongoDB databases used by
Drupal.

If MongoDB is installed on localhost, you may view the web admin interface:

    http://localhost:28017/


VARIABLES
=========

MongoDB uses several specific configuration variables that are not currently
exposed in the UI. Non-developers should note that all $conf variables should
be placed at the bottom of the sites settings.php file under a #MongoDB comment.


Configuration Variables
-----------------------

### 1: mongodb_connections

The mongodb_connections variable holds the databases available to the module.

The contents are arranged as an associative array holding the name (alias) of
the connection, the database address, and the name of the database. If not
defined, it makes a single default entry. See the note below *.

    $conf['mongodb_connections'] = array(
      // Connection name/alias
      'default' => array(
        // Omit USER:PASS@ if Mongo isn't configured to use authentication.
        'host' => 'mongodb://USER:PASS@localhost',
        // Database name
        'db' => 'drupal_default',
      ),
    );

If `mongodb_connections` is not defined, 'default' is automatically created with
the following settings:

    'default' => array(
      'host' => 'localhost',
      'db' => 'drupal',
    )

Also, `connection_options` might be specified to allow for connecting to replica
sets (and any other options listed on 
http://www.php.net/manual/mongo.construct.php)

    $conf['mongodb_connections'] = array(
      // Connection name/alias.
      'default' => array(
        'host' => 'mongodb://[username:password@]host1[:port1][,host2[:port2:],...]',
        // Database name.
        'db' => 'drupal_default',
        'connection_options' => array('replicaSet' => 'replicasetname'),
      ),
    );

Where square brackets indicate username, password, port1 etc are optional.

Querying a slave is also possible by creating a new connection alias and
specifying `slave_ok` option.

    $conf['mongodb_connections'] = array(
      // Connection name/alias
      'slave' => array(
        'host' => 'slave1',
        // Database name
        'db' => 'drupal_default',
        'slave_ok' => TRUE,
      ),
    );

As of the MongoDB 1.3 PHP driver, `slave_ok` has been deprecated and instead
read preferences should be used when dealing with a replica set. These
preferences can be set at a connection or collection level. See below for the
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

A variable primarily for developers, mongodb_debug causes a collection to return
a `MongoDebugCollection` and `MongoDebugCursor` instead of their normal
equivalents. If not defined, defaults to `FALSE`.

    $conf['mongodb_debug'] = FALSE;


### 3: mongodb_session

This variable holds the name of the collection used by the hidden
`mongodb_session` sub-module. If not defined, it defaults to `session`. See the
"Hidden features" section for that component.

    $conf['mongodb_session'] = 'anyname';


### 4: mongodb_watchdog

This variable holds the name of the main collection used by the
`mongodb_watchdog` module. It defaults to `watchdog`.

    $conf['mongodb_watchdog'] = 'drupalogs';
   

### 5: mongodb_watchdog_items

This variable defines the maximum item limit on the capped collection used by
the `mongodb_watchdog` sub-module. If not defined, it defaults to 10000. The
actual (size-based) limit is derived from this variable, assuming 1 kiB per
watchdog entry.

    $conf['mongodb_watchdog_items'] = 15000;


### 6: watchdog_limit

This variable define the maximum severity level to save into watchdog. Errors
under this level will be ignored by watchdog. If not defined, all errors will
saved.

    $conf['watchdog_limit'] = WATCHDOG_CRITICAL;

See [Drupal severity levels] for further information about Watchdog severity
levels.

[Drupal severity levels]: https://api.drupal.org/api/drupal/includes%21common.inc/function/watchdog_severity_levels/6


### 7: mongodb_collections

See the COLLECTIONS section below.


COLLECTIONS
------------

Collections are MongoDB's equivalent of relational database tables. In the
context of this module, they can be used to span data from the multiple
sub-modules into separate databases.

This is accomplished by aliasing the collection name to a connection name, as
defined in the `mongodb_connections` variable. If you want everything in a
single database, collections do not need to be configured and everything is
written to the local default database using the default collection names given
below. A dot is allowed in the name of mongodb collections.

MODULE                  COLLECTION NAMES
-----------------------------------------------------------
mongodb_watchdog        "watchdog" (variable)
-----------------------------------------------------------

In the following example, the watchdog collection will be handled by
the hypothetical connection alias 'logginghost'

    $conf['mongodb_collections'] = array('watchdog' => 'logginghost');

If you are using the 1.3 drivers, you can specify the following connection
options `db_connection` and `read_preference`. These options allow to control
collection level mongo read preferences and whether any tags should be used.

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
array structure for the 1.3 drivers.


TROUBLESHOOTING
------------

Always have an available MongoDB instance before enabling these modules, or when
disabling / uninstalling them.


HIDDEN FEATURES
---------------

### Cache

A cache plugin using MongoDB has existed for D6, with the last commit by chx on
2009-12-31.

To work on recent D6/PF6 versions, it needs an additional patch, the last
version of which exists at https://www.drupal.org/node/1098268#comment-5169652

Should you wish to resurrect it, you should probably start by updating that
issue with an up-to-date patch, and configure the cache like this.

    # -- Configure Cache
    $conf['cache_backends'][] = 'sites/all/modules/contrib/mongodb/mongodb_cache/mongodb_cache.inc';
    $conf['cache_default_class'] = 'DrupalMongoDBCache';


### Sessions

A cache plugin using MongoDB has existed for D6, with the last commit by Ben
Buckmann on 2011-04-25.

To work on recent D6/PF6 versions, it needs an additional patch, the last
version of which exists at https://www.drupal.org/node/1007974#comment-5097964   

Should you wish to resurrect it, you should probably start by updating that
issue with an up-to-date patch, and configure the session like this, on top of
the MongoDB Cache component.

    # Session Caching
    $conf['session_inc'] = 'sites/all/modules/mongodb/mongodb_session/mongodb_session.inc';
    $conf['cache_backends'][] = 'sites/all/modules/contrib/mongodb/mongodb_cache/mongodb_cache.inc';
    $conf['cache_session'] = 'DrupalMongoDBCache';


### Queue

A queue plugin using MongoDB has existed for D6, in the form of a patch to be
found at https://www.drupal.org/node/1179738#comment-4656180

It depends on the contrib [Drupal Queue] module

[Drupal Queue]: https://www.drupal.org/project/drupal_queue

Should you wish to resurrect it, you should probably start by updating that
issue with an up-to-date patch, applying on 6.x-1.x HEAD, as there never was a
committed version for 6.x-1.x.
