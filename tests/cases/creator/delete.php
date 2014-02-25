<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';


$sql = "DELETE QUICK t1, t2 FROM t1 INNER JOIN t2 INNER JOIN t3 WHERE t1.id=t2.id AND t2.id=t3.id";
$parser = new PHPSQLParser($sql);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'delete.sql', false);
ok($created === $expected, 'a multi-table delete statement');

?>