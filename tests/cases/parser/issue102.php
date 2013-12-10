<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$sql = "SELECT IF(f = 0 || f = 1, 1, 0) FROM tbl";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue102.serialized');
eq_array($p, $expected, 'pipes as OR');
