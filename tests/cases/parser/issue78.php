<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$parser = new PHPSQLParser();

$sql = "EXPLAIN EXTENDED SELECT * FROM foo.bar";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78a.serialized');
eq_array($p, $expected, 'explain select');

$sql = "EXPLAIN SELECT * FROM foo.bar";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78b.serialized');
eq_array($p, $expected, 'explain select');

$sql = "EXPLAIN foo bar";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78c.serialized');
eq_array($p, $expected, 'explain table');

$sql = "DESCRIBE foo bar%";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78d.serialized');
eq_array($p, $expected, 'describe table');

$sql = "DESC FORMAT = JSON DELETE FROM tableA WHERE x=1";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue78e.serialized');
eq_array($p, $expected, 'describe delete');

?>