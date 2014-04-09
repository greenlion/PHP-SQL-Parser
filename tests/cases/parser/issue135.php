<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "SELECT x,y,z FROM tableA WHERE x<5 GROUP BY STD(y)";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue135.serialized');
eq_array($p, $expected, 'STD must be an aggregate function');

?>
