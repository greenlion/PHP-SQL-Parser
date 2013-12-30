<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho () AUTO_INCREMENT = 1 DEFAULT CHARACTER SET _utf8 PASSWORD 'test123'";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'tableoptions1.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with table options');

// TODO: the union statement within the CREATE TABLE has not been parsed
$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho () UNION (tableA, tableB,tableC)";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'tableoptions2.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with UNION table option');

?>