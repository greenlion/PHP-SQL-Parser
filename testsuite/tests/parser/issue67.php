<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$sql = "SET SESSION group_concat_max_len = @@max_allowed_packet";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue67.serialized');
eq_array($p, $expected, '@ character after operator should not fail.');
