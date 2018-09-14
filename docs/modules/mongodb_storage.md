# Key-value: `mongodb_storage`

The `mongodb_storage` module implements the [Key-Value storage][keyvallink]
for Drupal in MongoDB.

## Configuration

To use the MongoDB Key-Value (Expirable) storage:

* ensure there is a `keyvalue` database alias as in
  [settings configuration](../../install#settings-configuration).
* declare MongoDB as the default Key-Value storage implementation by editing
  the existing declarations in the `sites/default/services.yml` file:

        # In sites/default/services.yml.
        factory.keyvalue:
          default: keyvalue.mongodb
        factory.keyvalue.expirable:
          keyvalue_expirable_default: keyvalue.expirable.mongodb

* enable the module, e.g. using `drush en mongodb_storage`.
* import the existing Key-Value contents from the database, using the Drush
  or Console `mongodb:storage:import_keyvalue` command: `drush most-ikv`. 
  It will output the names of the imported stores, for your information:

          key_value
          key_value_expire
        
* rebuild the container to take these changes into account using `drush cr`.

[keyvallink]: https://en.wikipedia.org/wiki/Key-value_database

## Command

The module provides one single command to import the content of the default SQL
storage for Key-Value into MongoDB. The command is available for Drush and 
Drupal Console indifferently, and is described in the previous paragraph as part
of the configuration steps.
