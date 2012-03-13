<?php

require_once('php-sql-parser.php');
require_once('php-sql-creator.php');

class OracleSQLParser extends PHPSQLCreator {

    var $con;

    public function __construct($con) {
        parent::__construct();
        $this->con = $con;
    }

    private function preprint($s, $return = false) {
        $x = "<pre>";
        $x .= print_r($s, 1);
        $x .= "</pre>";
        if ($return)
            return $x;
        else
            print $x;
    }

    protected function processAlias($parsed) {
        if ($parsed === false) {
            return "";
        }
        # we don't need an AS between expression and alias
        $sql = $parsed['name'];
        return $sql;
    }

    protected function processColRef($parsed) {
        if ($parsed['expr_type'] !== 'colref') {
            return "";
        }
        
        # we have to change the tablereference, if the tablename is too long
        # we have to change the column name, if the column is uid
        
        return $parsed['base_expr'];
    }

    # prevents parsing of create-oracle.php statements
    # maybe we run into problems with Limesurvey internal creation statements
    private function isNativeStatement($sql) {
        $sql = trim($sql);
        if (stripos($sql, "CREATE") === 0) {
            return true;
        }
        if (stripos($sql, "ALTER") === 0) {
            return true;
        }
        if (stripos($sql, "COMMENT") === 0) {
            return true;
        }
        if (stripos($sql, "USE") === 0) {
            return true;
        }
        return false;
    }

    public function process($sql) {
        if ($this->isNativeStatement($sql)) {
            return $sql;
        }

        $parser = new PHPSQLParser($sql, true);
        $sql = $this->create($parser->parsed);

        echo $sql . "\n";
        return $sql;
    }
}

$parser = new OracleSQLParser(false);
$parser->process("SELECT a.*, c.*, u.users_name FROM SURVEYS as a  INNER JOIN SURVEYS_LANGUAGESETTINGS as c ON ( surveyls_survey_id = a.sid AND surveyls_language = a.language ) AND surveyls_survey_id=a.sid and surveyls_language=a.language  INNER JOIN USERS as u ON (u.uid=a.owner_id)  ORDER BY surveyls_title");
//$parser->process(" SELECT a.*, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url  FROM SURVEYS AS a INNER JOIN SURVEYS_LANGUAGESETTINGS on (surveyls_survey_id=a.sid and surveyls_language=a.language)  order by active DESC, surveyls_title");
//$parser->process(" INSERT INTO settings_global VALUES ('DBVersion', '146')");
//$parser->process("CREATE TABLE answers ( qid number(11) default '0' NOT NULL, code varchar2(5) default '' NOT NULL, answer CLOB NOT NULL, assessment_value number(11) default '0' NOT NULL, sortorder number(11) NOT NULL, language varchar2(20) default 'en', scale_id number(3) default '0' NOT NULL, PRIMARY KEY (qid,code,language,scale_id) )");
//$parser->process("USE DATABASE `sdbprod`");
//$parser->process("insert into SETTINGS_GLOBAL (stg_value,stg_name) values('','force_ssl')");
//$parser->process("SELECT * FROM SETTINGS_GLOBAL");
//$parser->process("SELECT stg_value FROM SETTINGS_GLOBAL where stg_name='force_ssl'");
//$parser->process("update SETTINGS_GLOBAL set stg_value='' where stg_name='force_ssl'");
//$parser->process("SELECT * FROM FAILED_LOGIN_ATTEMPTS WHERE ip='172.18.47.211'");
