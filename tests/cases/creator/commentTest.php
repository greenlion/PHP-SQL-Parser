<?php
/**
 * commentTest.php
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

class commentTest extends \PHPUnit\Framework\TestCase {

    /**
     * A hash comment runs to the end of the line, so the creator has to
     * terminate it. Otherwise the rest of the created statement would be
     * commented out.
     *
     * The comment and the expression are interchanged, like it is already
     * the case within the FROM clause, see FromBuilderTest.
     */
    public function testHashCommentInSelect() {
        $sql = "select 1#this is a comment\nfrom dual;";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT #this is a comment\n1 FROM dual", $created, 'a hash comment in the select clause');
    }

    public function testInlineCommentInSelect() {
        $sql = "select 1 -- this is a comment\nfrom dual";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT -- this is a comment\n1 FROM dual", $created, 'an inline comment in the select clause');
    }

    public function testBlockCommentInSelect() {
        $sql = "SELECT a, /* mid */ b FROM test";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT a, /* mid */ b FROM test", $created, 'a block comment is not terminated by a newline');
    }

    public function testInlineCommentInWhere() {
        $sql = "SELECT a FROM test WHERE id = 3 -- in where\nAND b > 4";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT a FROM test WHERE id = 3 -- in where\n AND b > 4", $created,
                          'an inline comment in the where clause');
    }

    public function testBlockCommentInWhere() {
        $sql = "SELECT a FROM test WHERE id = 3 /* block */ AND b > 4";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT a FROM test WHERE id = 3 /* block */ AND b > 4", $created,
                          'a block comment in the where clause');
    }

    public function testInlineCommentInHaving() {
        $sql = "SELECT count(*) c FROM test GROUP BY a HAVING count(*) > 1 -- in having";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT count(*) c FROM test GROUP BY a HAVING count(*) > 1 -- in having\n", $created,
                          'an inline comment in the having clause');
    }

    /**
     * A comment is not an element of the order-by list, so it must not
     * get a comma of its own.
     */
    public function testInlineCommentInOrderBy() {
        $sql = "SELECT a FROM test ORDER BY -- in order by\n a DESC";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT a FROM test ORDER BY -- in order by\na DESC", $created,
                          'an inline comment in the order-by clause');
    }

    public function testInlineCommentBetweenOrderByElements() {
        $sql = "SELECT a FROM test ORDER BY a, -- mid\n b";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT a FROM test ORDER BY a ASC -- mid\n, b ASC", $created,
                          'an inline comment between two order-by elements');
    }

    public function testInlineCommentInFrom() {
        $sql = "SELECT * FROM car -- trailing\nWHERE color = 'black'";
        $parser = new PHPSQLParser();
        $p = $parser->parse($sql);
        $creator = new PHPSQLCreator();
        $created = $creator->create($p);
        $this->assertSame("SELECT * FROM -- trailing\ncar WHERE color = 'black'", $created, 'an inline comment in the from clause');
    }
}
