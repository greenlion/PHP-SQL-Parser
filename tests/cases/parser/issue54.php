<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$errorNumber = 0;

function issue54ErrorHandler($errno, $errstr, $errfile, $errline) {
    global $errorNumber;
    $errorNumber = $errno;    
    return true;
}
$old_error_handler = set_error_handler("issue54ErrorHandler");

$parser = new PHPSQLParser();
$sql = "SELECT schema.`table`.c as b, sum(id + 5 * (5 + 5)) as p FROM schema.table WHERE a=1 GROUP BY c HAVING p > 10 ORDER BY p DESC";
$parser->parse($sql);
$p = $parser->parsed;

ok($errorNumber === 0, 'No notice should be thrown');
$old_error_handler = set_error_handler($old_error_handler);

$expected = getExpectedValue(dirname(__FILE__), 'issue54.serialized');
eq_array($p, $expected, 'having clause and column references with schema/table/col parts.');

?>
