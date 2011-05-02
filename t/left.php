<?php

require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();


$sql = 'SELECT a.field1, b.field1, c.field1
  FROM tablea a 
  LEFT JOIN tableb b ON b.ida = a.id
  LEFT JOIN tablec c ON c.idb = b.id;';

$parser->parse($sql);
$p = $parser->parsed;

$result = serialize($p);
#file_put_contents('../r/left1.serialized',$result);
$good = file_get_contents('../r/left1.serialized');
ok($result == $good);

$sql = 'SELECT a.field1, b.field1, c.field1
  FROM tablea a 
  LEFT OUTER JOIN tableb b ON b.ida = a.id
  RIGHT JOIN tablec c ON c.idb = b.id
  JOIN tabled d USING (d_id);';


$parser->parse($sql);
$p = $parser->parsed;
#file_put_contents('../r/left2.serialized',$result);
$good = file_get_contents('../r/left2.serialized');
ok($result == $good);



