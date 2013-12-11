<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$sql = "show columns from `foo.bar`";
$parser = new PHPSQLParser($sql);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue78a.sql', false);
ok($created === $expected, 'show columns from');

$sql = "show CREATE DATABASE `foo`";
$parser = new PHPSQLParser($sql);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue78b.sql', false);
ok($created === $expected, 'show create database');

$sql = "show DATABASES LIKE '%bar%'";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue78c.sql', false);
ok($created === $expected, 'show databases like');

$sql = "SHOW ENGINE foo STATUS";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue78d.sql', false);
ok($created === $expected, 'show engine status');

$sql = "SHOW FULL COLUMNS FROM `foo.bar` FROM hohoho LIKE '%xmas%'";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue78e.sql', false);
ok($created === $expected, 'show full columns from like');

?>