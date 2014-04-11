<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

try {
    $sql = "SELECT PERCENTILE(xyz, 90) as percentile from some_table";
    $parser = new PHPSQLParser();
    $parser->add_custom_function("percentile");
    $p = $parser->parse($sql);
} catch (Exception $e) {
    $p = array();
}
print_r($p);
exit;
ok($p['TABLE']['create-def']['sub_tree'][1]['sub_tree'][1]['sub_tree'][5]['expr_type'] === ExpressionType::DEF_VALUE,
        'column definition with DEFAULT value');

?>
