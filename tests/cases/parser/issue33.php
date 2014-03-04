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


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ti (id INT, amount DECIMAL(7,2), tr_date DATE)
    ENGINE=INNODB
    PARTITION BY HASH( MONTH(tr_date) )
    PARTITIONS 6";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33m.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with partitions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ti (id INT, amount DECIMAL(7,2), tr_date DATE)
    ENGINE=INNODB
    PARTITION BY LINEAR KEY ALGORITHM=2 (tr_date)
    PARTITIONS 6";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33n.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with partitions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ti (id INT, amount DECIMAL(7,2), tr_date DATE)
    ENGINE=INNODB
    PARTITION BY LINEAR KEY ALGORITHM=2 (tr_date)
    PARTITIONS 6
    SUBPARTITION BY LINEAR HASH (MONTH(tr_date))
    SUBPARTITIONS 2";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33o.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with partitions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ti (id INT, amount DECIMAL(7,2), purchased DATE)
    ENGINE=INNODB
    PARTITION BY RANGE(YEAR(purchased))";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33p.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with partitions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ti (id INT, amount DECIMAL(7,2), purchased DATE)
    ENGINE=INNODB
    PARTITION BY LIST COLUMNS (purchased, amount)";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33q.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with partitions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ts (id INT, purchased DATE)
    PARTITION BY RANGE( YEAR(purchased) )
    SUBPARTITION BY HASH( TO_DAYS(purchased) ) (
        PARTITION p0 VALUES LESS THAN (1990) (
            SUBPARTITION s0
                DATA DIRECTORY = '/disk0/data'
                INDEX DIRECTORY = '/disk0/idx',
            SUBPARTITION s1
                DATA DIRECTORY = '/disk1/data'
                INDEX DIRECTORY = '/disk1/idx'
        ),
        PARTITION p1 VALUES LESS THAN (2000) (
            SUBPARTITION s2
                DATA DIRECTORY = '/disk2/data'
                INDEX DIRECTORY = '/disk2/idx',
            SUBPARTITION s3
                DATA DIRECTORY = '/disk3/data'
                INDEX DIRECTORY = '/disk3/idx'
        ),
        PARTITION p2 VALUES LESS THAN MAXVALUE (
            SUBPARTITION s4
                DATA DIRECTORY = '/disk4/data'
                INDEX DIRECTORY = '/disk4/idx',
            SUBPARTITION s5
                DATA DIRECTORY = '/disk5/data'
                INDEX DIRECTORY = '/disk5/idx'
        )
    )";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33r.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with subpartitions and partition-definitions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ts (id INT, purchased DATE)
    PARTITION BY RANGE COLUMNS(id)
    PARTITIONS 3
    SUBPARTITION LINEAR KEY ALGORITHM=2 (purchased) 
    SUBPARTITIONS 2 (
        PARTITION p0 VALUES LESS THAN (1990) (
            SUBPARTITION s0
                DATA DIRECTORY = '/disk0/data'
                INDEX DIRECTORY = '/disk0/idx',
            SUBPARTITION s1
                DATA DIRECTORY = '/disk1/data'
                INDEX DIRECTORY = '/disk1/idx'
        ),
        PARTITION p1 VALUES LESS THAN (2000) (
            SUBPARTITION s2
                DATA DIRECTORY = '/disk2/data'
                INDEX DIRECTORY = '/disk2/idx',
            SUBPARTITION s3
                DATA DIRECTORY = '/disk3/data'
                INDEX DIRECTORY = '/disk3/idx'
        ),
        PARTITION p2 VALUES LESS THAN MAXVALUE (
            SUBPARTITION s4
                DATA DIRECTORY = '/disk4/data'
                INDEX DIRECTORY = '/disk4/idx',
            SUBPARTITION s5
                DATA DIRECTORY = '/disk5/data'
                INDEX DIRECTORY = '/disk5/idx'
        )
    )";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33s.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with subpartitions and partition-definitions');


$parser = new PHPSQLParser();
$sql = "CREATE TABLE ts (id INT, purchased DATE)
    PARTITION BY RANGE COLUMNS(id)
    PARTITIONS 3
    SUBPARTITION LINEAR KEY ALGORITHM=2 (purchased) 
    SUBPARTITIONS 2 (
        PARTITION p0 VALUES LESS THAN (1990) 
        ENGINE bla
        INDEX DIRECTORY = '/bar/foo'
        MAX_ROWS = 5
        MIN_ROWS = 2
        (
            SUBPARTITION s0
                DATA DIRECTORY = '/disk0/data'
                INDEX DIRECTORY = '/disk0/idx',
            SUBPARTITION s1
                DATA DIRECTORY = '/disk1/data'
                INDEX DIRECTORY = '/disk1/idx'
        ),
        PARTITION p1 VALUES LESS THAN (2000) 
        STORAGE ENGINE=bla
        COMMENT = 'foobar'
        DATA DIRECTORY '/foo/bar'
        (
            SUBPARTITION s2
                DATA DIRECTORY = '/disk2/data'
                INDEX DIRECTORY = '/disk2/idx',
            SUBPARTITION s3
                DATA DIRECTORY = '/disk3/data'
                INDEX DIRECTORY = '/disk3/idx'
        ),
        PARTITION p2 VALUES LESS THAN MAXVALUE 
        INDEX DIRECTORY '/foo/bar'
        MIN_ROWS =10
        MAX_ROWS  100
        (
            SUBPARTITION s4
                DATA DIRECTORY = '/disk4/data'
                INDEX DIRECTORY = '/disk4/idx',
            SUBPARTITION s5
                DATA DIRECTORY = '/disk5/data'
                INDEX DIRECTORY = '/disk5/idx'
        )
    )";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue33t.serialized');
eq_array($p, $expected, 'CREATE TABLE statement with subpartitions and partition-definitions');

?>
