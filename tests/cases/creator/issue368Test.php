<?php
/**
 * issue368.php
 *
 * Test case for PHPSQLCreator.
 */

namespace PHPSQLParser\Test\Creator;

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

class Issue368Test extends \PHPUnit\Framework\TestCase
{
    /*
     * https://github.com/greenlion/PHP-SQL-Parser/issues/368
     * CURRENT_TIMESTAMP is detected as a reserved word an generate errors when used in JOIN clause
     */
    public function testIssue368()
    {
        $sql = "SELECT foo FROM barTable LEFT JOIN bazTable ON barTable.a = bazTable.a AND bazTable.d <= CURRENT_TIMESTAMP"; // KO

        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();

        $parser->parse($sql);

        $this->assertEquals($sql, $creator->create($parser->parsed));
    }
}
