<?php

namespace PHPSQLParser\Test\Parser;

use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;
use PHPUnit\Framework\TestCase;

/**
 * @see https://github.com/greenlion/PHP-SQL-Parser/issues/354
 */
class issue354Test extends TestCase
{
    /** @var PHPSQLParser $parser */
    private $parser;
    /** @var PHPSQLCreator $creator */
    private $creator;

    public function setUp(): void
    {
        parent::setUp();

        $this->parser = new PHPSQLParser();
        $this->creator = new PHPSQLCreator();
    }

    /**
     * A backslash escaped quote does not end a string literal, so a comment
     * character behind it belongs to the string and does not start a comment.
     *
     * @dataProvider dataEscapedQuoteBeforeCommentCharacter
     * @param string $sql
     * @param string $literal
     */
    public function testEscapedQuoteBeforeCommentCharacter($sql, $literal)
    {
        $parsed = $this->parser->parse($sql);

        $this->assertArrayHasKey('WHERE', $parsed, 'WHERE section behind the string literal');
        $this->assertEquals($literal, $parsed['SET'][0]['sub_tree'][2]['base_expr'], 'string literal');
    }

    public function dataEscapedQuoteBeforeCommentCharacter()
    {
        // [string $sql, string $literal]
        return array(
            array("update table1 set col1 = '\\'' where col2 = 1", "'\\''"),
            array("update table1 set col1 = '--' where col2 = 1", "'--'"),
            array("update table1 set col1 = '--\\'' where col2 = 1", "'--\\''"),
            array("update table1 set col1 = '\\'--' where col2 = 1", "'\\'--'"),
            array("update table1 set col1 = 'a\\'b--c' where col2 = 1", "'a\\'b--c'"),
            array("update table1 set col1 = 'a\\'b # c' where col2 = 1", "'a\\'b # c'"),
            array("update table1 set col1 = \"a\\\"b # c\" where col2 = 1", "\"a\\\"b # c\""),
            array("update table1 set col1 = 'a\\\\' where col2 = 1", "'a\\\\'"),
        );
    }

    /**
     * Comment characters within a string literal are data, so the literal stays
     * intact, the rest of the statement is still parsed and the statement
     * survives a parse and create cycle.
     *
     * @dataProvider dataCommentCharacterWithinStringLiteral
     * @param string $sql
     * @param string $literal
     */
    public function testCommentCharacterWithinStringLiteralIsNoComment($sql, $literal)
    {
        $parsed = $this->parser->parse($sql);
        $where = $parsed['WHERE'];
        $lastExpression = end($where);

        $this->assertEquals($literal, $where[2]['base_expr'], 'string literal');
        $this->assertEquals('2', $lastExpression['base_expr'], 'condition behind the string literal');
        $this->assertEquals($sql, $this->creator->create($parsed), 'parse and create cycle');
    }

    public function dataCommentCharacterWithinStringLiteral()
    {
        // [string $sql, string $literal]
        return array(
            array("SELECT a FROM t WHERE b = '#hash' AND c = 2", "'#hash'"),
            array("SELECT a FROM t WHERE b = '-- dashes' AND c = 2", "'-- dashes'"),
            array("SELECT a FROM t WHERE b = '/* block */' AND c = 2", "'/* block */'"),
            array("SELECT a FROM t WHERE b = 'it\\'s #1' AND c = 2", "'it\\'s #1'"),
            array("SELECT a FROM t WHERE b = 'it\\'s -- fine' AND c = 2", "'it\\'s -- fine'"),
            array("SELECT a FROM t WHERE b = 'it\\'s /* fine */' AND c = 2", "'it\\'s /* fine */'"),
            array("SELECT a FROM t WHERE b = \"it\\\"s #1\" AND c = 2", "\"it\\\"s #1\""),
            array("SELECT a FROM t WHERE b = 'a\\\\' AND c = 2", "'a\\\\'"),
        );
    }

    /**
     * A comment character within a value does not merge the value list.
     *
     * @dataProvider dataInsertWithCommentCharacterWithinStringLiteral
     * @param string $sql
     */
    public function testValueListIsNotMergedByCommentCharacterWithinStringLiteral($sql)
    {
        $parsed = $this->parser->parse($sql);

        $this->assertCount(3, $parsed['VALUES'][0]['data'], 'value list');
        $this->assertEquals($sql, $this->creator->create($parsed), 'parse and create cycle');
    }

    public function dataInsertWithCommentCharacterWithinStringLiteral()
    {
        // [string $sql]
        return array(
            array("INSERT INTO t (a, b, c) VALUES ('#tag', 'y', 'z')"),
            array("INSERT INTO t (a, b, c) VALUES ('l\\'agent', 'see #tag', 'z')"),
            array("INSERT INTO t (a, b, c) VALUES ('l\\'agent', 'see -- tag', 'z')"),
            array("INSERT INTO t (a, b, c) VALUES ('l\\'agent', 'plain', 'see #tag')"),
        );
    }

    /**
     * A comment outside of a string literal is still recognized as comment,
     * even behind a string literal containing an escaped quote.
     *
     * @dataProvider dataCommentOutsideStringLiteral
     * @param string $sql
     */
    public function testCommentOutsideStringLiteralIsStillAComment($sql)
    {
        $parsed = $this->parser->parse($sql);

        $this->assertEquals('a', $parsed['SELECT'][0]['base_expr'], 'column list without comment');
        $this->assertEquals('t', $this->getFirstTable($parsed['FROM']), 'table name without comment');
    }

    public function dataCommentOutsideStringLiteral()
    {
        // [string $sql]
        return array(
            array("SELECT a FROM t # trailing comment"),
            array("SELECT a FROM t -- trailing comment"),
            array("SELECT a FROM t /* trailing comment */"),
            array("SELECT a FROM t # comment with 'quote'"),
            array("SELECT a FROM t -- comment with 'quote'"),
            array("SELECT a FROM t WHERE b = 'l\\'agent' # trailing comment"),
            array("SELECT a FROM t WHERE b = 'l\\'agent' -- trailing comment"),
            array("# leading comment with 'quote'\nSELECT a FROM t"),
        );
    }

    /**
     * Both quote characters delimit a string in MySQL, so the opposite quote
     * character within a string literal is data and does not end it.
     *
     * @dataProvider dataMixedQuoteDelimiters
     * @param string $sql
     * @param string $literal
     */
    public function testOppositeQuoteCharacterWithinStringLiteral($sql, $literal)
    {
        $parsed = $this->parser->parse($sql);
        $where = $parsed['WHERE'];
        $lastExpression = end($where);

        $this->assertEquals($literal, $where[2]['base_expr'], 'string literal');
        $this->assertEquals('2', $lastExpression['base_expr'], 'condition behind the string literal');
    }

    public function dataMixedQuoteDelimiters()
    {
        // [string $sql, string $literal]
        return array(
            array("SELECT a FROM t WHERE b = \"it's fine\" AND c = 2", "\"it's fine\""),
            array("SELECT a FROM t WHERE b = \"it's #1\" AND c = 2", "\"it's #1\""),
            array("SELECT a FROM t WHERE b = \"it's -- x\" AND c = 2", "\"it's -- x\""),
            array("SELECT a FROM t WHERE b = 'say \"hi\" #1' AND c = 2", "'say \"hi\" #1'"),
            array("SELECT a FROM t WHERE b = 'say \"hi #1' AND c = 2", "'say \"hi #1'"),
            array("SELECT a FROM t WHERE b = \"'\" AND c = 2", "\"'\""),
            array("SELECT a FROM t WHERE b = '\"' AND c = 2", "'\"'"),
            array("SELECT a FROM t WHERE b = 'a\\\\' AND c = 2", "'a\\\\'"),
        );
    }

    /**
     * An escaped quote within a string literal must not change the way a comment
     * behind that literal is recognized.
     *
     * @dataProvider dataCommentBehindStringLiteral
     * @param string $escapedSql
     * @param string $plainSql
     */
    public function testCommentBehindEscapedQuoteIsRecognizedAsUsual($escapedSql, $plainSql)
    {
        $escaped = $this->parser->parse($escapedSql);
        $plain = $this->parser->parse($plainSql);

        $this->assertCount(count($plain['WHERE']), $escaped['WHERE'], 'WHERE section');
        $this->assertEquals($plain['FROM'], $escaped['FROM'], 'FROM section');
    }

    public function dataCommentBehindStringLiteral()
    {
        // [string $escapedSql, string $plainSql]
        return array(
            array(
                "SELECT a FROM t WHERE b = 'l\\'agent' # trailing comment",
                "SELECT a FROM t WHERE b = 'lagent' # trailing comment",
            ),
            array(
                "SELECT a FROM t WHERE b = 'l\\'agent' -- trailing comment",
                "SELECT a FROM t WHERE b = 'lagent' -- trailing comment",
            ),
            array(
                "SELECT a FROM t WHERE b = 'l\\'agent' /* trailing comment */",
                "SELECT a FROM t WHERE b = 'lagent' /* trailing comment */",
            ),
            array(
                "SELECT a FROM t WHERE b = \"it\\\"s\" # trailing comment",
                "SELECT a FROM t WHERE b = \"its\" # trailing comment",
            ),
        );
    }

    private function getFirstTable($from)
    {
        foreach ($from as $part) {
            if ($part['expr_type'] === 'table') {
                return $part['table'];
            }
        }

        return null;
    }
}
