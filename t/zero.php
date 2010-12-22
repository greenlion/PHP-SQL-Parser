<?php
require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();
$sql = 'SELECT c1
          from some_table an_alias
	where d > 0;';
$parser->parse($sql);
$p = $parser->parsed;
ok($parser->parsed['WHERE'][2]['base_expr'] == '0');


