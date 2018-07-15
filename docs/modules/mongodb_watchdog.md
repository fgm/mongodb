# Logger: `mongodb_watchdog`

The module uses a separate database, using the logger database alias in settings. Do NOT point that alias to the same database as default, because the module drops the logger database when uninstalling, which would drop all your other data with it.

* mongodb.watchdog.items: the maximum item limit on the capped collection used by the module. If not defined, it defaults to 10000. The actual (size-based) limit is derived from this variable, assuming 1 kiB per watchdog entry.

* mongodb.watchdog.limit: the maximum severity level (0 to 7, per RFC 5424) to save into watchdog. Errors below this level (with a higher numerical level) will be ignored by the module. If not defined, all events will saved.

* mongodb.watchdog.items_per_page: the maximum number of events displayed on the event details page.

* mongodb_watchdog.request_tracking: if true, enable the per-request event tracking. If not defined, it defaults to false because its cost is not entirely negligible. This feature requires the use of mod_unique_id in Apache 2.x

* mongodb_watchdog.requests: if request tracking is enabled, this setting defined the maximum requests limit on the capped collection used by the module. If not defined, it defaults to 100000. The actual (size-based) limit is derived from this variable, assuming 1 kiB per tracker entry.

See `Drupal\Core\Logger\RfcLogLevel` and `Psr\Log\LogLevel` for further information about severity levels.