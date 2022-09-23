# MongoDB suite for Drupal

The MongoDB suite for Drupal 10 and 9 is a set of modules enabling the storage of
various types of data on a Drupal&reg; site in MongoDB&reg;. This comes in
addition to the standard SQL storage used by Drupal.

It comprises several Drupal modules, each implementing a specific functionality.
Except the base `mongodb` module, upon which all others depend because it
provides the standardized connection service to Drupal, all the modules are
independent of each other except where indicated.

The [`mongodb`](modules/mongodb.md) module is not just the basis for this
package: it is also designed to ease the development of bespoke business logic
for end-user projects, providing Drupal-integrated Symfony&reg; services for
Client and Database with a familiar alias-based selection, like those provided
by Drupal core for the SQL database drivers.


## Modules

### Existing

| Module              | In a word | Information                                  |
|---------------------|-----------|----------------------------------------------|
| [mongodb]           | driver    | Client and Database services, [tests] base   |
| [mongodb_storage]   | key-value | Key-value store, with server-side expiration |
|                     | queue     | Default queue implementation                 |
| [mongodb_watchdog]  | logger    | PSR-3 compliant logger with a built-in UI    |

[mongodb]: /modules/mongodb
[mongodb_storage]: /modules/mongodb_storage
[mongodb_watchdog]: /modules/mongodb_watchdog
[tests]: /tests


### Planned

Modules expected to be ported to 8.x-2.x in some release after 2.1 include:

| Module              | In a word | Information                                |
|---------------------|-----------|--------------------------------------------|
| mongodb_cache       | cache     | Cache storage, with server-side expiration |
| mongodb_lock        | lock      | Lock plugin                                |
| mongodb_path        | path      | Path plugin                                |

Some of these are likely to be included as `mongodb_storage` features, not as
additional sub-modules.


### Future directions

This module has no direct equivalent in earlier versions, but its development
has been considered too.

| Module          | Information                           |
|-----------------|---------------------------------------|
| `mongodb_debug` | Provides low-level debug information. |


A D7 version exists as the [mongodb_logger] project,
but it depends on the legacy `mongo` PHP extension.
Any future version will need a version of the `mongodb` extension which implements the
[MongoDB APM specification].

[MongoDB APM specification]: http://php.net/manual/fr/mongodb.tutorial.apm.php
[mongodb_logger]: https://github.com/FGM/mongodb_logger/


## Legal information

* This suite of modules is licensed under the General Public License,
  v2.0 or later (GPL-2.0-or-later).
* MongoDB is a registered trademark of MongoDB Inc.
* Drupal is a registered trademark of Dries Buytaert.
* Symfony is a registered trademark of Symfony SAS.
