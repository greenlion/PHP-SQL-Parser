<?php
/**
 * partitionByTest.php
 *
 * Test case for PHPSQLCreator.
 *
 * PHP version 5
 *
 * LICENSE:
 * Copyright (c) 2010-2014 Justin Swanhart and André Rothe
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author    André Rothe <andre.rothe@phosco.info>
 * @copyright 2010-2014 Justin Swanhart and André Rothe
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */
namespace PHPSQLParser\Test\Creator;
use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

class partitionByTest extends \PHPUnit\Framework\TestCase {

    protected function assertRoundTrip($sql, $message) {
        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        $created = $creator->create($parser->parse($sql, true));
        $this->assertSame($sql, $created, $message);
    }

    public function testHashPartitioning() {
        $this->assertRoundTrip("CREATE TABLE t (a int) PARTITION BY HASH(a) PARTITIONS 4",
                               'HASH partitioning with a partition count');
    }

    public function testLinearKeyPartitioningWithAlgorithm() {
        $this->assertRoundTrip("CREATE TABLE t (a int) PARTITION BY LINEAR KEY ALGORITHM=2 (a) PARTITIONS 4",
                               'LINEAR KEY partitioning with an ALGORITHM option');
    }

    /**
     * VALUES IN (...) contains a comma-separated list of constants, this must
     * not throw an UnableToCreateSQLException nor lose any of the values.
     */
    public function testListPartitioningWithMultipleValues() {
        $this->assertRoundTrip("CREATE TABLE t (a int) PARTITION BY LIST(a) (PARTITION p0 VALUES IN (1,2,3))",
                               'LIST partitioning with a multi-value VALUES IN clause');
    }

    public function testRangeColumnsPartitioningWithMultipleValues() {
        $this->assertRoundTrip(
            "CREATE TABLE t (a int, b int) PARTITION BY RANGE COLUMNS(a,b)"
            . " (PARTITION p0 VALUES LESS THAN (10,20), PARTITION p1 VALUES LESS THAN (MAXVALUE,MAXVALUE))",
            'RANGE COLUMNS partitioning with a multi-value VALUES LESS THAN clause');
    }

    public function testPartitionDefinitionOptions() {
        $this->assertRoundTrip(
            "CREATE TABLE t (a int, b date) PARTITION BY RANGE(YEAR(b))"
            . " (PARTITION p0 VALUES LESS THAN (1990) DATA DIRECTORY '/d0' INDEX DIRECTORY '/i0'"
            . " MAX_ROWS=1000 MIN_ROWS=1 COMMENT 'old', PARTITION p1 VALUES LESS THAN MAXVALUE ENGINE=InnoDB)",
            'partition definitions with DATA/INDEX DIRECTORY, MAX_ROWS, MIN_ROWS, COMMENT and ENGINE');
    }

    public function testSubpartitioning() {
        $this->assertRoundTrip(
            "CREATE TABLE t (a int) PARTITION BY HASH(a) PARTITIONS 4 SUBPARTITION BY HASH(b) SUBPARTITIONS 2",
            'HASH partitioning with a HASH subpartitioning clause');
    }

    public function testSubpartitionDefinitions() {
        $this->assertRoundTrip(
            "CREATE TABLE t (a int) PARTITION BY RANGE(a) SUBPARTITION BY HASH(a) SUBPARTITIONS 2"
            . " (PARTITION p0 VALUES LESS THAN (10) (SUBPARTITION s0, SUBPARTITION s1),"
            . " PARTITION p1 VALUES LESS THAN MAXVALUE (SUBPARTITION s2, SUBPARTITION s3))",
            'RANGE partitioning with explicit subpartition definitions');
    }

    /**
     * Table options (e.g. ENGINE) and PARTITION BY can both follow the table
     * name directly, without a create-definition in between; and the SELECT
     * of a CREATE TABLE ... SELECT statement must follow the partition-options.
     */
    public function testPartitionByWithoutCreateDefinition() {
        $this->assertRoundTrip("CREATE TABLE mytable PARTITION BY HASH(a) PARTITIONS 4 SELECT a FROM t",
                               'PARTITION BY without a create-definition, followed by SELECT');
    }

    public function testPartitionByWithTableOptions() {
        $sql = "CREATE TABLE t (a int) ENGINE=InnoDB PARTITION BY HASH(a) PARTITIONS 4";
        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        $created = $creator->create($parser->parse($sql, true));
        $this->assertSame("CREATE TABLE t (a int) ENGINE = InnoDB PARTITION BY HASH(a) PARTITIONS 4", $created,
                          'table options and PARTITION BY are both preserved');
    }
}
