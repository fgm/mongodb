# MongoDB suite for Drupal

The MongoDB suite for Drupal 10 is a set of modules enabling the storage of
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


## Release series

- **[8.x-2.x]** is this suite: extra MongoDB services for stable production projects
  running on a SQL-based Drupal, which keeps its SQL database for everything else.
    - The current release is **[8.x-2.1]**, for Drupal 10.
      It remains supported until Drupal 10 reaches its end of life.
    - The next release, 8.x-2.2, will support Drupal 10 and 11.
- **[3.x]** is a separate product sharing the same drupal.org project:
  the "No SQL" database driver, to install Drupal itself on MongoDB, maintained by daffie.

[8.x-2.x]: https://www.drupal.org/project/mongodb/releases/8.x-2.x-dev
[8.x-2.1]: https://www.drupal.org/project/mongodb/releases/8.x-2.1
[3.x]: https://www.drupal.org/project/mongodb/releases/3.x-dev


## Modules

### Existing

| Module              | In a word | Information                                  |
|---------------------|-----------|----------------------------------------------|
| [mongodb]           | driver    | Client and Database services, [tests] base   |
| [mongodb_storage]   | key-value | Key-value store, with server-side expiration |
|                     | queue     | Default queue implementation                 |
| [mongodb_watchdog]  | logger    | PSR-3 compliant logger with a built-in UI    |

[mongodb]: modules/mongodb.md
[mongodb_storage]: modules/mongodb_storage.md
[mongodb_watchdog]: modules/mongodb_watchdog.md
[tests]: tests.md


### Planned

- **8.x-2.2**: support for Drupal 10 and 11, dropping Drupal 9.
  The main work is [#3625939] (Drupal 11 / Symfony 7 compatibility).
- **Drupal 12** support is expected after that.

Other candidate work open in the [issue queue], not yet assigned to a release:

- Compatibility with version 2.x of the `mongodb` extension and library: [#3542043].
- The new core `QueueFactoryInterface`: [#3425809].
- PHP 8.4 implicitly nullable type declarations: [#3458044].

Core services known to benefit from a switch outside SQL,
expected to be ported to 8.x-2.x in some release after 2.2:

| Module              | In a word | Information                                | Issue       |
|---------------------|-----------|--------------------------------------------|-------------|
| mongodb_cache       | cache     | Cache storage, with server-side expiration | [#3132584]  |
| mongodb_lock        | lock      | Lock plugin                                |             |
| mongodb_path        | path      | Path plugin                                | 7.x version: [#2538542] |
| mongodb_ban         | ban       | Ban IP manager service                     | [#2224723]  |

Some of these are likely to be included as `mongodb_storage` features, not as
additional sub-modules.
The status of all branches is tracked in [#2017091].

[issue queue]: https://www.drupal.org/project/issues/mongodb
[#2017091]: https://www.drupal.org/project/mongodb/issues/2017091
[#2224723]: https://www.drupal.org/project/mongodb/issues/2224723
[#2538542]: https://www.drupal.org/project/mongodb/issues/2538542
[#3132584]: https://www.drupal.org/project/mongodb/issues/3132584
[#3425809]: https://www.drupal.org/project/mongodb/issues/3425809
[#3458044]: https://www.drupal.org/project/mongodb/issues/3458044
[#3542043]: https://www.drupal.org/project/mongodb/issues/3542043
[#3625939]: https://www.drupal.org/project/mongodb/issues/3625939


### Future directions

This module has no direct equivalent in earlier versions, but its development
has been considered too.

| Module          | Information                           |
|-----------------|---------------------------------------|
| `mongodb_debug` | Provides low-level debug information, like the one Devel provides for SQL: [#2545918]. |

[#2545918]: https://www.drupal.org/project/mongodb/issues/2545918


A D7 version exists as the [mongodb_logger] project,
but it depends on the legacy `mongo` PHP extension.
Any future version will need a version of the `mongodb` extension which implements the
[MongoDB APM specification].

[MongoDB APM specification]: https://www.php.net/manual/en/mongodb.tutorial.apm.php
[mongodb_logger]: https://github.com/FGM/mongodb_logger/


## Legal information

* This suite of modules is licensed under the General Public License,
  v2.0 or later (GPL-2.0-or-later).
* MongoDB is a registered trademark of MongoDB Inc.
* Drupal is a registered trademark of Dries Buytaert.
* Symfony is a registered trademark of Symfony SAS.
* Documentation changes made since 2026-09-01 are AI-assisted and reviewed by the maintainer,
  who holds editorial responsibility for them.
