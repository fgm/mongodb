// $Id$

Configuration
=============

MongoDB uses two variables (somewhat similar to the memcached module).

The mongodb_connections variable is an associative array. The keys are
what I will call aliases, the values are associative arrays again, with two
keys, host and db. Example:

$conf['mongodb_connections'] = array(
  'logginghost' => array('host' => 'log.local', 'db' => 'drupalogs'),
);

Then mongodb_collections will allow mapping collections to aliases:

$conf['mongodb_collections'] = array(
  'watchdog' => 'logginghost',
)

If a connection is not specified on mongodb_collections then default is
presumed. If the default alias is not specified then
array('host' => 'localhost', 'db' => 'drupal') is presumed. This way, if no
variables are defined thenMongoDB writes everything in the drupal database on
localhost.

mongodb_block uses the 'block' collection, mongodb_cache uses the name of the
bin (cache_bootstrap, cache_menu etc), mongodb_session uses 'session' and
mongodb_watchdog is configurable but by default it uses 'watchdog'.
mongodb_field_storage uses collections like fields_current.node,
fields_current.taxonomy_term, fields_revision.user... the dot is allowed in
the name of mongodb collections. Before the dot it uses fields_current or
fields_revision and puts the entity type after.
