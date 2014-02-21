<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$query = "CREATE TABLE t1 (c1 CHAR(3),c2 CHAR(3),KEY(c1))";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue123.sql', false);
ok($created === $expected, 'create table with key column');