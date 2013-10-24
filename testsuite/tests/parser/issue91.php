<?php
require_once(dirname(__FILE__) . "/../../../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$parser = new PHPSQLParser();

# TODO: not solved
$sql = 'SELECT DISTINCT colA * colB From test t';
$p = $parser->parse($sql);
$expected = getExpectedValue(dirname(__FILE__), 'issue91.serialized');
eq_array($p, $expected, 'distinct select');
