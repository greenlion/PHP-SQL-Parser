<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$parser = new PHPSQLParser();

$sql = "INSERT INTO test (`name`, `test`) VALUES ('Hello this is what happens\n when new lines are involved', '')";
$parser->parse($sql);
$p = $parser->parsed;
print_r($p);
$expected = getExpectedValue('issue37a.serialized');
eq_array($p, $expected, 'INSERT statement with newline character');
