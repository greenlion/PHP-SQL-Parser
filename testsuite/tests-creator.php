<?php

require_once(dirname(__FILE__) . '/../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../php-sql-creator.php');
require_once(dirname(__FILE__) . '/test-more.php');

function process($sql) {
    $parser = new PHPSQLParser($sql);
    //echo print_r($parser->parsed, true)."\n";
    $creator = new PHPSQLCreator($parser->parsed);
    //echo $creator->created;
    return $creator->created;
}

$sql = " SELECT a.*, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url  FROM SURVEYS AS a INNER JOIN SURVEYS_LANGUAGESETTINGS on (surveyls_survey_id=a.sid and surveyls_language=a.language)  order by active DESC, surveyls_title";
$created = process($sql);
$expected = getExpectedValue('create1.sql', false);
ok($created === $expected, 'a join');

$sql = "SELECT * FROM contacts WHERE contacts.id IN (SELECT email_addr_bean_rel.bean_id FROM email_addr_bean_rel, email_addresses WHERE email_addresses.id = email_addr_bean_rel.email_address_id AND email_addr_bean_rel.deleted = 0 AND email_addr_bean_rel.bean_module = 'Contacts' AND email_addresses.email_address IN (\"test@example.com\"))";
$created = process($sql);
$expected = getExpectedValue('create2.sql', false);
ok($created === $expected, 'a subquery and in-lists');

$sql = 'SELECT 	SUM( 10 ) as test FROM account';
$created = process($sql);
$expected = getExpectedValue('create3.sql', false);
ok($created === $expected, 'a function');

$sql = 'SELECT *
    FROM (t1 LEFT JOIN t2 ON t1.a=t2.a)
         LEFT JOIN t3
         ON t2.b=t3.b OR t2.b IS NULL';
$created = process($sql);
$expected = getExpectedValue('create4.sql', false);
ok($created === $expected, 'left joins and table-expression');

$sql = "SELECT * FROM t1 LEFT JOIN (t2, t3, t4)
                 ON (t2.a=t1.a AND t3.b=t1.b AND t4.c=t1.c)";
$created = process($sql);
$expected = getExpectedValue('create5.sql', false);
ok($created === $expected, 'table-expression on second position');

$sql = "SELECT qid FROM QUESTIONS WHERE gid='1' and language='de-informal' ORDER BY question_order, title ASC";
$created = process($sql);
$expected = getExpectedValue('create6.sql', false);
ok($created === $expected, 'explicit ASC statement');

$sql = "INSERT INTO test (`name`, `test`) VALUES ('\'Superman\'', ''), ('\'Superman\'', '')";
$created = process($sql);
$expected = getExpectedValue('create7.sql', false);
ok($created === $expected, 'multiple records within INSERT');

$sql = "INSERT INTO test (`name`, `test`) VALUES ('\'Superman\'', '')";
$created = process($sql);
$expected = getExpectedValue('create8.sql', false);
ok($created === $expected, 'a simple INSERT statement');

$sql = "INSERT INTO test (`name`, `test`) VALUES ('\'Superman\'', ''), ('\'sdfsd\'', '')";
$created = process($sql);
$expected = getExpectedValue('create9.sql', false);
ok($created === $expected, 'multiple records within INSERT (2)');

$sql = "SELECT * FROM `table` `t` WHERE ( ( UNIX_TIMESTAMP() + 3600 ) > `t`.`expires` ) ";
$created = process($sql);
$expected = getExpectedValue('create10.sql', false);
ok($created === $expected, 'expressions with function within WHERE clause');

