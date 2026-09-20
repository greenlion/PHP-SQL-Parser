<?php
/**
 * createTableSelectTest.php
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

    public function testCreateTableSelect() {
        $parser = new PHPSQLParser();
        $p = $parser->parse($this->getSql(), true);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $expected = getExpectedValue(dirname(__FILE__), 'createTableSelect.sql', false);
        $this->assertSame($expected, $created, 'CREATE TABLE ... SELECT with functions and quoted aliases');
    }

    public function testCreateTableAsSelect() {
        $sql = str_replace('create table mytable SELECT', 'create table mytable AS SELECT', $this->getSql());
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql, true);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $expected = getExpectedValue(dirname(__FILE__), 'createTableSelect.sql', false);
        $this->assertSame(str_replace('mytable SELECT', 'mytable AS SELECT', $expected), $created,
                          'CREATE TABLE ... AS SELECT with functions and quoted aliases');
    }

    /**
     * The created statement must be parseable and must result in the same
     * output, if it is created again.
     */
    public function testCreateTableSelectIsIdempotent() {
        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        $created = $creator->create($parser->parse($this->getSql(), true));

        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        $this->assertSame($created, $creator->create($parser->parse($created, true)),
                          're-creation of CREATE TABLE ... SELECT');
    }

    /**
     * Table options in front of the SELECT statement must be part of the
     * created statement, also if there is no create definition.
     */
    public function testCreateTableSelectWithTableOptions() {
        $sql = "CREATE TABLE mytable ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_bin SELECT a, b FROM t";
        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        $created = $creator->create($parser->parse($sql, true));
        $this->assertSame("CREATE TABLE mytable ENGINE = MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_bin"
                          . " SELECT a, b FROM t", $created, 'CREATE TABLE ... SELECT with table options');
    }

    public function testCreateTableSelectWithDirectoryOptions() {
        $sql = "CREATE TABLE mytable DATA DIRECTORY '/tmp/data' INDEX DIRECTORY '/tmp/index' AS SELECT a FROM t";
        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        $created = $creator->create($parser->parse($sql, true));
        $this->assertSame($sql, $created, 'CREATE TABLE ... AS SELECT with directory options');
    }
}
