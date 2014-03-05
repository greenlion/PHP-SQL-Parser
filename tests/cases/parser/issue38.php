<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "SELECT * FROM `table` `t` WHERE ( ( UNIX_TIMESTAMP() + 3600 ) > `t`.`expires` ) ";
$parser = new PHPSQLParser();
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue38.serialized');
eq_array($p, $expected, 'function within WHERE and quoted table + quoted columns');

?>
