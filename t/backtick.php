<?php
require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();
$sql = 'SELECT c1.`some_column` or `c1`.`another_column` or c1.`some column` as `an alias`
          from some_table an_alias group by `an alias`, `alias2`;';
$parser->parse($sql);
$p = $parser->parsed;
ok($parser->parsed['SELECT'][0]['alias'] == '`an alias`');
ok($parser->parsed['SELECT'][0]['sub_tree'][4]['base_expr'] == 'c1.`some column`');
