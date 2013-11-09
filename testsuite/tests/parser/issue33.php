<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (LIKE xyz)";
$parser->parse($sql);
$p = $parser->parsed;
print_r($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue33a.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with (LIKE)');

$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho LIKE xyz";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33b.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with LIKE');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE \"cachetable01\" (
\"sp_id\" varchar(240) DEFAULT NULL,
\"ro\" varchar(240) DEFAULT NULL,
\"balance\" varchar(240) DEFAULT NULL,
\"last_cache_timestamp\" varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1";
$parser->parse($sql);
$p = $parser->parsed;
print_r($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue33.serialized');
eq_array($p, $expected, 'CREATE TABLE statement');