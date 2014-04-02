<?php

require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$query  = "SELECT col FROM table1 GROUP BY col";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62a.sql', false);
ok($created === $expected, 'GROUP BY colref should not fail');

$query  = "SELECT col AS somealias FROM table ORDER BY somealias LIMIT 1";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62b.sql', false);
ok($created === $expected, 'ORDER BY alias should not fail');

$query  = "SELECT * FROM table LIMIT 1";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62c.sql', false);
ok($created === $expected, 'LIMIT should not be ignored');

$query  = "SELECT * FROM table ORDER BY TIME_FORMAT(column,'%H:%i') DESC";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62d.sql', false);
ok($created === $expected, 'function inside ORDER BY should not fail');

$query  = "SELECT * FROM table ORDER BY column DESC";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62e.sql', false);
ok($created === $expected, 'simple ORDER BY DESC should not fail');

$query  = "INSERT INTO tab1 (col1,col2) VALUES (?,?)";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62f.sql', false);
ok($created === $expected, 'prepared INSERT statements should not fail');

$query  = "DELETE FROM tab1 WHERE col1=1";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62g.sql', false);
ok($created === $expected, 'DELETE FROM statements should not fail');

$query  = "SELECT col1 FROM tab1 inner join tab2 on tab1.col1=tab2.col1 and col2 in (1,2) order by col3";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62h.sql', false);
ok($created === $expected, 'IN-list within table ref clause should not fail');

$query  = "SELECT COUNT(colname) AS aliasname FROM tablename";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62i.sql', false);
ok($created === $expected, 'function alias within SELECT should not be lost');

$query  = "update table1,table2 set table1.col1=0 where table1.col2=table2.col2";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62j.sql', false);
ok($created === $expected, 'multiple table updates should not fail');

$query  = "SELECT col1 FROM tab1 WHERE col1=(SELECT col1 FROM tab2 WHERE col2=103)";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62k.sql', false);
ok($created === $expected, 'sub-queries should not fail');

$query  = "select round((1-(phy.value / (cur.value + con.value)))*100,2) from vtiger_users";
$parser = new PHPSQLParser();
$p = $parser->parse($query);
$creator = new PHPSQLCreator();
$created = $creator->create($p);
print_r($created);
$expected = getExpectedValue(dirname(__FILE__), 'issue62l.sql', false);
ok($created === $expected, 'complex select clause should not fail');

?>
