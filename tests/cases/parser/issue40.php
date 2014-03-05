<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$parser = new PHPSQLParser();

$sql = "select a from t where x = \"a'b\\cd\" and y = 'ef\"gh'";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue40a.serialized');
eq_array($p, $expected, 'escaped characters 1');


$sql = "select a from t where x = \"abcd\" and y = 'efgh'";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue40b.serialized');
eq_array($p, $expected, 'escaped characters 2');

?>
