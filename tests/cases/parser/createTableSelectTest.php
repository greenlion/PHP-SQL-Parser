<?php
/**
 * createTableSelectTest.php
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

class createTableSelectTest extends \PHPUnit\Framework\TestCase {

    /**
     * CREATE TABLE ... SELECT, with quoted aliases, aggregates within
     * function calls and a string constant, which contains a dollar sign.
     */
    protected function getSql() {
        return <<<'SQL'
create table mytable SELECT timeheader.Project, CONCAT('$',timeheader.Expenses) As Expenses, DATE_FORMAT(timedetail.RowDate,'%d/%m/%Y') AS Datum,SEC_TO_TIME(SUM( TIME_TO_SEC( timedetail.Duration ))) AS Hours, CONCAT('$',FORMAT(timeheader.Intern_Rate,2,'en_US')) as 'Intern per Hour', CONCAT('$',FORMAT(SUM( TIME_TO_SEC( timedetail.Duration ))/3600timeheader.Intern_Rate,2,'en_US')) AS 'Internal Cost', CONCAT('$',FORMAT(timeheader.Extern_Rate,2,'en_US')) as 'Extern per Hour', CONCAT('$',FORMAT(SUM( TIME_TO_SEC( timedetail.Duration ))/3600timeheader.Extern_Rate,2,'en_US')) AS 'Extern Cost', CONCAT('$',FORMAT(125,2,'en_US')) as 'Billable per Hour', CONCAT('$',FORMAT((SUM( TIME_TO_SEC( timedetail.Duration ))*(125/3600))+timeheader.Expenses,2,'en_US')) AS 'Billable' FROM timedetail LEFT JOIN timeheader ON timedetail.Identifyer = timeheader.Identifyer group by timeheader.Identifyer, timeheader.Project, timeheader.Intern_Rate, timeheader.Extern_Rate, timeheader.Expenses, timedetail.RowDate order by timeheader.Identifyer, timedetail.RowDate;
SQL;
    }

    public function testCreateTableSelectStatement() {
        $parser = new PHPSQLParser();
        $p = $parser->parse($this->getSql(), true);

        $this->assertEquals(array('CREATE', 'TABLE', 'SELECT', 'FROM', 'GROUP', 'ORDER'), array_keys($p),
                            'the statement parts of CREATE TABLE ... SELECT');

        $this->assertEquals('table', $p['CREATE']['expr_type'], 'we create a table');
        $this->assertFalse($p['CREATE']['not-exists'], 'without IF NOT EXISTS');

        $this->assertEquals('mytable', $p['TABLE']['name'], 'the name of the new table');
        $this->assertEquals(array('mytable'), $p['TABLE']['no_quotes']['parts'], 'the unquoted table name');
        $this->assertFalse($p['TABLE']['create-def'], 'there is no column definition');
        $this->assertFalse($p['TABLE']['options'], 'there are no table options');
        $this->assertArrayNotHasKey('select-option', $p['TABLE'], 'the SELECT follows without AS, IGNORE or REPLACE');
    }

    public function testCreateTableSelectColumns() {
        $parser = new PHPSQLParser();
        $p = $parser->parse($this->getSql(), true);

        $this->assertCount(10, $p['SELECT'], 'the number of the selected columns');

        $this->assertEquals('colref', $p['SELECT'][0]['expr_type'], 'the first column is a column reference');
        $this->assertEquals('timeheader.Project', $p['SELECT'][0]['base_expr'], 'the first column');
        $this->assertFalse($p['SELECT'][0]['alias'], 'the first column has no alias');

        // the aliases of the columns 1 to 9, some of them are quoted
        $aliases = array('Expenses', 'Datum', 'Hours', "'Intern per Hour'", "'Internal Cost'", "'Extern per Hour'",
                         "'Extern Cost'", "'Billable per Hour'", "'Billable'");
        foreach ($aliases as $idx => $alias) {
            $column = $p['SELECT'][$idx + 1];
            $this->assertEquals('function', $column['expr_type'], 'the column ' . ($idx + 1) . ' is a function');
            $this->assertTrue($column['alias']['as'], 'the alias of column ' . ($idx + 1) . ' uses AS');
            $this->assertEquals($alias, $column['alias']['name'], 'the alias of column ' . ($idx + 1));
            $this->assertEquals(array(trim($alias, "'")), $column['alias']['no_quotes']['parts'],
                                'the unquoted alias of column ' . ($idx + 1));
        }

        // CONCAT('$',timeheader.Expenses) As Expenses
        $concat = $p['SELECT'][1];
        $this->assertEquals('CONCAT', $concat['base_expr'], 'the function name of the second column');
        $this->assertEquals('const', $concat['sub_tree'][0]['expr_type'], 'the dollar sign is a constant');
        $this->assertEquals("'$'", $concat['sub_tree'][0]['base_expr'], 'the dollar sign within a string constant');
        $this->assertEquals('colref', $concat['sub_tree'][1]['expr_type'], 'the second parameter is a column');

        // SEC_TO_TIME(SUM( TIME_TO_SEC( timedetail.Duration ))) AS Hours
        $secToTime = $p['SELECT'][3];
        $this->assertEquals('SEC_TO_TIME', $secToTime['base_expr'], 'the function name of the fourth column');
        $this->assertEquals('aggregate_function', $secToTime['sub_tree'][0]['expr_type'], 'SUM is an aggregate');
        $this->assertEquals('SUM', $secToTime['sub_tree'][0]['base_expr'], 'the aggregate function');
        $this->assertEquals('TIME_TO_SEC', $secToTime['sub_tree'][0]['sub_tree'][0]['base_expr'],
                            'the function within the aggregate');
    }

    public function testCreateTableSelectClauses() {
        $parser = new PHPSQLParser();
        $p = $parser->parse($this->getSql(), true);

        $this->assertCount(2, $p['FROM'], 'the number of the tables');
        $this->assertEquals('timedetail', $p['FROM'][0]['table'], 'the first table');
        $this->assertEquals('JOIN', $p['FROM'][0]['join_type'], 'the first table has no join type');
        $this->assertEquals('timeheader', $p['FROM'][1]['table'], 'the second table');
        $this->assertEquals('LEFT', $p['FROM'][1]['join_type'], 'the second table is joined with LEFT JOIN');
        $this->assertEquals('ON', $p['FROM'][1]['ref_type'], 'the join reference type');
        $this->assertCount(3, $p['FROM'][1]['ref_clause'], 'the ON clause of the join');

        $this->assertCount(6, $p['GROUP'], 'the number of the GROUP BY expressions');
        $this->assertEquals('timeheader.Identifyer', $p['GROUP'][0]['base_expr'], 'the first GROUP BY expression');
        $this->assertEquals('timedetail.RowDate', $p['GROUP'][5]['base_expr'], 'the last GROUP BY expression');

        $this->assertCount(2, $p['ORDER'], 'the number of the ORDER BY expressions');
        $this->assertEquals('timeheader.Identifyer', $p['ORDER'][0]['base_expr'], 'the first ORDER BY expression');
        $this->assertEquals('timedetail.RowDate', $p['ORDER'][1]['base_expr'], 'the last ORDER BY expression');
    }

    /**
     * The SELECT part of a CREATE TABLE ... SELECT statement must be parsed
     * in the same way as a standalone SELECT statement.
     */
    public function testCreateTableSelectEqualsPlainSelect() {
        $sql = rtrim($this->getSql(), ';');
        $select = substr($sql, strlen('create table mytable '));

        $parser = new PHPSQLParser();
        $create = $parser->parse($sql);
        $plain = $parser->parse($select);

        foreach (array('SELECT', 'FROM', 'GROUP', 'ORDER') as $clause) {
            $this->assertEquals($plain[$clause], $create[$clause],
                                'the ' . $clause . ' clause of CREATE TABLE ... SELECT');
        }
    }

    /**
     * The same statement, but with the optional AS keyword. It must set the
     * select-option of the table, without any duplicate handling.
     */
    public function testCreateTableAsSelect() {
        $sql = str_replace('create table mytable SELECT', 'create table mytable AS SELECT', $this->getSql());

        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertEquals(array('CREATE', 'TABLE', 'SELECT', 'FROM', 'GROUP', 'ORDER'), array_keys($p),
                            'the statement parts of CREATE TABLE ... AS SELECT');
        $this->assertEquals('mytable', $p['TABLE']['name'], 'the name of the new table');
        $this->assertTrue($p['TABLE']['select-option']['as'], 'the AS keyword of the select-option');
        $this->assertFalse($p['TABLE']['select-option']['duplicates'], 'neither IGNORE nor REPLACE was given');
        $this->assertEquals('AS', $p['TABLE']['select-option']['base_expr'], 'the base expression of the select-option');
        $this->assertEquals(array(array('expr_type' => 'reserved', 'base_expr' => 'AS', 'position' => 21)),
                            $p['TABLE']['select-option']['sub_tree'], 'the sub_tree of the select-option');
        $this->assertCount(10, $p['SELECT'], 'the number of the selected columns');
    }

    /**
     * The AS keyword without IGNORE or REPLACE must not convert the unset
     * select-option (boolean false) into an array implicitly. That is
     * deprecated since PHP 8.1 and will be an error in a future PHP version.
     */
    public function testCreateTableAsSelectWithoutErrors() {
        $sql = str_replace('create table mytable SELECT', 'create table mytable AS SELECT', $this->getSql());

        $errors = array();
        $reporting = error_reporting(E_ALL);
        set_error_handler(function ($errno, $errstr) use (&$errors) {
            $errors[] = $errstr;
            return true;
        }, E_ALL);

        try {
            $parser = new PHPSQLParser();
            $parser->parse($sql, true);
        } catch (\Exception $e) {
            restore_error_handler();
            error_reporting($reporting);
            throw $e;
        }

        restore_error_handler();
        error_reporting($reporting);

        $this->assertSame(array(), $errors, 'CREATE TABLE ... AS SELECT must not emit any PHP error');
    }

    /**
     * The table options can be placed between the table name and the SELECT
     * statement, also if the statement has no create definition at all.
     */
    public function testCreateTableSelectWithTableOptions() {
        $sql = "CREATE TABLE mytable ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_bin SELECT a, b FROM t";

        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertFalse($p['TABLE']['create-def'], 'there is no column definition');
        $this->assertCount(3, $p['TABLE']['options'], 'the number of the table options');

        $engine = $p['TABLE']['options'][0];
        $this->assertEquals('expression', $engine['expr_type'], 'the type of the ENGINE option');
        $this->assertEquals('ENGINE=MyISAM', $engine['base_expr'], 'the base expression of the ENGINE option');
        $this->assertEquals(21, $engine['position'], 'the position of the ENGINE option');
        $this->assertEquals('reserved', $engine['sub_tree'][0]['expr_type'], 'the ENGINE keyword');
        $this->assertEquals('operator', $engine['sub_tree'][1]['expr_type'], 'the = operator');
        $this->assertEquals('const', $engine['sub_tree'][2]['expr_type'], 'the engine name');
        $this->assertEquals('MyISAM', $engine['sub_tree'][2]['base_expr'], 'the engine name');

        $charset = $p['TABLE']['options'][1];
        $this->assertEquals('character-set', $charset['expr_type'], 'the type of the CHARACTER SET option');
        $this->assertEquals('DEFAULT CHARACTER SET utf8', $charset['base_expr'],
                            'the base expression of the CHARACTER SET option');
        $this->assertEquals(35, $charset['position'], 'the position of the CHARACTER SET option');

        $collate = $p['TABLE']['options'][2];
        $this->assertEquals('collation', $collate['expr_type'], 'the type of the COLLATE option');
        $this->assertEquals('COLLATE utf8_bin', $collate['base_expr'], 'the base expression of the COLLATE option');
        $this->assertEquals(62, $collate['position'], 'the position of the COLLATE option');

        $this->assertCount(2, $p['SELECT'], 'the number of the selected columns');
        $this->assertCount(1, $p['FROM'], 'the number of the tables');
    }

    /**
     * The DATA DIRECTORY and INDEX DIRECTORY options in front of the SELECT
     * statement, they are the only options with an own expression type.
     */
    public function testCreateTableSelectWithDirectoryOptions() {
        $sql = "CREATE TABLE mytable DATA DIRECTORY '/tmp/data' INDEX DIRECTORY '/tmp/index' AS SELECT a FROM t";

        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);

        $this->assertCount(2, $p['TABLE']['options'], 'the number of the table options');
        $this->assertTrue($p['TABLE']['select-option']['as'], 'the AS keyword of the select-option');

        $data = $p['TABLE']['options'][0];
        $this->assertEquals('directory', $data['expr_type'], 'the type of the DATA DIRECTORY option');
        $this->assertEquals('DATA', $data['kind'], 'the kind of the DATA DIRECTORY option');
        $this->assertEquals("DATA DIRECTORY '/tmp/data'", $data['base_expr'],
                            'the base expression of the DATA DIRECTORY option');
        $this->assertEquals(21, $data['position'], 'the position of the DATA DIRECTORY option');
        $this->assertEquals("'/tmp/data'", $data['sub_tree'][2]['base_expr'], 'the data directory');

        $index = $p['TABLE']['options'][1];
        $this->assertEquals('directory', $index['expr_type'], 'the type of the INDEX DIRECTORY option');
        $this->assertEquals('INDEX', $index['kind'], 'the kind of the INDEX DIRECTORY option');
        $this->assertEquals("INDEX DIRECTORY '/tmp/index'", $index['base_expr'],
                            'the base expression of the INDEX DIRECTORY option');
        $this->assertEquals(48, $index['position'], 'the position of the INDEX DIRECTORY option');
        $this->assertEquals("'/tmp/index'", $index['sub_tree'][2]['base_expr'], 'the index directory');
    }
}
