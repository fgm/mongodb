INTRODUCTION
============

MongoDB integration for Drupal, version 8.x-2.0-dev. - Experimental branch for
entity/field storage. _Only use for development purposes: it does *not* work at
this point.

[![Build Status](https://travis-ci.org/fgm/mongodb.svg?branch=xp-storage)](https://travis-ci.org/fgm/mongodb)
[![Coverage Status](https://coveralls.io/repos/github/fgm/mongodb/badge.svg?branch=xp-storage)](https://coveralls.io/github/fgm/mongodb?branch=xp-storage)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fgm/mongodb/badges/quality-score.png?b=xp-storage)](https://scrutinizer-ci.com/g/fgm/mongodb/?branch=xp-storage)

This package is a collection of several modules, allowing Drupal sites to store
various data in MongoDB, either using the provided modules, or writing their own
on top of the main `mongodb` module to take .

Module                | Information
----------------------|---------------------------------------------------------
mongodb               | Drupal/Drush wrapper around mongodb-php-library.
mongodb_storage       | Key-value storage in MongoDB.
mongodb_watchdog      | Store logger (watchdog) messages in MongoDB.

The complete documentation is available on [Github pages].

[Github pages]: https://fgm.github.io/mongodb/


LEGAL
=====

Like any Drupal module, this package is licensed under the [General Public
License, version 2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
or later.

* Drupal is a registered trademark of Dries Buytaert.
* Github is a registered trademark of GitHub, Inc.
* Mongo, MongoDB and the MongoDB leaf logo are registered trademarks of
  MongoDB, Inc.
