<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

# TODO: not solved, the parser doesn't recognize the UNION
$sql="SELECT * FROM ((SELECT 1 AS `ID`) UNION (SELECT 2 AS `ID`)) AS `Tmp`";

try {
	$parser = new PHPSQLParser($sql);
} catch (UnableToCalculatePositionException $e) {}

$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue95.serialized');
eq_array($p, $expected, 'union within the from clause');

?>