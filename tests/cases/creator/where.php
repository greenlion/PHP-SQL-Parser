<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';


$sql = "SELECT * FROM `table` `t` WHERE ( ( UNIX_TIMESTAMP() + 3600 ) > `t`.`expires` ) ";
$parser = new PHPSQLParser($sql);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'where.sql', false);
ok($created === $expected, 'expressions with function within WHERE clause');

?>