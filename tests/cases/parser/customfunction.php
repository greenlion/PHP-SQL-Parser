<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

try {
    $sql = "SELECT PERCENTILE(xyz, 90) as percentile from some_table";
    $parser = new PHPSQLParser();
    $parser->addCustomFunction("percentile");
    $p = $parser->parse($sql, true);
} catch (Exception $e) {
    $p = array();
}
ok($p['SELECT'][0]['expr_type'] === ExpressionType::CUSTOM_FUNCTION, 'custom function within SELECT clause');
?>
