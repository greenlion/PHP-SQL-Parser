<?php
/**
 * aliasesTest.php
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

class manticoreCasesTest extends \PHPUnit\Framework\TestCase
{

    protected $parser;

    /**
     * @before
     * Executed before each test
     */
    protected function setup(): void
    {
        $this->parser = new PHPSQLParser();
    }


    /**
     * @test
     * @dataProvider manticoreQueryProvider
     */
    public function manticoreCasesTest($query, $resultFileName)
    {
        $p = $this->parser->parse($query);
//        setExpectedValue(dirname(__FILE__), $resultFileName . '.serialized', $p);
        $expected = getExpectedValue(dirname(__FILE__), $resultFileName . '.serialized');
        $this->assertEquals($expected, $p);
    }

    /**
     * @return array
     */
    public function manticoreQueryProvider(): array
    {
        return [
            ['CREATE SOURCE test', 'ms_create_source_1'],
            ['CREATE SOURCE `test`', 'ms_create_source_2'],
            ["CREATE SOURCE kafka (id bigint, term text, abbrev text, GlossDef json) type='kafka'
		   broker_list='kafka:9092' topic_list='my-data' consumer_group='manticore' num_consumers='4' batch=50", 'ms_create_source_3'],
            ['CREATE VIEW view_table', 'ms_create_view_1'],
            ["CREATE MATERIALIZED VIEW view_table TO destination_kafka AS SELECT id, term as name,
		   abbrev as short_name, UTC_TIMESTAMP() as received_at, GlossDef.size as size FROM kafka", 'ms_create_view_2'],
            ["CREATE TABLE destination_kafka (id bigint, name text, short_name text, received_at text, size multi) engine='columnar'", 'ms_create_table_1'],
            ["SHOW TABLES", 'ms_show_tables_1'],
            ["SHOW TABLE abc", 'ms_show_tables_2'],
            ["SHOW TABLE `abc`", 'ms_show_tables_3'],
            ["SHOW SOURCES", 'ms_show_sources_1'],
            ["SHOW SOURCE abc", 'ms_show_sources_2'],
            ["SHOW SOURCE `abc`", 'ms_show_sources_3'],
            ["SHOW VIEWS", 'ms_show_views_1'],
            ["SHOW VIEWS aaa", 'ms_show_views_2'],
            ["SHOW VIEW abc", 'ms_show_views_3'],
            ["SHOW VIEW `abc`", 'ms_show_views_4'],
            ["SHOW MATERIALIZED VIEWS", 'ms_show_views_5'],
            ["SHOW MATERIALIZED VIEW abc", 'ms_show_views_6'],
            ["SHOW MATERIALIZED VIEW `abc`", 'ms_show_views_7'],
            ["DROP TABLE abc", 'ms_drop_table_1'],
            ["DROP TABLE `abc`", 'ms_drop_table_2'],
            ["DROP TABLE IF EXIST abc", 'ms_drop_table_3'],
            ["DROP SOURCE abc", 'ms_drop_source_1'],
            ["DROP SOURCE `abc`", 'ms_drop_source_2'],
            ["DROP SOURCE IF EXIST `abc`", 'ms_drop_source_3'],
            ["DROP VIEW `abc`", 'ms_drop_view_1'],
            ["DROP MATERIALIZED VIEW abc", 'ms_drop_view_2'],
            ["DROP VIEW IF EXIST abc", 'ms_drop_view_3'],
            ["DROP MATERIALIZED VIEW IF EXIST `abc`", 'ms_drop_view_4'],
            ["alter table rt ADD column title int", 'ms_alter_table_1'],
            ["alter table rt ADD column title text indexed stored engine='columnar'", 'ms_alter_table_2'],
            ["ALTER SOURCE `abc` ADD column title int", 'ms_alter_source_1'],
            ["ALTER VIEW `abc` ADD column title int", 'ms_alter_view_1'],
            ["ALTER MATERIALIZED VIEW abc ADD column title int", 'ms_alter_view_2'],
            ["ALTER MATERIALIZED VIEW view_name suspended=1", 'ms_alter_table_3'],
        ];
    }
}

?>
