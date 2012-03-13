<?php
require_once(dirname(__FILE__) . '/../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../test-more.php');

$parser = new PHPSQLParser();

$sql = "UPDATE table1 SET field1='foo' WHERE field2='bar' AND id=(SELECT id FROM test1 t where t.field1=(SELECT id from test2 t2 where t2.field = 'foo'))";
$parser->parse($sql);
$p = $parser->parsed;

# we miss a trailing ")" on the last subquery
$expected = getExpectedValue('update.serialized');
eq_array($p, $expected, 'update with a sub-select');
