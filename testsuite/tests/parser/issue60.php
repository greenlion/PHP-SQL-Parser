<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

# not solved: the resultset defines password as function instead of colref
$sql = "SELECT id, password
FROM users";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
ok($p['SELECT'][1]['position'] === 11, 'wrong usage of keyword password');
ok($p['SELECT'][1]['expr_type'] === 'colref', 'password should be a colref here');