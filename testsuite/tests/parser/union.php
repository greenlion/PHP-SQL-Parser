<?php
require_once(dirname(__FILE__) . "/../../../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$parser = new PHPSQLParser();

$sql = 'SELECT colA From test a
union
SELECT colB from test 
as b';
$p = $parser->parse($sql, true);
$expected = getExpectedValue('union1.serialized');
eq_array($p, $expected, 'simple union');

$sql = '(SELECT colA From test a)
        union all
        (SELECT colB from test b)';
#order by 1  # this will not parsed
$p = $parser->parse($sql, true);
$expected = getExpectedValue('union2.serialized');
eq_array($p, $expected, 'mysql union');
