<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';

$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (LIKE xyz)";
$parser->parse($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33a.sql', false);
ok($created === $expected, 'CREATE TABLE statement with (LIKE)');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho LIKE xyz";
$parser->parse($sql, true);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33b.sql', false);
ok($created === $expected, 'CREATE TABLE statement with LIKE');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000) NOT NULL, CONSTRAINT hohoho_pk PRIMARY KEY (a), CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33c.sql', false);
ok($created === $expected, 'CREATE TABLE statement with named primary key and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), CONSTRAINT PRIMARY KEY (a), CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
try {
    $creator = new PHPSQLCreator($parser->parsed);
    $created = $creator->created;
} catch (Exception $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
    $created = "";
}
$expected = getExpectedValue(dirname(__FILE__), 'issue33d.sql', false);
ok($created === $expected, 'CREATE TABLE statement with not named primary key and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), PRIMARY KEY USING btree (a), CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33e.sql', false);
ok($created === $expected, 'CREATE TABLE statement with named primary key, index type and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE \"cachetable01\" (
\"sp_id\" varchar(240) DEFAULT NULL,
\"ro\" varchar(240) DEFAULT NULL,
\"balance\" varchar(240) DEFAULT NULL,
\"last_cache_timestamp\" varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=latin1";
$parser->parse($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33f.sql', false);
ok($created === $expected, 'CREATE TABLE statement columns and options');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), PRIMARY KEY USING btree (a(5) ASC) key_block_size 4 with parser haha, CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33g.sql', false);
ok($created === $expected, 'CREATE TABLE statement with primary key with index options and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000)) ENGINE=xyz,COMMENT='haha' DEFAULT COLLATE = latin1_german2_ci";
$parser->parse($sql, true);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33h.sql', false);
ok($created === $expected, 'CREATE TABLE statement with table options separated by different characters');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), b integer, FOREIGN KEY haha (b) references xyz (id) match full on delete cascade) ";
$parser->parse($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33i.sql', false);
ok($created === $expected, 'CREATE TABLE statement with foreign key references');


$parser = new PHPSQLParser();
$sql = "CREATE TEMPORARY TABLE IF   NOT 
EXISTS turma(id text NOT NULL ,
nome text NOT NULL ,
nota1 int NOT NULL ,
nota2 int NOT NULL
)";
$parser->parse($sql, true);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33j.sql', false);
ok($created === $expected, 'simple CREATE TEMPORARY TABLE statement with positions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), PRIMARY KEY (a(5) ASC) key_block_size 4 using btree with parser haha, CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33k.sql', false);
ok($created === $expected, 'CREATE TABLE statement with primary key column and multiple index options and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a integer not null) REPLACE AS SELECT DISTINCT * FROM abcd WHERE x<5";
$parser->parse($sql, true);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33l.sql', false);
ok($created === $expected, 'CREATE TABLE statement with select statement, replace duplicates');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), b float(5,3)) ";
$parser->parse($sql);
$p = $parser->parsed;
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue33m.sql', false);
ok($created === $expected, 'CREATE TABLE statement multi-param column type');

?>