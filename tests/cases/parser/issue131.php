<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "create unique index i1 using BTREE on t1 (c1(5) DESC, `col 2`(8) ASC) ALGORITHM=DEFAULT using hash LOCK=SHARED";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue131.serialized');
eq_array($p, $expected, 'create index statement');

?>
