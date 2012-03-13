<?php

require_once(dirname(__FILE__) . '/php-sql-parser.php');
require_once(dirname(__FILE__) . '/php-sql-creator.php');
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
