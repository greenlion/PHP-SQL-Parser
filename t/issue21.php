<?php
require_once(dirname(__FILE__) . "/../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../test-more.php");

$parser = new PHPSQLParser();

$sql = 'SELECT  SUM( 10 ) as test FROM account';
$p = $parser->parse($sql);
$expected = getExpectedValue('issue21.serialized');
eq_array($p, $expected, 'only space characters within SQL statement');


$sql = 'SELECT 	SUM( 10 ) as test FROM account';
$p = $parser->parse($sql);
$expected = getExpectedValue('issue21.serialized'); // should be the same as above
eq_array($p, $expected, 'tab character within SQL statement');
