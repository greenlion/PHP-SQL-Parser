<?php

require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();


$sql = 'SELECT *
    FROM (t1 LEFT JOIN t2 ON t1.a=t2.a)
         LEFT JOIN t3
         ON t2.b=t3.b OR t2.b IS NULL';


$parser->parse($sql);
$p = $parser->parsed;
#echo $sql . "\n";
#print_r($p);

$result = serialize($p);
#file_put_contents('../r/left1.serialized',$result);
$good = file_get_contents('../r/left1.serialized');
ok($result == $good);

$sql = "SELECT * FROM t1 LEFT JOIN (t2, t3, t4)
                 ON (t2.a=t1.a AND t3.b=t1.b AND t4.c=t1.c)";

$parser->parse($sql);
$p = $parser->parsed;
#file_put_contents('../r/left2.serialized',$result);
$good = file_get_contents('../r/left2.serialized');
ok($result == $good);



