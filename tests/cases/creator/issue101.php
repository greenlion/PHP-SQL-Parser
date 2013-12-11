<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$sql = "SELECT tab.col AS `tab.col`, tab2.col AS `tab2.col` FROM tab, tab2";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue101.sql', false);
ok($created === $expected, 'alias with quotes');

?>