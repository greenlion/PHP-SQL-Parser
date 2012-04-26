<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$parser = new PHPSQLParser();

// not solved

$sql = 'SELECT a from b left join c on c.a = b.a and (c.b. = b.b) where a.a > 1';
$parser->parse($sql, false);
$p = $parser->parsed;

print_r($p);


$parser->parse($sql, true);
$p = $parser->parsed;

print_r($p);

$expected = getExpectedValue(dirname(__FILE__), 'issue45.serialized');
eq_array($p, $expected, 'issue 45 position problem');

