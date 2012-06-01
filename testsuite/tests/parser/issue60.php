<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$sql = "SELECT id, password
FROM users";

$parser = new PHPSQLParser($sql, false);
$p = $parser->parsed;

print_r($p);

$expected = getExpectedValue(dirname(__FILE__), 'issue60.serialized');
eq_array($p, $expected, 'wrong usage of keyword password');
