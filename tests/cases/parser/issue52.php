<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$parser = new PHPSQLParser();

$sql = "SELECT a FROM b WHERE c IN (1, 2)";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue52.serialized');
eq_array($p, $expected, 'should not die if query contains IN clause');

?>
