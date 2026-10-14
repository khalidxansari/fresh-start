#!/usr/bin/env bash
# Build a throwaway WordPress site with the Hunza Folk theme and sample content,
# using SQLite so no MySQL server is needed.
#
#   dev/setup-wp-test-site.sh /path/to/new/folder [port]
#   php -S 127.0.0.1:PORT -t /path/to/new/folder/site /path/to/new/folder/router.php
#
# Needs: php (with pdo_sqlite), composer, git, and network access to packagist.org
# and github.com. Admin login: admin / admin-test-pw.
set -euo pipefail

DEST=$(mkdir -p "${1:?usage: $0 <empty folder> [port]}" && cd "$1" && pwd)
PORT=${2:-8090}
REPO=$(cd "$(dirname "$0")/.." && pwd)
export COMPOSER_ALLOW_SUPERUSER=1 WP_CLI_PHP_ARGS="-d memory_limit=512M"

echo "==> WordPress core and WP-CLI (Composer, plugins disabled)"
mkdir -p "$DEST/tools"
( cd "$DEST/tools" && echo '{}' > composer.json &&
  composer require johnpbloch/wordpress-core wp-cli/wp-cli-bundle --no-plugins --no-interaction --no-progress -q )
WPCLI="bash $DEST/tools/vendor/wp-cli/wp-cli/bin/wp --path=$DEST/site --allow-root"

echo "==> SQLite database plugin (GitHub)"
git clone -q --depth 1 https://github.com/WordPress/sqlite-database-integration.git "$DEST/sqlite-src"

echo "==> Assemble the site"
rm -rf "$DEST/site"
cp -r "$DEST/tools/vendor/johnpbloch/wordpress-core" "$DEST/site"
mkdir -p "$DEST/site/wp-content/plugins" "$DEST/site/wp-content/database" "$DEST/site/wp-content/uploads"
# The plugin ships a symlink to its driver; copy the real files.
cp -rL "$DEST/sqlite-src/packages/plugin-sqlite-database-integration" "$DEST/site/wp-content/plugins/sqlite-database-integration"
PLUGIN="$DEST/site/wp-content/plugins/sqlite-database-integration"
sed -e "s|{SQLITE_IMPLEMENTATION_FOLDER_PATH}|$PLUGIN|g" -e "s|{SQLITE_PLUGIN}|sqlite-database-integration/load.php|g" \
  "$PLUGIN/db.copy" > "$DEST/site/wp-content/db.php"
ln -s "$REPO/wp-content/themes/hunza-folk" "$DEST/site/wp-content/themes/hunza-folk"
[ -f "$REPO/dev/preview/audio/tone-a.wav" ] && cp "$REPO/dev/preview/audio/tone-a.wav" "$DEST/site/wp-content/uploads/"

cat > "$DEST/site/wp-config.php" <<PHP
<?php
define('DB_NAME','wp'); define('DB_USER',''); define('DB_PASSWORD',''); define('DB_HOST',''); define('DB_CHARSET','utf8'); define('DB_COLLATE','');
foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as \$k) { define(\$k, 'test'); }
\$table_prefix = 'wp_';
define('WP_DEBUG', true); define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false);
define('WP_HOME', 'http://127.0.0.1:$PORT'); define('WP_SITEURL', 'http://127.0.0.1:$PORT');
define('DISABLE_WP_CRON', true); define('WP_HTTP_BLOCK_EXTERNAL', true); define('FS_METHOD', 'direct');
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
PHP

# Router for php -S so pretty permalinks work.
cat > "$DEST/router.php" <<'PHP'
<?php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p !== '/' && is_file(__DIR__ . '/site' . $p)) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/site/index.php';
chdir(__DIR__ . '/site');
require __DIR__ . '/site/index.php';
PHP

echo "==> Install, activate theme, import sample content"
$WPCLI core install --url="http://127.0.0.1:$PORT" --title="Hunza Folk" --admin_user=admin \
  --admin_password=admin-test-pw --admin_email=test@example.com --skip-email
$WPCLI theme activate hunza-folk
$WPCLI rewrite structure '/%postname%/' --hard 2>/dev/null || true
$WPCLI hunza import "$REPO/wp-content/themes/hunza-folk/content" --status=publish

echo
echo "Done. Start the site with:"
echo "  php -S 127.0.0.1:$PORT -t $DEST/site $DEST/router.php"
