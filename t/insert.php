<?php
require_once(dirname(__FILE__) . "/../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../test-more.php");

$parser = new PHPSQLParser();

$sql = "insert into SETTINGS_GLOBAL (stg_value,stg_name) values('','force_ssl')";
$p = $parser->parse($sql);

print_r($p);
