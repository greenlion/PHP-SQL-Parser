<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$query = "create unique index i1 using BTREE on t1 (c1(5) DESC, `col 2`(8) ASC) ALGORITHM=DEFAULT using hash LOCK=SHARED";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue131.sql', false);
ok($created === $expected, 'CREATE INDEX statement');