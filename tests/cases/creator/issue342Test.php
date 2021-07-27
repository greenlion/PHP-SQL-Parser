<?php

namespace PHPSQLParser\Test\Creator;
use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

class issue342Test extends \PHPUnit_Framework_TestCase
{
    public function testIssue299()
    {
        $sql = 'SELECT if(true,true,false) FROM t';

        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();

        $parser->parse($sql, true);

        $this->assertEquals($sql, $creator->create($parser->parsed));
    }
}
