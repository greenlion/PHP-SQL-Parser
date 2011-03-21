<?php
require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();
$sql = 'SELECT c1
          from some_table an_alias
	where d<>0  or d <> 0 or d<>"test1" or d <> "test2";';
$parser->parse($sql);
$p = $parser->parsed;
ok($parser->parsed['WHERE'][1]['base_expr'] == '<>' &&
$parser->parsed['WHERE'][5]['base_expr'] == '<>' &&
$parser->parsed['WHERE'][9]['base_expr'] == '<>' &&
$parser->parsed['WHERE'][13]['base_expr'] == '<>'
);


