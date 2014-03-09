<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$sql = "SELECT a.*
FROM iuz6l_menu_types AS a
LEFT JOIN iuz6l_menu AS b ON b.menutype = a.menutype AND b.home != 0
LEFT JOIN iuz6l_languages AS l ON (l.lang_code = language)
WHERE (b.client_id = 0 OR b.client_id IS NULL)";

$parser = new PHPSQLParser($sql);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue104.sql', false);
ok($created === $expected, 'ref clause parentheses');

?>
