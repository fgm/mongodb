Configuration
=============

- Variables
  - `mongodb_path_flush`: controls flushing the path cache, to mimic core.
    - 0 : feature is not enabled. This is the default.
    - != 0 : feature is enabled. Configuration sets it to MAXINT. On each flush, 
      the variable will be set to the flush timestamp.

Design considerations
=====================

Cache flush
-----------

Because the path cache, unlike in the SQL model, is stored in the alias 
document, actally flushing it involves modifying each document in the alias
collection. 

To avoid incurring this cost, a policy similar to the one in Varnish is used: 
when a flush is required, the `mongodb_path_flush` variable is used as a 
timestamp, and the document timestamp returns by a fetch is compared with that 
value. If the document timestamp is earlier than the value, it means it was 
stored before the "flush", so it is removed from the document before use, 
causing it to be regenerated as if it had actually been removed by an actual 
flush.

    
Replace SQL path plugin
-----------------------

A path plugin can replace the core path plugin for optimal SQL load reduction, 
or behave as a path cache for optimal core + contrib compatibility. This plugin
chooses the former, the Redis path plugin chooses the latter. 

- replace includes/path.inc:   all code using only the path.inc MUST work
- core code using {url_alias} directly
  - includes/update.inc
    - update_fix_d7_requirements() : only used for the D6 to D7 upgrade: 
      ignored
  - modules/path/path.admin.inc : 
    - path_admin_overview(), 
    - path_admin_form_validate()
  - modules/path/path.test :
    - getPID() : it does not have to pass
  - modules/simpletest/tests/path.test: ignored, this is only for the D6-D7 
    upgrade
  - modules/simpletest/tests/drupal6*php: ignored, this is only for the D6-D7 
    upgrade
  - modules/system/system.install
    - system_schema(): creates the table. Not a problem.
    - system_update_7042(): irrelevant
    - system_update_7048(): irrelevant
    - system_update_7068(): irrelevant
- contrib code using {url_aliass} directly
  - redis 
    - redis.path.inc : 
      - because it behaves as a caching layer for the standard path plugin, it 
        accesses {url_alias} directly
      - because it ias a path plugin, its compatibility is not considered
