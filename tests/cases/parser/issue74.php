<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$parser = new PHPSQLParser();

// DROP {DATABASE | SCHEMA} [IF EXISTS] db_name
$sql = "DROP DATABASE blah";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue74a.serialized');
eq_array($p, $expected, 'drop database statement');

$sql = "DROP SCHEMA blah";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue74b.serialized');
eq_array($p, $expected, 'drop schema statement');

$sql = "DROP DATABASE IF EXISTS blah";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue74c.serialized');
eq_array($p, $expected, 'drop database if exists statement');

$sql = "DROP SCHEMA IF EXISTS blah";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue74d.serialized');
eq_array($p, $expected, 'drop schema if exists statement');


// DROP [TEMPORARY] TABLE [IF EXISTS] tbl_name [, tbl_name] ... [RESTRICT | CASCADE]
$sql = "DROP TABLE blah1, blah2 RESTRICT";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue74e.serialized');
eq_array($p, $expected, 'drop table-list statement');

$sql = "DROP TEMPORARY TABLE IF EXISTS blah1, blah2 CASCADE";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'issue74f.serialized');
eq_array($p, $expected, 'drop temporary table-list if exists statement');

?>
