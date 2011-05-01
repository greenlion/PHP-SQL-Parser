<?php

require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();


$sql = 'SELECT a.field1, b.field1, c.field1
  FROM tablea a 
  LEFT JOIN tableb b ON b.ida = a.id
  LEFT JOIN tablec c ON c.idb = b.id;';

echo $sql . "\n";

$parser->parse($sql);
$p = $parser->parsed;
print_r($p);

$sql = 'SELECT a.field1, b.field1, c.field1
  FROM tablea a 
  LEFT OUTER JOIN tableb b ON b.ida = a.id
  LEFT OUTER JOIN tablec c ON c.idb = b.id;';

echo $sql . "\n";

$parser->parse($sql);
$p = $parser->parsed;
print_r($p);



