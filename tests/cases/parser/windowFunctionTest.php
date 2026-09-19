<?php
/**
 * windowFunctionTest.php
 *
 * Test case for the window function support of PHPSQLParser and PHPSQLCreator.
 *
 * It covers the parse tree of the OVER clause, the WINDOW clause, the frame
 * clause and the null treatment clause, the recreation of all of them, and it
 * replays the window function statements of the PostgreSQL regression test
 * suite (see tests/fixtures/postgresql-window.sql).
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
 *
 */

namespace PHPSQLParser\Test\Parser;

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\utils\ExpressionType;

class windowFunctionTest extends \PHPUnit\Framework\TestCase {

    const POSTGRESQL_FIXTURE = '/../../fixtures/postgresql-window.sql';

    /**
     * Window function support, checked in one go. The helpers below are no test
     * methods on purpose, so a run of the suite reports one test for the whole
     * feature instead of one per detail.
     */
    public function testWindowFunctions() {
        $this->checkInlineWindowFunction();
        $this->checkPartitionAndOrder();
        $this->checkKeywordsAreNotColumnReferences();
        $this->checkFrameClause();
        $this->checkNamedWindows();
        $this->checkNullTreatment();
        $this->checkAmbiguousKeywords();
        $this->checkPositions();
        $this->checkRecreation();
        $this->checkPostgreSQLRegressionSuite();
    }

    protected function parse($sql) {
        $parser = new PHPSQLParser();
        return $parser->parse($sql);
    }

    protected function create($sql) {
        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        return $creator->create($parser->parse($sql));
    }

    /**
     * Counts the window functions within a parse tree.
     */
    protected function countWindowFunctions($node) {
        if (!is_array($node)) {
            return 0;
        }
        $count = 0;
        if (isset($node['expr_type']) && $node['expr_type'] === ExpressionType::WINDOW_FUNCTION) {
            $count++;
        }
        foreach ($node as $part) {
            if (is_array($part)) {
                $count += $this->countWindowFunctions($part);
            }
        }
        return $count;
    }

    /**
     * A function call followed by OVER becomes a window function, which holds
     * the function and the window specification within its sub_tree.
     */
    protected function checkInlineWindowFunction() {
        $parsed = $this->parse("SELECT ROW_NUMBER() OVER (PARTITION BY dept) AS rn FROM emp");
        $item = $parsed['SELECT'][0];

        $this->assertSame(ExpressionType::WINDOW_FUNCTION, $item['expr_type']);
        $this->assertSame('ROW_NUMBER() OVER (PARTITION BY dept)', $item['base_expr']);
        $this->assertSame('rn', $item['alias']['name']);
        $this->assertCount(2, $item['sub_tree']);

        list($function, $spec) = $item['sub_tree'];
        $this->assertSame(ExpressionType::SIMPLE_FUNCTION, $function['expr_type']);
        $this->assertSame('ROW_NUMBER', $function['base_expr']);
        $this->assertSame(ExpressionType::WINDOW_SPEC, $spec['expr_type']);

        // the function keeps its own type and its arguments
        $parsed = $this->parse("SELECT SUM(sal) OVER (PARTITION BY dept) FROM emp");
        list($function, $spec) = $parsed['SELECT'][0]['sub_tree'];
        $this->assertSame(ExpressionType::AGGREGATE_FUNCTION, $function['expr_type']);
        $this->assertSame('SUM', $function['base_expr']);
        $this->assertSame('sal', $function['sub_tree'][0]['base_expr']);
        $this->assertFalse($spec['window_name']);
        $this->assertFalse($spec['order']);
        $this->assertFalse($spec['frame']);

        // a window function can be part of a larger expression
        $parsed = $this->parse("SELECT SUM(b) OVER (PARTITION BY a) / 2 AS half FROM t");
        $item = $parsed['SELECT'][0];
        $this->assertSame(ExpressionType::EXPRESSION, $item['expr_type']);
        $this->assertSame(ExpressionType::WINDOW_FUNCTION, $item['sub_tree'][0]['expr_type']);
        $this->assertSame(ExpressionType::OPERATOR, $item['sub_tree'][1]['expr_type']);

        // an alias without AS must still be detected
        $parsed = $this->parse("SELECT DENSE_RANK() OVER (ORDER BY sal DESC) rnk FROM emp");
        $item = $parsed['SELECT'][0];
        $this->assertSame(ExpressionType::WINDOW_FUNCTION, $item['expr_type']);
        $this->assertFalse($item['alias']['as']);
        $this->assertSame('rnk', $item['alias']['name']);
    }

    /**
     * PARTITION BY and ORDER BY are lists of expressions, the sort direction
     * belongs to the ORDER BY entry.
     */
    protected function checkPartitionAndOrder() {
        $parsed = $this->parse("SELECT RANK() OVER (PARTITION BY dept, region ORDER BY sal DESC, hired) FROM emp");
        $spec = $parsed['SELECT'][0]['sub_tree'][1];

        $this->assertCount(2, $spec['partition']);
        $this->assertSame('dept', $spec['partition'][0]['base_expr']);
        $this->assertSame('region', $spec['partition'][1]['base_expr']);

        $this->assertCount(2, $spec['order']);
        $this->assertSame('sal', $spec['order'][0]['base_expr']);
        $this->assertSame('DESC', $spec['order'][0]['direction']);
        $this->assertSame('hired', $spec['order'][1]['base_expr']);
        $this->assertSame('ASC', $spec['order'][1]['direction']);
    }

    /**
     * The keywords of a window specification must not show up as column
     * references, otherwise a caller cannot collect the used columns.
     */
    protected function checkKeywordsAreNotColumnReferences() {
        $parsed = $this->parse(
            "SELECT SUM(sal) OVER (PARTITION BY dept ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM emp");
        $spec = $parsed['SELECT'][0]['sub_tree'][1];

        $expressions = array();
        array_walk_recursive($spec, function ($value, $key) use (&$expressions) {
            if ($key === 'base_expr') {
                $expressions[] = $value;
            }
        });

        foreach (array('PARTITION', 'ROWS', 'PRECEDING', 'CURRENT', 'ROW', 'BETWEEN') as $keyword) {
            $this->assertNotContains($keyword, $expressions, $keyword . ' must not be a column reference');
        }
    }

    /**
     * All frame units, both extent forms, every kind of bound and all four
     * exclusions.
     */
    protected function checkFrameClause() {
        $parsed = $this->parse(
            "SELECT SUM(sal) OVER (ORDER BY hired ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM emp");
        $frame = $parsed['SELECT'][0]['sub_tree'][1]['frame'];

        $this->assertSame(ExpressionType::WINDOW_FRAME, $frame['expr_type']);
        $this->assertSame('ROWS', $frame['unit']);
        $this->assertFalse($frame['exclude']);
        $this->assertSame(ExpressionType::WINDOW_FRAME_BOUND, $frame['start']['expr_type']);
        $this->assertSame('PRECEDING', $frame['start']['direction']);
        $this->assertFalse($frame['start']['unbounded']);
        $this->assertSame('2', $frame['start']['value']['base_expr']);
        $this->assertSame('CURRENT ROW', $frame['end']['direction']);
        $this->assertFalse($frame['end']['value']);

        // without BETWEEN there is no end bound
        $parsed = $this->parse("SELECT SUM(sal) OVER (ORDER BY hired RANGE UNBOUNDED PRECEDING) FROM emp");
        $frame = $parsed['SELECT'][0]['sub_tree'][1]['frame'];
        $this->assertSame('RANGE', $frame['unit']);
        $this->assertTrue($frame['start']['unbounded']);
        $this->assertSame('PRECEDING', $frame['start']['direction']);
        $this->assertFalse($frame['end']);

        // the offset of a bound can be an expression
        $parsed = $this->parse(
            "SELECT SUM(x) OVER (ORDER BY d RANGE BETWEEN INTERVAL 5 DAY PRECEDING AND CURRENT ROW) FROM t");
        $frame = $parsed['SELECT'][0]['sub_tree'][1]['frame'];
        $this->assertSame(ExpressionType::EXPRESSION, $frame['start']['value']['expr_type']);
        $this->assertSame('INTERVAL 5 DAY', $frame['start']['value']['base_expr']);

        $parsed = $this->parse(
            "SELECT SUM(sal) OVER (GROUPS BETWEEN CURRENT ROW AND 3 FOLLOWING EXCLUDE NO OTHERS) FROM emp");
        $frame = $parsed['SELECT'][0]['sub_tree'][1]['frame'];
        $this->assertSame('GROUPS', $frame['unit']);
        $this->assertSame('NO OTHERS', $frame['exclude']);
        $this->assertSame('FOLLOWING', $frame['end']['direction']);
        $this->assertSame('3', $frame['end']['value']['base_expr']);

        foreach (array('CURRENT ROW', 'GROUP', 'TIES') as $exclusion) {
            $parsed = $this->parse("SELECT SUM(a) OVER (ROWS UNBOUNDED PRECEDING EXCLUDE $exclusion) FROM t");
            $this->assertSame($exclusion, $parsed['SELECT'][0]['sub_tree'][1]['frame']['exclude']);
        }
    }

    /**
     * The WINDOW clause is a section of its own, a window function can refer
     * to the names it defines.
     */
    protected function checkNamedWindows() {
        $parsed = $this->parse("SELECT SUM(sal) OVER w AS running FROM emp WINDOW w AS (PARTITION BY dept ORDER BY hired)");

        $spec = $parsed['SELECT'][0]['sub_tree'][1];
        $this->assertSame('w', $spec['window_name']);
        $this->assertFalse($spec['partition']);
        $this->assertFalse($spec['order']);
        $this->assertFalse($spec['frame']);

        $this->assertArrayHasKey('WINDOW', $parsed);
        $this->assertCount(1, $parsed['WINDOW']);
        $def = $parsed['WINDOW'][0];
        $this->assertSame(ExpressionType::WINDOW_DEF, $def['expr_type']);
        $this->assertSame('w', $def['window_name']);
        $this->assertSame('w AS (PARTITION BY dept ORDER BY hired)', $def['base_expr']);
        $this->assertSame(ExpressionType::WINDOW_SPEC, $def['spec']['expr_type']);
        $this->assertSame('dept', $def['spec']['partition'][0]['base_expr']);
        $this->assertSame('hired', $def['spec']['order'][0]['base_expr']);

        // the WINDOW clause must not be swallowed by the FROM clause
        $table = $parsed['FROM'][0];
        $this->assertSame('emp', $table['table']);
        $this->assertSame('emp', $table['base_expr']);
        $this->assertFalse($table['alias']);

        // a definition can inherit another window, the inherited name is part
        // of the specification
        $parsed = $this->parse(
            "SELECT SUM(sal) OVER w, AVG(sal) OVER w2 FROM emp WINDOW w AS (PARTITION BY dept), w2 AS (w ORDER BY hired)");
        $this->assertCount(2, $parsed['WINDOW']);
        $this->assertSame('w', $parsed['WINDOW'][0]['window_name']);
        $this->assertSame('w2', $parsed['WINDOW'][1]['window_name']);
        $this->assertSame('w', $parsed['WINDOW'][1]['spec']['window_name']);
        $this->assertSame('hired', $parsed['WINDOW'][1]['spec']['order'][0]['base_expr']);

        // an inline specification can reference a named window as well
        $parsed = $this->parse("SELECT SUM(sal) OVER (w ORDER BY hired) FROM emp WINDOW w AS (PARTITION BY dept)");
        $spec = $parsed['SELECT'][0]['sub_tree'][1];
        $this->assertSame('w', $spec['window_name']);
        $this->assertSame('hired', $spec['order'][0]['base_expr']);

        // window names can be quoted
        $parsed = $this->parse("SELECT SUM(sal) OVER `my win` FROM emp WINDOW `my win` AS (PARTITION BY dept)");
        $this->assertSame('`my win`', $parsed['SELECT'][0]['sub_tree'][1]['window_name']);
        $this->assertSame(array('my win'), $parsed['SELECT'][0]['sub_tree'][1]['no_quotes']['parts']);
        $this->assertSame('`my win`', $parsed['WINDOW'][0]['window_name']);
        $this->assertSame(array('my win'), $parsed['WINDOW'][0]['no_quotes']['parts']);
    }

    /**
     * The null treatment clause stands between the function and the OVER.
     */
    protected function checkNullTreatment() {
        foreach (array('IGNORE NULLS', 'RESPECT NULLS') as $treatment) {
            $parsed = $this->parse("SELECT first_value(orbit) $treatment OVER w FROM planets");
            $item = $parsed['SELECT'][0];

            $this->assertSame(ExpressionType::WINDOW_FUNCTION, $item['expr_type']);
            $this->assertSame($treatment, $item['null_treatment']);
            $this->assertSame("first_value(orbit) $treatment OVER w", $item['base_expr']);
            $this->assertSame('first_value', $item['sub_tree'][0]['base_expr']);
            $this->assertSame('w', $item['sub_tree'][1]['window_name']);
        }

        $parsed = $this->parse("SELECT first_value(orbit) OVER w FROM planets");
        $this->assertArrayNotHasKey('null_treatment', $parsed['SELECT'][0]);
    }

    /**
     * OVER, WINDOW and IGNORE are not reserved within this dialect. Only the
     * window function syntax may claim them, every other use keeps working.
     */
    protected function checkAmbiguousKeywords() {
        $parsed = $this->parse("SELECT over FROM t");
        $this->assertSame(ExpressionType::COLREF, $parsed['SELECT'][0]['expr_type']);
        $this->assertSame('over', $parsed['SELECT'][0]['base_expr']);

        $parsed = $this->parse("SELECT a FROM window");
        $this->assertArrayNotHasKey('WINDOW', $parsed);
        $this->assertSame('window', $parsed['FROM'][0]['table']);

        $parsed = $this->parse("SELECT a FROM window AS w");
        $this->assertArrayNotHasKey('WINDOW', $parsed);
        $this->assertSame('window', $parsed['FROM'][0]['table']);
        $this->assertSame('w', $parsed['FROM'][0]['alias']['name']);

        // IGNORE as an index hint
        $parsed = $this->parse("SELECT a FROM t IGNORE INDEX (i)");
        $this->assertArrayNotHasKey('OPTIONS', $parsed);
        $this->assertSame('IGNORE INDEX', $parsed['FROM'][0]['hints'][0]['hint_type']);

        // IGNORE as part of the INSERT statement
        $parsed = $this->parse("INSERT IGNORE INTO t (a) VALUES (1)");
        $this->assertSame(ExpressionType::RESERVED, $parsed['INSERT'][0]['expr_type']);
        $this->assertSame('IGNORE', $parsed['INSERT'][0]['base_expr']);

        // IGNORE as a statement option
        $parsed = $this->parse("UPDATE IGNORE t SET a = 1");
        $this->assertSame('IGNORE', $parsed['OPTIONS'][0]['base_expr']);
    }

    protected function checkPositions() {
        $sql = "SELECT SUM(sal) OVER (PARTITION BY dept ORDER BY hired) AS s FROM emp";
        $parser = new PHPSQLParser();
        $parsed = $parser->parse($sql, true);

        $item = $parsed['SELECT'][0];
        $this->assertArrayHasKey('position', $item);
        $this->assertSame(7, $item['position']);
        $this->assertSame('SUM(sal) OVER (PARTITION BY dept ORDER BY hired)',
                          substr($sql, $item['position'], strlen($item['base_expr'])));
    }

    /**
     * The creator must rebuild every statement from the parse tree. The ORDER
     * BY of a window spells out the direction, like the ORDER BY clause does.
     */
    protected function checkRecreation() {
        $statements = array(
            "SELECT ROW_NUMBER() OVER () FROM t",
            "SELECT ROW_NUMBER() OVER (PARTITION BY dept) AS rn FROM emp",
            "SELECT ROW_NUMBER() OVER (PARTITION BY dept, region ORDER BY sal DESC, hired ASC) AS rn FROM emp",
            "SELECT SUM(sal) OVER (ORDER BY hired ASC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM emp",
            "SELECT SUM(sal) OVER (ORDER BY hired ASC ROWS UNBOUNDED PRECEDING) FROM emp",
            "SELECT SUM(sal) OVER (RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) FROM emp",
            "SELECT SUM(sal) OVER (GROUPS BETWEEN CURRENT ROW AND 3 FOLLOWING EXCLUDE TIES) FROM emp",
            "SELECT SUM(x) OVER (ORDER BY d ASC RANGE BETWEEN INTERVAL 5 DAY PRECEDING AND CURRENT ROW) FROM t",
            "SELECT LAG(sal,1,0) OVER (PARTITION BY dept ORDER BY hired ASC) AS prev FROM emp",
            "SELECT COUNT(*) OVER (PARTITION BY a) FROM t",
            "SELECT DENSE_RANK() OVER (ORDER BY sal DESC) rnk FROM emp",
            "SELECT a, SUM(b) OVER (PARTITION BY a) / 2 AS half FROM t",
            "SELECT ROUND(SUM(b) OVER (PARTITION BY a),2) FROM t",
            "SELECT * FROM (SELECT RANK() OVER (PARTITION BY a ORDER BY b ASC) r FROM t) x WHERE r = 1",
            "SELECT SUM(sal) OVER (PARTITION BY dept) FROM emp ORDER BY dept ASC LIMIT 10",
            "SELECT first_value(a) IGNORE NULLS OVER (PARTITION BY b) FROM t",
            "SELECT lead(a,1) RESPECT NULLS OVER (PARTITION BY b) FROM t",
            "SELECT SUM(sal) OVER w AS running FROM emp WINDOW w AS (PARTITION BY dept ORDER BY hired ASC)",
            "SELECT SUM(sal) OVER w, AVG(sal) OVER w2 FROM emp WINDOW w AS (PARTITION BY dept), w2 AS (w ORDER BY hired ASC)",
            "SELECT SUM(sal) OVER (w ORDER BY hired ASC) FROM emp WINDOW w AS (PARTITION BY dept)",
            "SELECT SUM(sal) OVER `my win` FROM emp WINDOW `my win` AS (PARTITION BY dept)",
            // a definition always needs the parenthesis, also if it only inherits
            "SELECT SUM(sal) OVER w2 FROM emp WINDOW w AS (PARTITION BY dept), w2 AS (w)",
        );

        foreach ($statements as $sql) {
            $this->assertSame($sql, $this->create($sql), 'should be recreated: ' . $sql);
        }

        // the creator must use the parse tree, not the base_expr
        $parser = new PHPSQLParser("SELECT SUM(sal) OVER (PARTITION BY dept) FROM emp");
        $parsed = $parser->parsed;
        $spec =& $parsed['SELECT'][0]['sub_tree'][1];
        $spec['partition'][0]['base_expr'] = 'region';
        $spec['partition'][0]['no_quotes']['parts'] = array('region');

        $creator = new PHPSQLCreator($parsed);
        $this->assertSame("SELECT SUM(sal) OVER (PARTITION BY region) FROM emp", $creator->created);
    }

    /**
     * Replays the window function statements of the PostgreSQL regression test
     * suite. Every statement must be parsed, every OVER must build a window
     * function, every WINDOW clause must become a section, and the recreated
     * statement must be stable.
     */
    protected function checkPostgreSQLRegressionSuite() {
        $lines = file(__DIR__ . self::POSTGRESQL_FIXTURE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();
        $count = 0;

        foreach ($lines as $sql) {
            $sql = trim($sql);
            if ($sql === "" || substr($sql, 0, 2) === '--') {
                continue;
            }
            $count++;

            $parsed = $parser->parse($sql);
            $this->assertNotEmpty($parsed, 'should be parsed: ' . $sql);

            $this->assertSame(preg_match_all('/\bOVER\b/i', $sql), $this->countWindowFunctions($parsed),
                              'every OVER should build a window function: ' . $sql);

            if (preg_match('/\bWINDOW\s+\S+\s+AS\s*\(/i', $sql)) {
                $this->assertArrayHasKey('WINDOW', $parsed, 'WINDOW should be a section: ' . $sql);
                foreach ($parsed['WINDOW'] as $def) {
                    $this->assertSame(ExpressionType::WINDOW_DEF, $def['expr_type'], $sql);
                    $this->assertNotEmpty($def['window_name'], $sql);
                    $this->assertSame(ExpressionType::WINDOW_SPEC, $def['spec']['expr_type'], $sql);
                }
            }

            $created = $creator->create($parsed);
            $this->assertSame($created, $creator->create($parser->parse($created)),
                              'recreation should be stable: ' . $sql);
        }

        $this->assertSame(194, $count, 'the PostgreSQL fixture should provide all its statements');
    }
}
