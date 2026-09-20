<?php
/**
 * partitionByTest.php
 *
 * Test case for PHPSQLParser.
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
namespace PHPSQLParser\Test\Parser;
use PHPSQLParser\PHPSQLParser;

class partitionByTest extends \PHPUnit\Framework\TestCase {

    public function testHashPartitioning() {
        $sql = "CREATE TABLE t (a int) PARTITION BY HASH(a) PARTITIONS 4";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertCount(1, $p['TABLE']['partition-options'], 'the number of the partition-options');

        $partition = $p['TABLE']['partition-options'][0];
        $this->assertEquals('partition', $partition['expr_type'], 'the type of the partition-option');
        $this->assertEquals('PARTITION BY HASH(a) PARTITIONS 4', $partition['base_expr'],
                            'the base expression of the partition-option');
        $this->assertEquals('HASH', $partition['by'], 'the HASH partitioning type');
        $this->assertEquals('4', $partition['count'], 'the number of partitions');

        $hash = $partition['sub_tree'][2];
        $this->assertEquals('partition-hash', $hash['expr_type'], 'the type of the HASH definition');
        $this->assertFalse($hash['linear'], 'HASH partitioning is not linear');
        $this->assertEquals('HASH(a)', $hash['base_expr'], 'the base expression of the HASH definition');

        $count = $partition['sub_tree'][3];
        $this->assertEquals('partition-count', $count['expr_type'], 'the type of the partition count');
        $this->assertEquals('PARTITIONS 4', $count['base_expr'], 'the base expression of the partition count');
    }

    public function testLinearKeyPartitioningWithAlgorithm() {
        $sql = "CREATE TABLE t (a int) PARTITION BY LINEAR KEY ALGORITHM=2 (a) PARTITIONS 4";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $partition = $p['TABLE']['partition-options'][0];
        $this->assertEquals('LINEAR KEY', $partition['by'], 'the LINEAR KEY partitioning type');

        // the LINEAR reserved token is a sibling of the KEY node at index [2],
        // so the KEY node itself is at index [3]
        $this->assertEquals('reserved', $partition['sub_tree'][2]['expr_type'], 'the LINEAR keyword');
        $key = $partition['sub_tree'][3];
        $this->assertEquals('partition-key', $key['expr_type'], 'the type of the KEY definition');
        $this->assertTrue($key['linear'], 'KEY partitioning is linear');

        $algorithm = $key['sub_tree'][1];
        $this->assertEquals('partition-key-algorithm', $algorithm['expr_type'], 'the type of the ALGORITHM option');
        $this->assertEquals('ALGORITHM=2', $algorithm['base_expr'], 'the base expression of the ALGORITHM option');
    }

    /**
     * VALUES IN (...) contains a comma-separated list of constants. The commas
     * must be treated as separators, not as a part of the value expression.
     */
    public function testListPartitioningWithMultipleValues() {
        $sql = "CREATE TABLE t (a int) PARTITION BY LIST(a) (PARTITION p0 VALUES IN (1,2,3))";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertCount(2, $p['TABLE']['partition-options'], 'the number of the partition-options');
        $this->assertEquals('LIST', $p['TABLE']['partition-options'][0]['by'], 'the LIST partitioning type');

        $definitions = $p['TABLE']['partition-options'][1]['sub_tree'];
        $this->assertCount(1, $definitions, 'the number of the partition definitions');

        $values = $definitions[0]['sub_tree'][2];
        $this->assertEquals('partition-values', $values['expr_type'], 'the type of the VALUES clause');
        $this->assertEquals('VALUES IN (1,2,3)', $values['base_expr'], 'the base expression of the VALUES clause');

        $valueList = $values['sub_tree'][2]['sub_tree'];
        $this->assertCount(3, $valueList, 'the number of the values in the list');
        foreach (array('1', '2', '3') as $idx => $value) {
            $this->assertEquals('const', $valueList[$idx]['expr_type'], 'the type of value ' . $idx);
            $this->assertEquals($value, $valueList[$idx]['base_expr'], 'the value ' . $idx);
        }
    }

    /**
     * RANGE COLUMNS(...) partitioning uses a column list for the partitioning
     * key and VALUES LESS THAN (...) with multiple comma-separated values.
     */
    public function testRangeColumnsPartitioningWithMultipleValues() {
        $sql = "CREATE TABLE t (a int, b int) PARTITION BY RANGE COLUMNS(a,b)"
             . " (PARTITION p0 VALUES LESS THAN (10,20), PARTITION p1 VALUES LESS THAN (MAXVALUE,MAXVALUE))";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertEquals('RANGE', $p['TABLE']['partition-options'][0]['by'], 'the RANGE partitioning type');

        $definitions = $p['TABLE']['partition-options'][1]['sub_tree'];
        $this->assertCount(2, $definitions, 'the number of the partition definitions');

        // VALUES LESS THAN (...): sub_tree = [VALUES, LESS, THAN, bracket_expression]
        $values = $definitions[0]['sub_tree'][2]['sub_tree'][3]['sub_tree'];
        $this->assertCount(2, $values, 'the number of the values in the list');
        $this->assertEquals('10', $values[0]['base_expr'], 'the first value');
        $this->assertEquals('20', $values[1]['base_expr'], 'the second value');
    }

    /**
     * The options of a single PARTITION definition: DATA DIRECTORY,
     * INDEX DIRECTORY, MAX_ROWS, MIN_ROWS and COMMENT can all follow the
     * VALUES clause.
     */
    public function testPartitionDefinitionOptions() {
        $sql = "CREATE TABLE t (a int, b date) PARTITION BY RANGE(YEAR(b))"
             . " (PARTITION p0 VALUES LESS THAN (1990) DATA DIRECTORY '/d0' INDEX DIRECTORY '/i0'"
             . " MAX_ROWS=1000 MIN_ROWS=1 COMMENT 'old', PARTITION p1 VALUES LESS THAN MAXVALUE ENGINE=InnoDB)";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $definitions = $p['TABLE']['partition-options'][1]['sub_tree'];
        $this->assertCount(2, $definitions, 'the number of the partition definitions');

        $options = $definitions[0]['sub_tree'];
        $this->assertEquals('partition-def', $definitions[0]['expr_type'], 'the type of the first partition definition');
        $this->assertEquals('p0', $options[0]['name'], 'the name of the first partition');

        $types = array();
        foreach ($options as $option) {
            $types[] = $option['expr_type'];
        }
        $this->assertContains('partition-data-dir', $types, 'the DATA DIRECTORY option is present');
        $this->assertContains('partition-index-dir', $types, 'the INDEX DIRECTORY option is present');
        $this->assertContains('partition-max-rows', $types, 'the MAX_ROWS option is present');
        $this->assertContains('partition-min-rows', $types, 'the MIN_ROWS option is present');
        $this->assertContains('partition-comment', $types, 'the COMMENT option is present');

        $this->assertEquals('p1', $definitions[1]['sub_tree'][0]['name'], 'the name of the second partition');
        $engineTypes = array_column($definitions[1]['sub_tree'], 'expr_type');
        $this->assertContains('engine', $engineTypes, 'the ENGINE option of the second partition is present');
    }

    public function testSubpartitioning() {
        $sql = "CREATE TABLE t (a int) PARTITION BY HASH(a) PARTITIONS 4 SUBPARTITION BY HASH(b) SUBPARTITIONS 2";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertCount(2, $p['TABLE']['partition-options'], 'the number of the partition-options');
        $subpartition = $p['TABLE']['partition-options'][1];
        $this->assertEquals('sub-partition', $subpartition['expr_type'], 'the type of the subpartition-option');
        $this->assertEquals('HASH', $subpartition['by'], 'the HASH subpartitioning type');
        $this->assertEquals('2', $subpartition['count'], 'the number of subpartitions');
    }

    /**
     * Table options (e.g. ENGINE) and PARTITION BY can both follow the
     * table name directly, without a create-definition in between.
     */
    public function testPartitionByWithoutCreateDefinition() {
        $sql = "CREATE TABLE mytable ENGINE=InnoDB PARTITION BY HASH(a) PARTITIONS 4 SELECT a FROM t";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertCount(1, $p['TABLE']['options'], 'the number of the table options');
        $this->assertCount(1, $p['TABLE']['partition-options'], 'the number of the partition-options');
        $this->assertCount(1, $p['SELECT'], 'the number of the selected columns');
    }
}
