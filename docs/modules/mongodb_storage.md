# Key-value and Queue: `mongodb_storage`

The `mongodb_storage` module implements two Drupal APIs in MongoDB:

- the [Key-Value storage][keyvallink]
- the [Queue API][queueapilink]

[keyvallink]: https://en.wikipedia.org/wiki/Key-value_database
[queueapilink]: https://api.drupal.org/api/drupal/core%21core.api.php/group/queue/10

[settings]: ../install.md#configuring-settings
[default_queue]: https://api.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Aqueue/10

## Key-Value (Expirable) storage
### Configuration

To use the MongoDB Key-Value (Expirable) storage:

* ensure there is a `keyvalue` database alias as in
  [settings configuration][settings].
* declare MongoDB as the default Key-Value storage implementation by editing
  the existing parameter declarations in the `sites/default/services.yml` file:

        # In sites/default/services.yml.
        parameters:
          # (...snip...)
          factory.keyvalue:
            default: keyvalue.mongodb
          factory.keyvalue.expirable:
            keyvalue_expirable_default: keyvalue.expirable.mongodb

* enable the module, e.g. using `drush en mongodb_storage`.
* import the existing Key-Value contents from the database, using the Drush
  `mongodb:storage:import_keyvalue` command: `drush most-ikv`.
  It will output the names of the imported stores, for your information:

          key_value
          key_value_expire

* rebuild the container to take these changes into account using `drush cr`.

### Import of SQL Key-Value content

The module provides one single Drush command to import the content of the default SQL
storage for Key-Value into MongoDB.
It is  described in the previous paragraph as part
of the configuration steps.


## Queue service

This module provides a MongoDB Queue API implementation.

* Enable the module, e.g. using `drush en mongodb_storage`.
* Define a `queue` database alias, as described in [settings configuration][settings]
* Declare it as the [default Queue API implementation][default_queue],
  by adding this line to the `settings.php` file

        $settings['queue_default'] = 'queue.mongodb';

### Items from other producers

Other applications can feed Drupal queues by inserting documents
directly into the queue collection,
making Drupal one piece of an enterprise integration landscape.

* The collection for queue `foo` is `q_foo`, in the database of the `queue` alias.
* Documents use these fields:
    * `data`: the item payload, as a PHP-serialized string.
      Libraries producing that format exist for most languages,
      like [go-phpserialize] for Go.
      Without it, the item data is `NULL`.
    * `created`: optional, as an integer Unix timestamp in seconds.
      Without it, the first claim sets it:
      from the `_id` when it is an ObjectId, which embeds its creation time,
      or to the claim time otherwise.
      Releasing and claiming the item again does not change it.
    * `expires`: optional; leave it out or set it to `0`, and claims manage it.
    * `_id`: any type, but the ObjectId most drivers generate by default
      is the one which provides the creation time.
* Items are claimed by ascending `created`.
  Items without it come first, since a missing field sorts before any value.
* Claims use an update pipeline, which needs MongoDB 4.2 or later.

### Releasing items

`releaseItem()` returns `TRUE` only when it releases a claimed item,
and `FALSE` for an unclaimed, deleted or unknown item.
The Queue API leaves this open, and backends differ.
As compared on 2026-09-30 in [#3626967], releasing an unclaimed item returns:

* `TRUE`: core `DatabaseQueue` and `Batch`, and queues built on them
  like queue_unique and mongodb 3.x; redis 2.x; kafka.
* `FALSE`: this module, core `Memory` and `BatchMemory`, openstack_queues.
* Anything else: no return value (redis 8.x-1.x, aws_sqs_api, beanstalkd),
  an `Error` (rabbitmq 4.x), the AWS response (aws_sqs),
  or not applicable (advancedqueue, which does not implement the Queue API).

[go-phpserialize]: https://github.com/trim21/go-phpserialize
[#3626967]: https://www.drupal.org/project/mongodb/issues/3626967
