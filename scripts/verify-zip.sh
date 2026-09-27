#!/bin/sh
set -eu

if [ "${1:-}" != '--inside' ]; then
  docker compose run --rm --no-deps --entrypoint sh cli \
    /var/www/html/wp-content/plugins/funnelkit-lite/scripts/verify-zip.sh --inside
  exit
fi

project=/var/www/html/wp-content/plugins/funnelkit-lite
test_root=/tmp/funnelkit-zip-check
wp_test() { wp --path="$test_root" "$@"; }
mkdir -p "$test_root"
wp_test core download --version=6.8 --skip-content
wp_test config create --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=db --dbprefix=fkziptest_
wp_test core install --url=http://zip-test.invalid --title='ZIP Test' --admin_user=zipdemo --admin_password=zipdemo-pass --admin_email=zipdemo@example.test --skip-email
# A previous interrupted run can leave active-plugin metadata in these test tables.
wp_test option update active_plugins '[]' --format=json
wp_test plugin install "$project/dist/funnelkit-lite.zip" --activate
wp_test plugin is-active funnelkit-lite
wp_test eval-file "$project/tests/zip-lifecycle.php" active
wp_test plugin uninstall funnelkit-lite --deactivate
wp_test eval-file "$project/tests/zip-lifecycle.php" removed
wp_test eval-file "$project/tests/zip-lifecycle.php" cleanup
