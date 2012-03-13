<?php

require_once('php-sql-parser.php');
require_once('php-sql-creator.php');

class OracleSQLTranslator extends PHPSQLCreator {

    var $con;
    var $preventColumnRefs = false;

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

    private function getColumnNameFor($column) {
        if (strtolower($column) === 'uid') {
            $column = "uid_";
        }
        return $column;
    }

    private function getShortTableNameFor($table) {
        if (strtolower($table) === 'surveys_languagesettings') {
            $table = 'surveys_lngsettings';
        }
        return $table;
    }

    protected function processTable($parsed, $index) {
        if ($parsed['expr_type'] !== 'table') {
            return "";
        }

        $sql = $table = $this->getShortTableNameFor($parsed['table']);
        $sql .= " " . $this->processAlias($parsed['alias']);

        if ($index !== 0) {
            $sql = " " . $this->processJoin($parsed['join_type']) . " " . $sql;
            $sql .= $this->processRefType($parsed['ref_type']);
            $sql .= $this->processRefClause($parsed['ref_clause']);
        }
        return $sql;
    }

    protected function processColRef($parsed) {
        global $preventColumnRefs;

        if ($parsed['expr_type'] !== 'colref') {
            return "";
        }

        $colref = $parsed['base_expr'];
        $pos = strpos($colref, ".");
        if ($pos === false) {
            $pos = -1;
        }
        $table = trim(substr($colref, 0, $pos + 1), ".");
        $col = substr($colref, $pos + 1);

        # we have to change the column name, if the column is uid
        $col = $this->getColumnNameFor($col);

        # we have to change the tablereference, if the tablename is too long
        $table = $this->getShortTableNameFor($table);

        # if we have * as colref, we cannot use other columns
        $preventColumnRefs = $preventColumnRefs || (($table === "") && ($col === "*"));

        return (($table !== "") ? ($table . "." . $col) : $col);
    }

    protected function processSELECT($parsed) {
        global $preventColumnRefs;

        $sql = parent::processSELECT($parsed);
        if ($preventColumnRefs) {
            $sql = "SELECT *";
            $preventColumnRefs = false;
        }
        return $sql;
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
        print_r($parser->parsed);
        $sql = $this->create($parser->parsed);

        echo $sql . "\n";
        return $sql;
    }
}

/*
 * $sql = substr($sql, 0, $start) . "cast(substr(" . $columnInfo
                            . ",1,200) as varchar2(200))"
                            . substr($sql, $start + strlen($columnInfo));
 */

$parser = new OracleSQLTranslator(false);

$sql = "INSERT INTO surveys ( SID, OWNER_ID, ADMIN, ACTIVE, EXPIRES, STARTDATE, ADMINEMAIL, ANONYMIZED, FAXTO, FORMAT, SAVETIMINGS, TEMPLATE, LANGUAGE, DATESTAMP, USECOOKIE, ALLOWREGISTER, ALLOWSAVE, AUTOREDIRECT, ALLOWPREV, PRINTANSWERS, IPADDR, REFURL, DATECREATED, PUBLICSTATISTICS, PUBLICGRAPHS, LISTPUBLIC, HTMLEMAIL, TOKENANSWERSPERSISTENCE, ASSESSMENTS, USECAPTCHA, BOUNCE_EMAIL, EMAILRESPONSETO, EMAILNOTIFICATIONTO, TOKENLENGTH, SHOWXQUESTIONS, SHOWGROUPINFO, SHOWNOANSWER, SHOWQNUMCODE, SHOWWELCOME, SHOWPROGRESS, ALLOWJUMPS, NAVIGATIONDELAY, NOKEYBOARD, ALLOWEDITAFTERCOMPLETION ) 
VALUES ( 32225, 1, 'André', 'N', null, null, 'hello@zks.uni-leipzig.de', 'N', '', 'G', 'N', 'default', 'de-informal', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'N', 'N', a_function('2012-02-16','YYYY-MM-DD'), 'N', 'N', 'Y', 'Y', 'N', 'N', 'D', 'hello@zks.uni-leipzig.de', '', '', 15, 'Y', 'B', 'Y', 'X', 'Y', 'Y', 'N', 0, 'N', 'N' )";
$parser->process($sql);

$parser->process(
        "INSERT INTO users (users_name, password, full_name, parent_id, lang ,email, create_survey,create_user ,delete_user ,superadmin ,configurator ,manage_template , manage_label) VALUES ('admin', to_clob('92e32ca895ca2efd049dcfd79f47b19a6e2dc5f915fbd39e807e6775ae7569c3'), 'Your Name', 0, 'en', 'your-email@example.net', 1,1,1,1,1,1,1)");

$parser->process("INSERT INTO settings_global VALUES ('DBVersion','146')");
$parser->process(
        "SELECT a.*, c.*, u.users_name FROM SURVEYS as a  INNER JOIN SURVEYS_LANGUAGESETTINGS as c ON ( surveyls_survey_id = a.sid AND surveyls_language = a.language ) AND surveyls_survey_id=a.sid and surveyls_language=a.language  INNER JOIN USERS as u ON (u.uid=a.owner_id)  ORDER BY surveyls_title");
$parser->process(
        " SELECT *, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url  FROM SURVEYS AS a INNER JOIN SURVEYS_LANGUAGESETTINGS on (surveyls_survey_id=a.sid and surveyls_language=a.language)  order by active DESC, surveyls_title");

//$parser->process("CREATE TABLE answers ( qid number(11) default '0' NOT NULL, code varchar2(5) default '' NOT NULL, answer CLOB NOT NULL, assessment_value number(11) default '0' NOT NULL, sortorder number(11) NOT NULL, language varchar2(20) default 'en', scale_id number(3) default '0' NOT NULL, PRIMARY KEY (qid,code,language,scale_id) )");
//$parser->process("USE DATABASE `sdbprod`");
$parser->process("insert into SETTINGS_GLOBAL (stg_value,stg_name) values('','force_ssl')");

//$parser->process("SELECT * FROM SETTINGS_GLOBAL");
//$parser->process("SELECT stg_value FROM SETTINGS_GLOBAL where stg_name='force_ssl'");
//$parser->process("update SETTINGS_GLOBAL set stg_value='' where stg_name='force_ssl'");
//$parser->process("SELECT * FROM FAILED_LOGIN_ATTEMPTS WHERE ip='172.18.47.211'");
