<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$query = "select t1.*
from Table1 t1
STRAIGHT_JOIN  Table2 t2
on t1.CommonID = t2.CommonID
where t1.FilterID = 1";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue134.sql', false);
ok($created === $expected, 'a straight_join within from clause');

?>
