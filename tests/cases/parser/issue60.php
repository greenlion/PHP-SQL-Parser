<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "SELECT id, password
FROM users";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
ok($p['SELECT'][1]['position'] === 11, 'wrong usage of keyword password');
ok($p['SELECT'][1]['expr_type'] === 'colref', 'password should be a colref here');


$sql = "SET PASSWORD = PASSWORD('haha')";
$parser = new PHPSQLParser($sql, true);
$p = $parser->parsed;
ok($p['SET'][0]['sub_tree'][0]['expr_type'] === 'colref', 'set the password column of mysql.user');
ok($p['SET'][0]['sub_tree'][2]['expr_type'] === 'function', 'set password value');

?>
