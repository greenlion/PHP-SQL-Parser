<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

// not solved, charsets are not possible at the moment

$parser = new PHPSQLParser();

$sql = "SELECT _utf8'hi'";
$parser->parse($sql, false);
$p = $parser->parsed;

print_r($p);
echo serialize($p);

$expected = getExpectedValue(dirname(__FILE__), 'issue50.serialized');
eq_array($p, $expected, 'does not die if query contains _utf8');