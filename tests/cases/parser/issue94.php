<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";


$sql = 'SELECT DATE_ADD(NOW(), INTERVAL 1 MONTH) AS next_month';
$parser = new PHPSQLParser();
$p = $parser->parse($sql);
$expected = getExpectedValue(dirname(__FILE__), 'issue94.serialized');
eq_array($p, $expected, 'date_add()');

?>
