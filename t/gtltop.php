<?php
require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();
$sql = 'SELECT c1
          from some_table an_alias
	where d>0 and d>1 and d>-1 and d<2 and d<>0  or d <> 0 or d<>"test1" or d <> "test2";';
$parser->parse($sql);
$p = $parser->parsed;
$result=serialize($p);
#$fh = fopen('../r/gtltop.serialized', 'w');
#fputs($fh, $result);
#fclose($fh);

ok($result == file_get_contents('../r/gtltop.serialized'));

