<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$sql = "SELECT AVG(2.0 * foo) FROM bar";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue76a.sql', false);
ok($created === $expected, 'Expressions in functions and aggregates.');

$sql = "SELECT AVG(2.0 * foo, x) FROM bar";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue76b.sql', false);
ok($created === $expected, 'Expressions in functions and aggregates with additional parameters.');

?>