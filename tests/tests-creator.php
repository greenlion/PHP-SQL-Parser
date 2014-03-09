<?php

/**
 * execute all tests
 */
$start = microtime(true);
require_once dirname(__FILE__) . '/cases/creator/asc.php';
require_once dirname(__FILE__) . '/cases/creator/count_distinct.php';
require_once dirname(__FILE__) . '/cases/creator/delete.php';
require_once dirname(__FILE__) . '/cases/creator/function.php';
require_once dirname(__FILE__) . '/cases/creator/inlist.php';
require_once dirname(__FILE__) . '/cases/creator/insert.php';
require_once dirname(__FILE__) . '/cases/creator/issue33.php';
require_once dirname(__FILE__) . '/cases/creator/issue57.php';
require_once dirname(__FILE__) . '/cases/creator/issue58.php';
require_once dirname(__FILE__) . '/cases/creator/issue63.php';
require_once dirname(__FILE__) . '/cases/creator/issue66.php';
require_once dirname(__FILE__) . '/cases/creator/issue76.php';
require_once dirname(__FILE__) . '/cases/creator/issue78.php';
require_once dirname(__FILE__) . '/cases/creator/issue79.php';
require_once dirname(__FILE__) . '/cases/creator/issue83.php';
require_once dirname(__FILE__) . '/cases/creator/issue85.php';
require_once dirname(__FILE__) . '/cases/creator/issue86.php';
require_once dirname(__FILE__) . '/cases/creator/issue87.php';
require_once dirname(__FILE__) . '/cases/creator/issue88.php';
require_once dirname(__FILE__) . '/cases/creator/issue92.php';
require_once dirname(__FILE__) . '/cases/creator/issue94.php';
require_once dirname(__FILE__) . '/cases/creator/issue98.php';
require_once dirname(__FILE__) . '/cases/creator/issue100.php';
require_once dirname(__FILE__) . '/cases/creator/issue101.php';
require_once dirname(__FILE__) . '/cases/creator/issue102.php';
require_once dirname(__FILE__) . '/cases/creator/issue104.php';
require_once dirname(__FILE__) . '/cases/creator/issue105.php';
require_once dirname(__FILE__) . '/cases/creator/issue106.php';
require_once dirname(__FILE__) . '/cases/creator/issue110.php';
require_once dirname(__FILE__) . '/cases/creator/issue112.php';
require_once dirname(__FILE__) . '/cases/creator/issue117.php';
require_once dirname(__FILE__) . '/cases/creator/issue118.php';
require_once dirname(__FILE__) . '/cases/creator/issue121.php';
require_once dirname(__FILE__) . '/cases/creator/issue123.php';
require_once dirname(__FILE__) . '/cases/creator/issue124.php';
require_once dirname(__FILE__) . '/cases/creator/issue126.php';
require_once dirname(__FILE__) . '/cases/creator/issue127.php';
require_once dirname(__FILE__) . '/cases/creator/issue129.php';
require_once dirname(__FILE__) . '/cases/creator/issue130.php';
require_once dirname(__FILE__) . '/cases/creator/issue131.php';
require_once dirname(__FILE__) . '/cases/creator/issue132.php';
require_once dirname(__FILE__) . '/cases/creator/join.php';
require_once dirname(__FILE__) . '/cases/creator/left.php';
require_once dirname(__FILE__) . '/cases/creator/magnus.php';
require_once dirname(__FILE__) . '/cases/creator/tableexpr.php';
require_once dirname(__FILE__) . '/cases/creator/update.php';
require_once dirname(__FILE__) . '/cases/creator/where.php';
echo "processing tests within: " .  (microtime(true) - $start) . " seconds\n";

?>