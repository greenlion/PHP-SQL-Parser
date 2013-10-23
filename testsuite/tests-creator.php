<?php

/**
 * execute all tests
 */
$start = microtime(true);
require_once(dirname(__FILE__) . '/tests/creator/asc.php');
require_once(dirname(__FILE__) . '/tests/creator/count_distinct.php');
require_once(dirname(__FILE__) . '/tests/creator/function.php');
require_once(dirname(__FILE__) . '/tests/creator/inlist.php');
require_once(dirname(__FILE__) . '/tests/creator/insert.php');
require_once(dirname(__FILE__) . '/tests/creator/issue57.php');
require_once(dirname(__FILE__) . '/tests/creator/issue58.php');
require_once(dirname(__FILE__) . '/tests/creator/issue63.php');
require_once(dirname(__FILE__) . '/tests/creator/issue66.php');
require_once(dirname(__FILE__) . '/tests/creator/issue76.php');
require_once(dirname(__FILE__) . '/tests/creator/issue83.php');
require_once(dirname(__FILE__) . '/tests/creator/issue85.php');
require_once(dirname(__FILE__) . '/tests/creator/issue86.php');
require_once(dirname(__FILE__) . '/tests/creator/issue88.php');
require_once(dirname(__FILE__) . '/tests/creator/issue92.php');
require_once(dirname(__FILE__) . '/tests/creator/join.php');
require_once(dirname(__FILE__) . '/tests/creator/left.php');
require_once(dirname(__FILE__) . '/tests/creator/tableexpr.php');
require_once(dirname(__FILE__) . '/tests/creator/update.php');
require_once(dirname(__FILE__) . '/tests/creator/where.php');
require_once(dirname(__FILE__) . '/tests/creator/magnus.php');
echo "processing tests within: " .  (microtime(true) - $start) . " seconds\n";