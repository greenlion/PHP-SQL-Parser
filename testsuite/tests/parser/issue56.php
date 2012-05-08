<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

# optimizer/index hints

$parser = new PHPSQLParser();
$sql = "insert /* +APPEND */ into TableName (Col1,col2) values(1,'pol')";
$parser->parse($sql);
$p = $parser->parsed;

print_r($p);

$expected = getExpectedValue(dirname(__FILE__), 'issue56a.serialized');
eq_array($p, $expected, 'optimizer hint within INSERT');
