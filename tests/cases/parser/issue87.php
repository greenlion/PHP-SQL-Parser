<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$parser = new PHPSQLParser();
$sql = "RENAME TABLE a TO b";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue87a.serialized');
eq_array($p, $expected, 'rename table');

$parser = new PHPSQLParser();
$sql = "RENAME TABLE a TO b, `c` to `a`, foo.bar to hello.world";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue87b.serialized');
eq_array($p, $expected, 'rename multiple tables');

?>
