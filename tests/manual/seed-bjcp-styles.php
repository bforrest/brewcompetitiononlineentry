<?php
/**
 * Seeds the BJCP 2025 (cider) and BJCP 2026 (mead) style rows into a database - the same two
 * guarded includes update/run_update.php runs on a version bump, without running the whole update.
 * Idempotent: a second run adds nothing.
 *
 *   docker cp tests/manual/seed-bjcp-styles.php bluebonnet-testing-web-1:/tmp/seed-bjcp-styles.php
 *   docker exec bluebonnet-testing-web-1 php /tmp/seed-bjcp-styles.php bcoem_scratch   # safe copy
 *   docker exec bluebonnet-testing-web-1 php /tmp/seed-bjcp-styles.php bcoem           # the real stack DB
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$database_name = $argv[1] ?? 'bcoem_scratch';
$root = '/var/www/html/';
define('LIB', $root.'lib/'); define('UPDATE', $root.'update/'); define('CONFIG', '/tmp/seed_cfg_'.preg_replace('/\W/', '', $database_name).'/'); define('HOSTED', FALSE);
@mkdir(CONFIG);
file_put_contents(CONFIG.'config.php', "<?php\n\$hostname='db'; \$username='bcoem'; \$password='bcoem_password'; \$database='".addslashes($database_name)."'; \$prefix=''; \$connection = new mysqli(\$hostname,\$username,\$password,\$database,3306); mysqli_query(\$connection,\"SET sql_mode=''\");\n");
if (!file_exists(CONFIG.'MysqliDb.php')) copy($root.'site/MysqliDb.php', CONFIG.'MysqliDb.php');
require CONFIG.'config.php';
require CONFIG.'MysqliDb.php';
require_once LIB.'update.lib.php';
$db_conn = new MysqliDb($connection);
$output_run_update = ""; $error_count = 0; $prefix = '';

if (!check_new_style_version("C1","E","Spanish Cider","BJCP2025")) include UPDATE.'styles_bjcp_2025_update.php';
if (!check_new_style_version("M2","E","Other Fruit Mead","BJCP2026")) include UPDATE.'styles_bjcp_2026_update.php';
echo "database: $database_name\n".($output_run_update === "" ? "nothing to add - already seeded\n" : strip_tags(str_replace('</li>', "\n", $output_run_update)))."errors: $error_count\n";
