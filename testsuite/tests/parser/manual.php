<?php

require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

# thats an issue written as comment into the ParserManual...
# the ON clause base_expr contains ")", which fails in PositionCalculator->findPositionWithinString()
$sql = "SELECT FROM some_table a LEFT JOIN another_table AS b ON FIND_IN_SET(a.id, b.ids_collection)";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;

print_r($p);

$expected = getExpectedValue(dirname(__FILE__), 'manual.serialized');
eq_array($p, $expected, 'no select expression');


