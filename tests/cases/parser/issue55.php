<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

# partial SQL statements
# TODO: table.c should be handled as table and c
$parser = new PHPSQLParser();
$sql = "GROUP BY a, b, table.c";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue55a.serialized');
eq_array($p, $expected, 'partial SQL statement - group by clause');


$sql = "ORDER BY a ASC, b DESC, table.c ASC";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue55b.serialized');
eq_array($p, $expected, 'partial SQL statement - order by clause');

?>