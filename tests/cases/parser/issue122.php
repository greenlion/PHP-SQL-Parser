<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "UPDATE db.`tablename` SET a=1";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue122.serialized');
eq_array($p, $expected, 'no_quotes property has been corrected');

?>
