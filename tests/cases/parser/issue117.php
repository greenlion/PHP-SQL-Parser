<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

// TODO: not solved, ORDER BY has been lost
$sql = "(((SELECT x FROM table)) ORDER BY x)";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue117.serialized');
eq_array($p, $expected, 'parentheses on the first position of statement');

?>
