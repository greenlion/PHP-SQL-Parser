<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$sql = "SELECT * FROM `model` WHERE `marker`='this_model' ORDER BY `test`";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue80a.serialized');
eq_array($p, $expected, 'quoted column names');

$sql = "SELECT x+3 `test` FROM `model` WHERE `marker`='this_model' ORDER BY `test`";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue80b.serialized');
eq_array($p, $expected, 'quoted names and aliases');


?>