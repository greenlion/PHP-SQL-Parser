<?php
require_once(dirname(__FILE__) . '/../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../test-more.php');

# not solved
$parser = new PHPSQLParser();
$sql = "UPDATE user SET lastlogin = 7, x = 3";
$parser->parse($sql);
$p = $parser->parsed;
print_r($p);
$expected = getExpectedValue('issue32.serialized');
eq_array($p, $expected, 'update with keyword as table');
