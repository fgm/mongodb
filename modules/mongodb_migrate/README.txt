MongoDB Migrate
===============

Step 1: Add $conf['field_storage_default'] = 'mongodb_field_storage'; to settings.php

Step 2: Run drush mongodb-migrate-prepare

Step 3: Run drush mongodb-migrate

  Options include:

    --timeout="<number of seconds" # Use "0" for all. Defaults to "900".
    --count="<number of items>" # Defaults to "0" (all).

  Examples:

    drush mongodb-migrate --timeout="0" # No timeout. Overrides the default of 900 seconds.
    drush mongodb-migrate --timeout="100" # Timeout after 100 seconds
    drush mongodb-migrate --count="10000" # End after 10000 items or 900 seconds (the default), whichever is first.

Step 4: Open up a MongoDB console and ensure that the fields_current.<entity> collections exist and have the expected values.

Step 5: If for some reason you need to re-run the migration, go back to step 2 to reset it and start over.
