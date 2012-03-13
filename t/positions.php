<?php
require_once(dirname(__FILE__) . "/../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../test-more.php");

$parser = new PHPSQLParser();

$sql = 'SELECT colA hello From test t';
$p = $parser->parse($sql, true);

print_r($p);