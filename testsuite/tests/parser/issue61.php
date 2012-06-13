<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

// parser can not handle functions or expressions within order-by
// it seems to be a problem since REV 142

$sql = "SELECT lcase(dummy.b) FROM dummy ORDER BY dummy.a, LCASE(dummy.b)";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;

print_r($p);
echo serialize($p);
