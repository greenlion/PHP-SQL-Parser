<?php

namespace PHPSQLParser\Test\Parser;

use PHPSQLParser\PHPSQLParser;

class IgnoreCommentsTest extends \PHPUnit_Framework_TestCase
{
    public function testComments1()
    {
        $sqlWithComment = 'SELECT a, -- inline comment in SELECT section
                        b 
                    FROM test';
        $sqlWithoutComment = 'SELECT a,
                        b 
                    FROM test';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in SELECT section');
    }

    public function testComments2()
    {
        $sqlWithComment = 'SELECT a, /* 
                            multi line 
                            comment
                        */
                        b 
                    FROM test';
        $sqlWithoutComment = 'SELECT a,
                        b 
                    FROM test';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'multi line comment');
    }

    public function testComments3()
    {
        $sqlWithComment = 'SELECT a
                    FROM test -- inline comment in FROM section';
        $sqlWithoutComment = 'SELECT a
                    FROM test';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in FROM section');
    }

    public function testComments4()
    {
        $sqlWithComment = 'SELECT a
                    FROM test
                    WHERE id = 3 -- inline comment in WHERE section
                    AND b > 4';
        $sqlWithoutComment = 'SELECT a
                    FROM test
                    WHERE id = 3
                    AND b > 4';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in WHERE section');
    }

    public function testComments5()
    {
        $sqlWithComment = 'SELECT a
                    FROM test
                    LIMIT -- inline comment in LIMIT section
                     10';
        $sqlWithoutComment = 'SELECT a
                    FROM test
                    LIMIT
                     10';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in LIMIT section');
    }

    public function testComments6()
    {
        $sqlWithComment = 'SELECT a
                    FROM test
                    ORDER BY -- inline comment in ORDER BY section
                     a DESC';
        $sqlWithoutComment = 'SELECT a
                    FROM test
                    ORDER BY
                     a DESC';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in ORDER BY section');
    }

    public function testComments7()
    {
        $sqlWithComment = 'INSERT INTO a (id) -- inline comment in INSERT section
                    VALUES (1)';
        $sqlWithoutComment = 'INSERT INTO a (id)
                    VALUES (1)';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in INSERT section');
    }

    public function testComments8()
    {
        $sqlWithComment = 'INSERT INTO a (id) 
                    VALUES (1) -- inline comment in VALUES section';
        $sqlWithoutComment = 'INSERT INTO a (id) 
                    VALUES (1)';

        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in VALUES section');
    }

    public function testComments9()
    {
        $sqlWithComment = 'INSERT INTO a (id) -- inline comment in INSERT section;
                    SELECT id -- inline comment in SELECT section
                    FROM x';
        $sqlWithoutComment = 'INSERT INTO a (id)
                    SELECT id
                    FROM x';
        $this->commonAssert($sqlWithComment, $sqlWithoutComment, 'inline comment in SELECT section');
    }

    private function commonAssert($sqlWithComment, $sqlWithoutComment, $assertMessage)
    {
        $withComment = (new PHPSQLParser($sqlWithComment, false, ['ignore_comment' => true]))->parsed;
        $withoutComment = (new PHPSQLParser($sqlWithoutComment, false, ['ignore_comment' => false]))->parsed;
        $this->assertEquals($withComment, $withoutComment, $assertMessage);
    }
}

?>
