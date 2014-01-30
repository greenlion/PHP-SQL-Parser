<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$sql = "(SELECT x FROM table) ORDER BY x";
$parser = new PHPSQLParser();
$p = $parser->parse($sql);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue117.sql', false);
ok($created === $expected, 'parentheses on the first position of statement');

?>
