<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$query = "select * from t1 order by c2-c1";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue130.sql', false);
ok($created === $expected, 'expressions within the ORDER-BY clause');