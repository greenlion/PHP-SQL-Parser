<?php
/**
 * withTest.php
 *
 * Test case for PHPSQLCreator "WITH" (Common Table Expressions) support.
 */

namespace PHPSQLParser\Test\Creator;

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

class withTest extends \PHPUnit\Framework\TestCase {

    public function testWithStatement() {
        $sql = "WITH cte AS (SELECT id FROM users WHERE active = 1) SELECT * FROM cte";
        $parser = new PHPSQLParser($sql);
        $creator = new PHPSQLCreator($parser->parsed);
        $created = $creator->created;
        $this->assertSame($sql, $created, 'WITH statement (CTE) should be recreated correctly');
    }

    public function testComplexWithStatement() {
        $sql = "WITH RankedOrders AS (SELECT c.fullname AS CustomerName, ch.name AS ChannelName, o.expected_delivery_date AS OrderDate, SUM(oi.qty) AS TotalQuantity, o.id AS Ordernumer, ROW_NUMBER() OVER(PARTITION BY o.customer_id ORDER BY o.expected_delivery_date DESC) AS ranks FROM orders o INNER JOIN order_items oi ON o.id = oi.order_id INNER JOIN customers c ON c.id = o.customer_id INNER JOIN channels ch ON ch.id = o.channel_id WHERE o.delivery_state = 'delivered' AND oi.unit_price != 0 AND o.order_type NOT IN ('delivery_request', 'replacement') AND YEAR(o.expected_delivery_date) = 2025 AND ch.id NOT IN (30, 46) GROUP BY o.customer_id, c.fullname, o.expected_delivery_date, o.id, ch.name) SELECT CustomerName, ChannelName, OrderDate, TotalQuantity, Ordernumer, ranks FROM RankedOrders WHERE ranks <= 4 ORDER BY CustomerName ASC, OrderDate DESC";
        $parser = new PHPSQLParser($sql);
        $creator = new PHPSQLCreator($parser->parsed);
        $created = $creator->created;
        $this->assertSame($sql, $created, 'Complex WITH statement (CTE) should be recreated correctly');
    }
}