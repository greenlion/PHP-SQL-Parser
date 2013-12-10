<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$sql = "show columns from `foo.bar`";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'show1.serialized');
eq_array($p, $expected, 'show columns from');

$sql = "show CREATE DATABASE `foo`";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'show2.serialized');
eq_array($p, $expected, 'show create database');

$sql = "show DATABASES LIKE '%bar%'";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'show3.serialized');
eq_array($p, $expected, 'show databases like');

$sql = "SHOW ENGINE foo STATUS";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'show4.serialized');
eq_array($p, $expected, 'show engine status');

$sql = "SHOW FULL COLUMNS FROM `foo.bar` FROM hohoho LIKE '%xmas%'";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'show5.serialized');
eq_array($p, $expected, 'show full columns from like');

?>