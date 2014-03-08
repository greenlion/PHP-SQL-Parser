<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$parser = new PHPSQLParser();
$sql = "INSERT INTO newTablename SELECT field1, field2, field3 FROM oldTablename where field1 > 100";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue84a.serialized');
eq_array($p, $expected, 'INSERT ... SELECT .. FROM ... WHERE');


$parser = new PHPSQLParser();
$sql = "INSERT INTO newTablename (SELECT field1, field2, field3 FROM oldTablename where field1 > 100)";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue84b.serialized');
eq_array($p, $expected, 'INSERT ... (SELECT .. FROM ... WHERE)');


$parser = new PHPSQLParser();
$sql = "INSERT INTO newTablename (field1, field2, field3) VALUES (1, 2, 3)";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue84c.serialized');
eq_array($p, $expected, 'INSERT ... (cols) VALUES (values)');

?>
