<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../../php-sql-creator.php');
require_once(dirname(__FILE__) . '/../../test-more.php');


$sql = "select (some_field = 'string') from table;";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue88.sql', false);
ok($created === $expected, 'Expression subtree should handle bracket_expressions.');
