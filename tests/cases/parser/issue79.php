<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "SELECT * FROM `users` WHERE id_user=@ID_USER";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue79a.serialized');
eq_array($p, $expected, 'user variables');

$sql = "SELECT (@aa:=id) AS a, (@aa+3) AS b FROM tbl_name HAVING b=5";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue79b.serialized');
eq_array($p, $expected, 'user variables with alias and assignment');

?>
