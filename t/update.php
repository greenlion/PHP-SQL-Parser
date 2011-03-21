<?php
require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();
$sql = "UPDATE table1 SET field1='foo' WHERE field2='bar' AND id=(SELECT if FROM test1 t where t.field1=(SELECT id from test2 t2 where t2.field = 'foo'))";
$parser->parse($sql);
$p = $parser->parsed;

$result = serialize($p);
#file_put_contents('../r/update.serialized',$serialized);
$good = file_get_contents('../r/update.serialized');
ok($result == $good);
