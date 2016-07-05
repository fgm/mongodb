# Key-value: `mongodb_storage`

The `mongodb_storage` module implements the [key-value storage][keyvallink]
for Drupal in MongoDB.

* To use the MongoDB Key-Value (Expirable) storage:
    * ensure there is a `keyvalue` database alias as in
      [settings configuration](../../install#settings-configuration).
    * declare MongoDB as the default keyvalue storage implementation by editing
      the existing declarations in the `sites/default/services.yml` file:

```yaml
    # In sites/default/services.yml.
    factory.keyvalue:
      default: keyvalue.mongodb
    factory.keyvalue.expirable:
      keyvalue_expirable_default: keyvalue.expirable.mongodb
```
* enable the module, e.g. using `drush en mongodb_storage`.
* import the existing Key-Value contents from the database, using the Drush
  `mongodb_storage-import-keyvalue`command: `drush most-ikv`. It will output
  the list of imported keys, for your information, like:

```
    key_value
      config.entity.key_store.action
        uuid:054e62b3-1c40-4f22-aa17-c092bd796ee8
        uuid:0cfd15f5-c01a-4912-991c-ad10e934f86e
    (...lots of line, then...)
    key_value_expire
      update_available_releases
        drupal
```
 * rebuild the container to take these changes into account using `drush cr`.
[keyvallink]: https://en.wikipedia.org/wiki/Key-value_database
