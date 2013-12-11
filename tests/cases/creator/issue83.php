<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

#TODO: not solved, the parser doesn't handle subselects here
$sql = "INSERT INTO newTablename (SELECT * FROM oldTablename);";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue83.sql', false);
ok($created === $expected, 'Subselect instead of value list.');

?>