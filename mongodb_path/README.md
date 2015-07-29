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

    
Replace or front the SQL path storage
-------------------------------------

A path plugin can replace the core path plugin for optimal SQL load reduction, 
or behave as a path cache for optimal core + contrib compatibility. This plugin
chooses the latter. 
