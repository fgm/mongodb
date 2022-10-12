# Key-value and Queue: `mongodb_storage`

The `mongodb_storage` module implements two Drupal APIs in MongoDB:

- the [Key-Value storage][keyvallink]
- the [Queue API][queueapilink]

[keyvallink]: https://en.wikipedia.org/wiki/Key-value_database
[queueapilink]: https://api.drupal.org/api/drupal/core%21core.api.php/group/queue/9.2.x

[settings]: ../install#settings-configuration
[default_queue]: https://api.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Aqueue/9.2.x

## Key-Value (Expirable) storage
### Configuration

To use the MongoDB Key-Value (Expirable) storage:

* ensure there is a `keyvalue` database alias as in
  [settings configuration](settings).
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
