<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho () AUTO_INCREMENT = 1 DEFAULT CHARACTER SET _utf8 PASSWORD 'test123'";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'tableoptions.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with table options');