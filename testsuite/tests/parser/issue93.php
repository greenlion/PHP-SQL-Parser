<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

# TODO: not solved
$sql="select 1 as `a` order by `a`";

try {
	$parser = new PHPSQLParser($sql, true);
} catch (UnableToCalculatePositionException $e) {}

$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue93.serialized');
eq_array($p, $expected, 'simple query');
