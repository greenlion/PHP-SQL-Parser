<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$query = "SELECT t1.c1, t2.c2 FROM t1 LEFT JOIN t2 ON (LEFT(t1.c2,6) = t2.c1)";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue124.sql', false);
ok($created === $expected, 'ref clause with function');