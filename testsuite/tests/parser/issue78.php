<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$parser = new PHPSQLParser();

$sql = "EXPLAIN EXTENDED SELECT * FROM foo.bar";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78a.serialized');
eq_array($p, $expected, 'explain select');

$sql = "EXPLAIN SELECT * FROM foo.bar";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78b.serialized');
eq_array($p, $expected, 'explain select');

$sql = "EXPLAIN foo.bar";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78c.serialized');
eq_array($p, $expected, 'explain table');

$sql = "DESCRIBE foo.bar";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78d.serialized');
eq_array($p, $expected, 'describe table');
