##################
  MongoDB module
################## 


Configuration
=============


MongoDB uses eight variables for configuration. These are not exposed in any UI
currently.


mongodb_watchdog_collectionname (watchdog) 
------------------------------------------

This variable holds the name of the collection used by the mongodb_watchdog 
module. It defaults to "watchdog".


mongodb_collections (mongodb)
-------------------

This variable allows mapping collections to aliases:

$conf['mongodb_collections'] = array(
  'watchdog' => 'logginghost',
)

If a collection has no alias specified in 'mongodb_collections', then the 
alias 'default' is used (as noted above, the 'default' connection alias
always exists.) If you want everything in one database, then there is no need
to set up mongodb_collections. So for example, if no variables are defined at
all, then MongoDB writes everything in the "drupal" database on "localhost".


mongodb_connections (mongodb, drush plugin, queue) 
-------------------

This variable is an associative array. The keys are what I will call aliases, 
the values are associative arrays again, with two keys, host and db. Example:

  $conf['mongodb_connections'] = array(
    'logginghost' => array('host' => 'log.local', 'db' => 'drupalogs'),
  );

The 'default' alias is special, if it's not defined then
 
  'default' => array('host' => 'localhost', 'db' => 'drupal')
   
is added automatically.


mongodb_debug (mongodb)
-------------

This variable, when set to TRUE, causes the mongodb_collection() to returned a
a mongoDebugCollection with mongoDebugCursor instead of their normal
equivalents. It defaults to FALSE.


mongodb_items (watchdog)
-------------

This variable defines the maximum item limit on the capped collection used by 
mongodb_watchdog. It defaults to 10000.

The actual (size-based) limit is derived from this variable,
assuming 1 kiB per watchdog entry 


mongodb_session (session)
---------------

This variable holds the name of the collection used by the mongodb_session 
module. It defaults to "session".


mongodb_slave (field_storage)
-------------

This variable holds an array of the slaves used for the mongodb field storage.
It defaults to array(). 


watchdog_limit (watchdog)
--------------

This is a proposed variable for D8 core watchdog, as per issue #1268636. 
Until core implements it, it has to be used by watchdog implementations instead.

 
Collection names
================
 
Module                 Collection(s)
-----------------------------------------------------------------
mongodb_block          "block" 
mongodb_cache          "cache_bootstrap"
                       "cache_menu"
                       ...
mongodb_field_storage  "fields_current.node",
                       "fields_current.taxonomy_term"
                       "fields_revision.user"
                       ...
mongodb_queue          "queue."<queue name foo>
                       "queue."<queue name bar>
                       ...
mongodb_session        "session" (variable)
mongodb_watchdog       "watchdog" (variable)
                       
The dot is allowed in the name of mongodb collections. Before the dot, 
mongodb_field_storage uses "fields_current" or "fields_revision" and puts the 
entity type after.
