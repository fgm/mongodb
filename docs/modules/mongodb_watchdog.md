# Logger: `mongodb_watchdog`

The `mongodb_watchdog` module stores the log entries for your Drupal site
in MongoDB collections as mentioned in
[Settings Configuration](../../install#settings-configuration)

It also exposes a logs browsing UI on `admin/reports/mongodb/watchdog/`,
with additional features in comparison with the built-in database logging:

- location of error log, with source file and line number
- integrated display of the Top404 and Top403
- grouping of logs by message
- optional: grouping of logs by HTTP request, when the `mongodb_watchdog.request_tracking`
  configuration is enabled

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

## Troubleshooting command

The module provides one single Drush command designed to help troubleshoot issues with
logging when this module is enabled.

The command takes no arguments and returns an analysis of the logger collections
which needs to be interpreted.

    $ drush mongodb:watchdog:sanitycheck
    0: 0
    1: 2
    1000: 1
    2000: 0
    3000: 0
    4000: 0
    5000: 0
    6000: 0
    7000: 0
    8000: 0
    9000: 0
    9999: 0
    10000: 0
    $

What the output of this command represents is the number of `watchdog_event_*`
collections with a document count in the range specified as the key.

In the results, the first and last two entries are specific: they match exact
counts, while the others are intervals, so the actual buckets are: `0`, `1`,
`2..1000`, `1001..2000`, ..., `9001..9998`, `9999`, `10000`. The specific
value 10000 is the number of entries allowed in event collections, as these are
MongoDB capped collections: whatever its value `n`, the command will report
`0`, `1`, `n-1`, `n`, and 9 ranges in between.


### Interpreting the results

As a general rule, on a high-load site, all buckets should have comparable
numbers, and the number of events logged grouped by pattern is pseudo-random,
except for the `0` bucket, which should be empty.

* `0` bucket non-empty: unless there was specific manipulation performed by
  hand, this is a bug, and should be reported: event collections are created
  when an event is created, by the first insertion, and dropped as needed, but
  never truncated or created without an insert.
* `1` bucket has a high value, possibly orders of magnitude higher than other
  buckets. This denotes an incorrect use of the Drupal logger system, for which
  the PSR-3 `message` parameter is a message template, and the variant part of
  the message is expressed as placeholder values in the options. Look for calls
  to log operations passing a variable as the message and replace them by a
  template containing placeholders for the variable content in the message.
* `n-1` bucket has a high value: suspicious situation with the logger,
  especially if the "n" value is low or 0: please report a possible bug, with
  accompanying data
* `n` bucket has a high value
    * `n-1` has a high value too: this is a mostly  normal situation,
      especially if the value in the previous bucket is also high, although it
      means the data rotation in your site is possibly a bit high and you should
      increase the size of the capped collections to ensure longer retention of
      data.
    * `n-1` has a low or 0 value: your logs are saturated by the site, and you
      are losing information, as all the capped collections in that situation
      are rolling over constantly. Ensure you have enough storage and raise the
      value capped collection size.
    * The config value to change in these cases is `mongodb.watchdog.items`.
