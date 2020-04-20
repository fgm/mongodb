#!/usr/bin/env bash

# This script makes it easy to run the Simpletests for the modules in the
# MongoDB package. Use it in just three steps if you havec Drush in your path:
#
# 1 enable Simpletest: "drush en -y simpletest"
# 2 edit your your settings*.php to configure MongoDB connection and cache plugin.
# 3 run: "bash tests.bash <absolute path to Drupal root>"
#
# You may want to run it with custom environment variables, e.g WEB_USER or VHOST.
#
# Notice: the script itself uses Drush to flush caches.

# The path where the script will leave JUnit test results
OUTPUT_DIR=/tmp/xml
# The user name under which your web server runs
WEB_USER=${WEB_USER:-"www-data"}
# The test group to run.
TESTS="MongoDB"
# The Drupal root directory. Dynamic from script arguments.
BASE=
# The Drupal vhost
VHOST=${VHOST:-"http://localhost"}

# Is the specified directory a Drupal root ?
#
# @return int
# - 0 on success
# - 1 on failure (not a Drupal root)
function isDrupal {
    local base=$1
    local result

    if [ ! -d "$base" ]; then
      return 1
    fi

    pushd "$base" > /dev/null
    [ -d core/includes -a -d core/misc -a -d core/modules -a -d core/profiles -a -d sites -a -d core/themes ]
    result=$?
    popd > /dev/null

    return "$result"
}

# Validate script arguments look sane.
function validate_arguments {
    # http://tldp.org/LDP/abs/html/exitcodes.html#EXITCODESREF
    if [ "$#" -ne 1 ]; then
      echo -e "Usage:\nbash $0 <site-root-directory>"
      exit 64
    else
      BASE=$1
      isDrupal $BASE
      if [ $? -ne 0 ]; then
        echo "$BASE does not look like a Drupal 8.x site root."
        exit 65
      fi
    fi
}

# Run the module tests groups
function run {
    local base=$1
    local vhost=$2
    local user=$3
    local output_dir=$4
    local tests=${@:5}
    local group

    echo -e "Running ${group} tests in $base on $vhost\n"
    cd "$base"
    mkdir -p "$output_dir"

    # Do not wrap $tests in "". It needs to be split on IFS.
    for test in $tests; do
       group="$test"
       echo "Running ${group} test group"

       # Testers might well have renamed run-tests.sh to run-tests.php,
       # so allow it with a wildcard.
       sudo -u "${user}"  php core/scripts/run-tests* \
        --php /usr/bin/php                  \
        --url $vhost                        \
        --sqlite /tmp/test.db               \
        --concurrency 1                     \
        --color                             \
        --verbose                           \
        --xml "$output_dir"                 \
        "$group"
       # shellcheck disable=SC2181
       if [ "$?" -ne 0 ]; then
         break;
       fi
    done
}

# Clean the Simpletest leftovers.
function clean {
    local base=$1
    local user=$2
    local output=$3

    echo "Cleaning leftover test results in $base"
    cd "$base"
    "${base}/../vendor/bin/drush" cr
    echo "Cleaning leftover test collections in MongoDB"
    "${base}/../vendor/bin/drush" mdct
    sudo -u "${user}" php core/scripts/run-tests* --clean
    echo "Removing results directory $output"
    rm -fr "$output"
}

# ---- Main logic -------------------------------------------------------------

validate_arguments $1
valid=$?
if [ "$valid" -ne 0 ]; then
  exit "$valid"
fi

run "$BASE" "$VHOST" "$WEB_USER" "$OUTPUT_DIR" "$TESTS"
clean "$BASE" "$WEB_USER" "$OUTPUT_DIR"
