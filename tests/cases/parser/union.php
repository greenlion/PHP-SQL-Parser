<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$parser = new PHPSQLParser();

$sql = 'SELECT colA From test a
union
SELECT colB from test 
as b';
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'union1.serialized');
eq_array($p, $expected, 'simple union');


// TODO: the order-by clause has not been parsed
$sql = '(SELECT colA From test a)
        union all
        (SELECT colB from test b) order by 1';
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'union2.serialized');
eq_array($p, $expected, 'mysql union with order-by');

?>
