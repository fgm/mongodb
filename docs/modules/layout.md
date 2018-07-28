## Project layout
### Module layout
    modules                    # The folder containing the code for modules in this suite
        mongodb                # driver
        mongodb_storage        # key-value
        mongodb_watchdog       # logger
    .coveralls.yml             # Code coverage configuration file
    .scrutinizer.yml           # Scrutinizer configuration file
    .travis.yml                # Travis build configuration  file
    README.md                  # Current Readme file
    README.old.md              # Old readme (for developers' reference)
    composer.json              # Composer configuration file
    core.phpunit.xml           # PHPUnit configuration file
    example.settings.local.php # Example settings to connect to MongoDB

### Documentation layout
    mkdocs.yml    # The configuration file.
    docs/
        index.md  # The documentation homepage.
        ...       # Other markdown pages, images and other files.