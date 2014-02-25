<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "delete from testA as a where a.id = 1";
$parser = new PHPSQLParser();
$p = $parser->parse($sql);
$expected = getExpectedValue(dirname(__FILE__), 'delete1.serialized');
eq_array($p, $expected, 'simple delete statement');

$sql = "DELETE t1, t2 FROM t1 INNER JOIN t2 INNER JOIN t3 WHERE t1.id=t2.id AND t2.id=t3.id";
$parser = new PHPSQLParser();
$p = $parser->parse($sql);
$expected = getExpectedValue(dirname(__FILE__), 'delete2.serialized');
eq_array($p, $expected, 'multi-table delete statement');

$sql = "DELETE FROM t1.*, t2.* USING t1 INNER JOIN t2 INNER JOIN t3 WHERE t1.id=t2.id AND t2.id=t3.id;";
$parser = new PHPSQLParser();
$p = $parser->parse($sql);
$expected = getExpectedValue(dirname(__FILE__), 'delete3.serialized');
eq_array($p, $expected, 'multi-table delete statement with using');

?>
