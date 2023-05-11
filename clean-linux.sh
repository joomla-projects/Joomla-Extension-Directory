echo 'Script to Clear Old Build Data on Linux'
rm -f composer.lock
rm -rf dist
rm -rf vendor
rm -f src\administrator\components\com_jed\composer.lock
rm -rf src\administrator\components\com_jed\vendor

