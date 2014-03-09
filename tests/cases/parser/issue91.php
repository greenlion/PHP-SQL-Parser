<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = 'SELECT DISTINCT colA * colB From test t';
$parser = new PHPSQLParser();
$p = $parser->parse($sql);
$expected = getExpectedValue(dirname(__FILE__), 'issue91.serialized');
eq_array($p, $expected, 'distinct select');

?>
