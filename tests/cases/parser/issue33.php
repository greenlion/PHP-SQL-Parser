<?php
require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (LIKE xyz)";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33a.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with (LIKE)');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho LIKE xyz";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33b.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with LIKE');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000) NOT NULL, CONSTRAINT hohoho_pk PRIMARY KEY (a), CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33c.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with named primary key and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), CONSTRAINT PRIMARY KEY (a), CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33d.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with primary key and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), PRIMARY KEY USING btree (a), CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33e.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with primary key and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE \"cachetable01\" (
\"sp_id\" varchar(240) DEFAULT NULL,
\"ro\" varchar(240) DEFAULT NULL,
\"balance\" varchar(240) DEFAULT NULL,
\"last_cache_timestamp\" varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET=latin1";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33f.serialized');
eq_array($p, $expected, 'CREATE TABLE statement');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), PRIMARY KEY USING btree (a(5) ASC) key_block_size 4 with parser haha, CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33g.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with primary key with index options and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000)) ENGINE=xyz,COMMENT='haha' DEFAULT COLLATE = latin1_german2_ci";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33h.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with table options separated by different characters');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), b integer, FOREIGN KEY haha (b) references xyz (id) match full on delete cascade) ";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33i.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with foreign key references');


$parser = new PHPSQLParser();
$sql = "CREATE TEMPORARY TABLE IF   NOT 
EXISTS turma(id text NOT NULL ,
nome text NOT NULL ,
nota1 int NOT NULL ,
nota2 int NOT NULL
)";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33j.serialized');
eq_array($p, $expected, 'simple CREATE TABLE statement with positions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a varchar(1000), PRIMARY KEY (a(5) ASC) key_block_size 4 using btree with parser haha, CHECK(a > 5))";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33k.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with primary key and multiple index options and check');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE hohoho (a integer not null) REPLACE AS SELECT DISTINCT * FROM abcd WHERE x<5";
$parser->parse($sql, true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33l.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with select statement, replace duplicates');

?>