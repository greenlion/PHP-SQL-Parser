<?php

namespace PHPSQLParser\Test\Parser;
use PHPSQLParser\PHPSQLParser;

class CommentsTest extends \PHPUnit\Framework\TestCase {
	
	protected $parser;
	
	/**
	 * @before
	 * Executed before each test
	 */
	protected function setup(): void {
		$this->parser = new PHPSQLParser(false, true);
	}
        
        public function testComments1() {
            $sql = 'SELECT a, -- inline comment in SELECT section
                        b 
                    FROM test';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment1.serialized');
            $this->assertEquals($expected, $p, 'inline comment in SELECT section');
        }
        
        public function testComments2() {
            $sql = 'SELECT a, /* 
                            multi line 
                            comment
                        */
                        b 
                    FROM test';
            $p = $this->parser->parse($sql);
            $expectedEncoded = getExpectedValue(dirname(__FILE__), 'comment2.serialized', false);
            $expectedSerialized = base64_decode($expectedEncoded);
            $expected = unserialize($expectedSerialized);

            $this->assertEquals($expected, $p, 'multi line comment');
        }

        public function testComments3() {
            $sql = 'SELECT a
                    FROM test -- inline comment in FROM section';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment3.serialized');
            $this->assertEquals($expected, $p, 'inline comment in FROM section');
        }

        public function testComments4() {
            $sql = 'SELECT a
                    FROM test
                    WHERE id = 3 -- inline comment in WHERE section
                    AND b > 4';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment4.serialized');
            $this->assertEquals($expected, $p, 'inline comment in WHERE section');
        }

        public function testComments5() {
            $sql = 'SELECT a
                    FROM test
                    LIMIT -- inline comment in LIMIT section
                     10';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment5.serialized');
            $this->assertEquals($expected, $p, 'inline comment in LIMIT section');
        }

        public function testComments6() {
            $sql = 'SELECT a
                    FROM test
                    ORDER BY -- inline comment in ORDER BY section
                     a DESC';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment6.serialized');
            $this->assertEquals($expected, $p, 'inline comment in ORDER BY section');
        }

        public function testComments7() {
            $sql = 'INSERT INTO a (id) -- inline comment in INSERT section
                    VALUES (1)';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment7.serialized');
            $this->assertEquals($expected, $p, 'inline comment in INSERT section');
        }

        public function testComments8() {
            $sql = 'INSERT INTO a (id) 
                    VALUES (1) -- inline comment in VALUES section';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment8.serialized');
            $this->assertEquals($expected, $p, 'inline comment in VALUES section');
        }

        public function testHashCommentWithoutWhitespace() {
            $sql = "select 1#this is a comment\nfrom dual;";
            $p = $this->parser->parse($sql);

            $comments = array_values(array_filter($p['SELECT'], function ($v) {
                return $v['expr_type'] === 'comment';
            }));
            $this->assertCount(1, $comments, 'the hash comment is recognised as a comment');
            $this->assertSame('#this is a comment', $comments[0]['value'], 'the whole hash comment is captured');

            $expressions = array_values(array_filter($p['SELECT'], function ($v) {
                return $v['expr_type'] !== 'comment';
            }));
            $this->assertCount(1, $expressions, 'the hash comment does not add an expression');
            $this->assertSame('1', $expressions[0]['base_expr'], 'the constant is not merged with the comment');
            $this->assertSame(false, $expressions[0]['alias'], 'the hash comment is not taken as an alias');

            $this->assertSame('dual', $p['FROM'][0]['table'], 'the hash comment does not swallow the FROM clause');
        }

        public function testHashCommentWithWhitespace() {
            $sql = "select 1 #this is a comment\nfrom dual";
            $p = $this->parser->parse($sql);

            $comments = array_values(array_filter($p['SELECT'], function ($v) {
                return $v['expr_type'] === 'comment';
            }));
            $this->assertCount(1, $comments, 'the hash comment is recognised as a comment');
            $this->assertSame('#this is a comment', $comments[0]['value'], 'the whole hash comment is captured');
            $this->assertSame('dual', $p['FROM'][0]['table'], 'the hash comment does not swallow the FROM clause');
        }

        public function testHashWithinQuotedStringIsNotAComment() {
            $sql = "select 'a#b' as `c#d` from test where x = 'has # hash'";
            $p = $this->parser->parse($sql);

            $this->assertSame("'a#b'", $p['SELECT'][0]['base_expr'], 'a hash inside a string is not a comment');
            $this->assertSame('`c#d`', $p['SELECT'][0]['alias']['name'], 'a hash inside a backquoted alias is not a comment');
            $this->assertSame("'has # hash'", $p['WHERE'][2]['base_expr'], 'a hash inside a WHERE string is not a comment');
        }

        public function testCommentInWithClause() {
            $sql = "WITH\n"
                . "-- this is a comment\n"
                . "TEMP_TABLE_NAME AS (SELECT 1);";
            $p = $this->parser->parse($sql);

            $this->assertSame('TEMP_TABLE_NAME', $p['WITH'][0]['sub_tree'][0]['name'], 'the comment is not taken as the CTE name');
            $this->assertSame('temporary-table', $p['WITH'][0]['sub_tree'][0]['expr_type']);
            $this->assertSame('reserved', $p['WITH'][0]['sub_tree'][1]['expr_type']);
            $this->assertSame('AS', $p['WITH'][0]['sub_tree'][1]['base_expr']);
            $this->assertSame('bracket_expression', $p['WITH'][0]['sub_tree'][2]['expr_type']);
            $this->assertSame('1', $p['WITH'][0]['sub_tree'][2]['sub_tree']['SELECT'][0]['base_expr']);
        }

        public function testComments9() {
            $sql = 'INSERT INTO a (id) -- inline comment in INSERT section;
                    SELECT id -- inline comment in SELECT section
                    FROM x';
            $p = $this->parser->parse($sql);
            $expected = getExpectedValue(dirname(__FILE__), 'comment9.serialized');
            $this->assertEquals($expected, $p, 'inline comment in SELECT section');
        }
}

?>