<?php

require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../../php-sql-creator.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$sql = "INSERT INTO newTablename (SELECT * FROM oldTablename);";
$parser = new PHPSQLParser($sql, true);
print_r($parser->parsed);

$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
print_r($created);

$expected = getExpectedValue(dirname(__FILE__), 'issue83.sql', false);
ok($created === $expected, 'Subselect instead of value list.');