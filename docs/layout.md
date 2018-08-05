## Project layout
### Module layout[^1]
    modules                    # The folder containing the code for modules in this suite
        mongodb                # Driver
        mongodb_storage        # Key-value
        mongodb_watchdog       # Logger
    .coveralls.yml             # Code coverage configuration file
    .scrutinizer.yml           # Scrutinizer configuration file
    .travis.yml                # Travis build configuration  file
    README.md                  # Current Readme file
    README.old.md              # Old readme (for developers' reference)
    composer.json              # Composer configuration file
    core.phpunit.xml           # PHPUnit configuration file
    example.settings.local.php # Example settings to connect to MongoDB

### Documentation layout[^2]
    mkdocs.yml    # The configuration file.
    docs/
        index.md  # The documentation homepage.
        ...       # Other markdown pages, images and other files.

[^1]: [Main module branch][Mainbranch]
[^2]: [Documentation branch][Docbranch]

[Mainbranch]: https://github.com/fgm/mongodb
[Docbranch]: https://github.com/fgm/mongodb/tree/mkdocs