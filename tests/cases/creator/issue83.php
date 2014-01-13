<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$parser = new PHPSQLParser();
$sql = "INSERT INTO newTablename SELECT field1, field2, field3 FROM oldTablename where field1 > 100";
$parser->parse($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue83a.sql', false);
ok($created === $expected, 'INSERT ... SELECT .. FROM ... WHERE');

$parser = new PHPSQLParser();
$sql = "INSERT INTO newTablename (SELECT field1, field2, field3 FROM oldTablename where field1 > 100)";
$parser->parse($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue83b.sql', false);
ok($created === $expected, 'INSERT ... (SELECT .. FROM ... WHERE)');

$parser = new PHPSQLParser();
$sql = "INSERT INTO newTablename (field1, field2, field3) VALUES (1, 2, 3)";
$parser->parse($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue83c.sql', false);
ok($created === $expected, 'INSERT ... (cols) VALUES (values)');

?>