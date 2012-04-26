<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$parser = new PHPSQLParser();

// not solved

$sql = 'SELECT [CONCAT(a, b)] from c';
$parser->parse($sql, false);
$p = $parser->parsed;

print_r($p);

$expected = getExpectedValue(dirname(__FILE__), 'issue44.serialized');
eq_array($p, $expected, 'issue 44 position problem');

