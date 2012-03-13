<?php
require_once(dirname(__FILE__) . '/../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../test-more.php');

$parser = new PHPSQLParser();
$sql = "SELECT * FROM cache as t";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue('issue34.serialized');
eq_array($p, $expected, 'keyword CACHE as tablename');
