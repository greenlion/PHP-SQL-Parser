<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");


// TODO: the keywords ON DUPLICATE KEY UPDATE are not parsed
$sql = 'INSERT INTO table (a,b,c) VALUES (1,2,3) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), c=3;';
$parser = new PHPSQLParser();
$p = $parser->parse($sql);
$expected = getExpectedValue(dirname(__FILE__), 'issue90.serialized');
eq_array($p, $expected, 'on duplicate key problem');

?>