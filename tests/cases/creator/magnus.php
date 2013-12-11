<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$sql = "SELECT
 u.`id` AS userid,
u.`user` AS username,
 u.`firstname`,
u.`lastname`,
 u.`email`,
CONCAT(19, lastname, 2013) AS test
 FROM
`user` u
 ORDER BY
 u.`user` DESC";

$parser = new PHPSQLParser();
$parsed = $parser->parse($sql);
$creator = new PHPSQLCreator();
$created = $creator->create($parsed);
$expected = getExpectedValue(dirname(__FILE__), 'magnus.sql', false);
ok($created === $expected, 'Aliases for functions.');

?>