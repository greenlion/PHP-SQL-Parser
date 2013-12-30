<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

// TODO: we should store the object type under the RENAME field
// because we can also rename users
// after that we should use sub_tree to split all relevant keywords for the
// position calculator
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