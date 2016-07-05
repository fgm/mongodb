# Logger: `mongodb_watchdog`

The `mongodb_watchdog` module stores the log entries for your Drupal site
in MongoDB collections as mentioned in
[Settings Configuration](../../install#settings-configuration)

This module uses a separate database, defined by the `logger` database alias in
settings. Do NOT point that alias to the same database as `default`, because the
module Drops the `logger` database when uninstalling, which would drop all your
other data with it.


## Configuration

Below are the configurable items available in `mongodb_watchdog.settings`

* `mongodb.watchdog.items`
    * the maximum item limit on the capped collection used by the module.
    * if not defined, it defaults to 10000.
    * the actual (size-based) limit is derived from this value, assuming
      1&nbsp;[kiB][kiBwiki] per watchdog entry.
* `mongodb.watchdog.limit`
    * the maximum severity level (0 to 7, per RFC 5424) to save into the logger
      storage.
    * events below this level (with a higher numerical level) will be ignored by
      the module.
    * if not defined, all events will saved.
* `mongodb.watchdog.items_per_page`
    * the maximum number of events displayed on the event details page.
* `mongodb_watchdog.request_tracking`
    * if true, enable the per-request event tracking.
    * if not defined, it defaults to false because its cost is not entirely
      negligible.
    * this feature requires the use of `mod_unique_id` in Apache 2.x
* `mongodb_watchdog.requests`
    * if request tracking is enabled, this setting defines the maximum requests
      limit on the capped collection used by the module.
    * if not defined, it defaults to 100000.
    * the actual (size-based) limit is derived from this variable, assuming
      1&nbsp;[kiB][kiBwiki] per tracker entry.

See `Drupal\Core\Logger\RfcLogLevel` and `Psr\Log\LogLevel` for further
information about severity levels.

[kiBwiki]: https://en.wikipedia.org/wiki/Kibibyte
