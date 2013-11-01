<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

# TODO: there are warnings within the parser
$sql = "SELECT * FROM `table` `t` WHERE ( ( UNIX_TIMESTAMP() + 3600 ) > `t`.`expires` ) ";
$parser = new PHPSQLParser();
$parser->parse($sql);
$p = $parser->parsed;
print_r($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue38.serialized');
eq_array($p, $expected, 'function within WHERE and quoted table + quoted columns');
