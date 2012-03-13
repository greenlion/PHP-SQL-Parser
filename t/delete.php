<?php
require_once(dirname(__FILE__) . "/../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../test-more.php");

# not solved

$parser = new PHPSQLParser();

$sql = "delete from testA as a where a.id = 1";
$p = $parser->parse($sql);
print_r($p);
$expected = getExpectedValue('delete1.serialized');
eq_array($p, $expected, 'simple delete statement');

