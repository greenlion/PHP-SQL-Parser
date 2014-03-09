<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "select webid, floor(iz/2.) as fl from MDR1.Tweb512 as w where w.webid < 100";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue97.serialized');
eq_array($p, $expected, 'incomplete floating point numbers');

?>
