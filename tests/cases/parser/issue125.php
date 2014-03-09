<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

// TODO: this issue has not been solved
$sql = "select t1.* from t1 left outer join t2 on left(t1.c1,6) = t2.c2";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue125.serialized');
eq_array($p, $expected, 'LEFT as function within the ref clause');

?>
