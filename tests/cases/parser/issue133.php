<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "SELECT x,y,z FROM MDR1.Particles85 WHERE RAND(154321) <= 2.91E-5";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue133a.serialized');
eq_array($p, $expected, 'scientific numbers');

$sql = "SELECT x,y,z FROM MDR1.Particles85 WHERE RAND(154321) <= 2E+5";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue133b.serialized');
eq_array($p, $expected, 'scientific numbers');

?>
