<?php

require_once(dirname(__FILE__) . '/php-sql-parser.php');
require_once(dirname(__FILE__) . '/php-sql-creator.php');
require_once(dirname(__FILE__) . '/test-more.php');


$sql = " SELECT a.*, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url  FROM SURVEYS AS a INNER JOIN SURVEYS_LANGUAGESETTINGS on (surveyls_survey_id=a.sid and surveyls_language=a.language)  order by active DESC, surveyls_title";
echo $sql."\n";

$parser = new PHPSQLParser();
$parser->parse($sql);

echo print_r($parser->parsed, true)."\n";

$creator = new PHPSQLCreator();
$creator->create($parser->parsed);

echo $creator->created;

