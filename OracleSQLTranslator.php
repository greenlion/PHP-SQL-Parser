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
      $sql = " " . $parsed['name'];
      return $sql;
   }
   
   protected function processDELETE($parsed) {
      if (count($parsed('TABLES')) > 1) {
         die("cannot translate delete statement into Oracle dialect, multiple tables are not allowed.");
      }
      return "DELETE";
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

      $alias = "";
      if (isset($parsed['alias'])) {
         $alias = $this->processAlias($parsed['alias']);
      }

      return (($table !== "") ? ($table . "." . $col) : $col) . $alias;
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

   public function process($sql) {
      $parser = new PHPSQLParser($sql);
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


$sql = "INSERT INTO surveys_lngsettings ( SURVEYLS_SURVEY_ID, SURVEYLS_LANGUAGE, SURVEYLS_TITLE, SURVEYLS_DESCRIPTION, SURVEYLS_WELCOMETEXT, SURVEYLS_ENDTEXT, SURVEYLS_URL, SURVEYLS_URLDESCRIPTION, SURVEYLS_EMAIL_INVITE_SUBJ, SURVEYLS_EMAIL_INVITE, SURVEYLS_EMAIL_REMIND_SUBJ, SURVEYLS_EMAIL_REMIND, SURVEYLS_EMAIL_REGISTER_SUBJ, SURVEYLS_EMAIL_REGISTER, SURVEYLS_EMAIL_CONFIRM_SUBJ, SURVEYLS_EMAIL_CONFIRM, SURVEYLS_DATEFORMAT, EMAIL_ADMIN_NOTIFICATION_SUBJ, EMAIL_ADMIN_NOTIFICATION, EMAIL_ADMIN_RESPONSES_SUBJ, EMAIL_ADMIN_RESPONSES, SURVEYLS_NUMBERFORMAT )
VALUES ( 53313, 'de-informal', 'Mappensurvey', 'Hier antworten nur Mappen!', 'Hallo Du Mappe', 'Geh heim du Mappe!', '', '', 'Einladung zur einer Umfrage', 'Hallo {FIRSTNAME},

Hiermit möchten wir dich zu einer Umfrage einladen.

Der Titel der Umfrage ist
''{SURVEYNAME}''

''{SURVEYDESCRIPTION}''

Um an dieser Umfrage teilzunehmen, klicke bitte auf den unten stehenden Link.

Mit freundlichen Grüßen,

{ADMINNAME} ({ADMINEMAIL})a

----------------------------------------------
Klicke hier um die Umfrage zu starten:
{SURVEYURL}

Wenn Du an dieser Umfrage nicht teilnehmen und keine weiteren Erinnerungen erhalten möchtest, klicke bitte auf den folgenden Link:
{OPTOUTURL}', 'Erinnerung an die Teilnahme an einer Umfrage', 'Hallo {FIRSTNAME},

Vor kurzem haben wir Dich zu einer Umfrage eingeladen.

Zu unserem Bedauern haben wir bemerkt, dass Du die Umfrage noch nicht ausgefüllt hast. Wir möchten Dir mitteilen, dass die Umfrage noch aktiv ist und würden uns freuen, wenn Du teilnehmen könntest.

Der Titel der Umfrage ist
''{SURVEYNAME}''

''{SURVEYDESCRIPTION}''

Um an dieser Umfrage teilzunehmen, klicke bitte auf den unten stehenden Link.

Mit freundlichen Grüßen,

{ADMINNAME} ({ADMINEMAIL})b

----------------------------------------------
Klicken Du hier um die Umfrage zu starten:
{SURVEYURL}

Wenn Du an dieser Umfrage nicht teilnehmen und keine weiteren Erinnerungen erhalten möchtest, klicke bitte auf den folgenden Link:
{OPTOUTURL}', 'Registrierungsbestätigung für Teilnahmeumfrage', 'Hallo {FIRSTNAME},

Du (oder jemand, der Deine E-Mail benutzt hat) hat sich für eine Umfrage mit dem Titel {SURVEYNAME} angemeldet.

Um an dieser Umfrage teilzunehmen, klicke bitte auf den folgenden Link.nn{SURVEYURL}

Wenn Du irgendwelche Fragen zu dieser Umfrage hast oder wenn Du Dich _nicht_ für diese Umfrage angemeldet hast und Du glaubst, dass Dir diese E-Mail irrtümlicherweise zugeschickt worden ist, kontaktiere bitte {ADMINNAME} unter {ADMINEMAIL}.', 'Bestätigung für die Teilnahme an unserer Umfrage', 'Hallo {FIRSTNAME},

Vielen Dank für die Teilnahme an der Umfrage mit dem Titel {SURVEYNAME}. Deine Antworten wurden bei uns gespeichert.

Wenn du irgendwelche Fragen zu dieser E-Mail hast, kontaktiere bitte {ADMINNAME} unter {ADMINEMAIL}.

Mit freundlichen Grüßen,

{ADMINNAME}', 1, 'Antwortabsendung für Umfrage {SURVEYNAME}', 'Hallo,

Eine neue Antwort wurde für die Umfrage ''{SURVEYNAME}'' abgegeben.

Klicke auf den folgenden Link um die Umfrage neu zu laden:
{RELOADURL}

Klicke auf den folgenden Link um den Antwortdatensatz anzusehen:
{VIEWRESPONSEURL}

Klicke auf den folgenden Link um den Antwortdatensatz zu bearbeiten:
{EDITRESPONSEURL}

Um die Statistik zu sehen, klicke hier:
{STATISTICSURL}', 'Antwortabsendung für Umfrage %s', 'Hallo,

Eine neue Antwort wurde für die Umfrage ''{SURVEYNAME}'' abgegeben.

Klicke auf den folgenden Link um die Umfrage neu zu laden:
{RELOADURL}

Klicke auf den folgenden Link um den Antwortdatensatz anzusehen:
{VIEWRESPONSEURL}

Klicke auf den folgenden Link um den Antwortdatensatz zu bearbeiten:
{EDITRESPONSEURL}

Um die Statistik zu sehen, klicke hier:
{STATISTICSURL}


Die folgenden Antworten wurden vom Teilnehmer gegeben:
{ANSWERTABLE}', 1 )";
$parser->process($sql);



//$sql = "SELECT a.uid, a.users_name FROM USERS AS a LEFT JOIN (SELECT uid AS id FROM USER_IN_GROUPS WHERE ugid = 1) AS b ON a.uid = b.id WHERE id IS NULL ORDER BY a.users_name";
//$parser->process($sql);

//$sql = "INSERT INTO surveys ( SID, OWNER_ID, ADMIN, ACTIVE, EXPIRES, STARTDATE, ADMINEMAIL, ANONYMIZED, FAXTO, FORMAT, SAVETIMINGS, TEMPLATE, LANGUAGE, DATESTAMP, USECOOKIE, ALLOWREGISTER, ALLOWSAVE, AUTOREDIRECT, ALLOWPREV, PRINTANSWERS, IPADDR, REFURL, DATECREATED, PUBLICSTATISTICS, PUBLICGRAPHS, LISTPUBLIC, HTMLEMAIL, TOKENANSWERSPERSISTENCE, ASSESSMENTS, USECAPTCHA, BOUNCE_EMAIL, EMAILRESPONSETO, EMAILNOTIFICATIONTO, TOKENLENGTH, SHOWXQUESTIONS, SHOWGROUPINFO, SHOWNOANSWER, SHOWQNUMCODE, SHOWWELCOME, SHOWPROGRESS, ALLOWJUMPS, NAVIGATIONDELAY, NOKEYBOARD, ALLOWEDITAFTERCOMPLETION )
//VALUES ( 32225, 1, 'André', 'N', null, null, 'hello@zks.uni-leipzig.de', 'N', '', 'G', 'N', 'default', 'de-informal', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'N', 'N', a_function('2012-02-16','YYYY-MM-DD'), 'N', 'N', 'Y', 'Y', 'N', 'N', 'D', 'hello@zks.uni-leipzig.de', '', '', 15, 'Y', 'B', 'Y', 'X', 'Y', 'Y', 'N', 0, 'N', 'N' )";
//$parser->process($sql);

//$parser->process(
//        "INSERT INTO users (users_name, password, full_name, parent_id, lang ,email, create_survey,create_user ,delete_user ,superadmin ,configurator ,manage_template , manage_label) VALUES ('admin', to_clob('92e32ca895ca2efd049dcfd79f47b19a6e2dc5f915fbd39e807e6775ae7569c3'), 'Your Name', 0, 'en', 'your-email@example.net', 1,1,1,1,1,1,1)");

//$parser->process("INSERT INTO settings_global VALUES ('DBVersion','146')");
//$parser->process(
//        "SELECT a.*, c.*, u.users_name FROM SURVEYS as a  INNER JOIN SURVEYS_LANGUAGESETTINGS as c ON ( surveyls_survey_id = a.sid AND surveyls_language = a.language ) AND surveyls_survey_id=a.sid and surveyls_language=a.language  INNER JOIN USERS as u ON (u.uid=a.owner_id)  ORDER BY surveyls_title");
//$parser->process(
//        " SELECT *, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url  FROM SURVEYS AS a INNER JOIN SURVEYS_LANGUAGESETTINGS on (surveyls_survey_id=a.sid and surveyls_language=a.language)  order by active DESC, surveyls_title");

//$parser->process("CREATE TABLE answers ( qid number(11) default '0' NOT NULL, code varchar2(5) default '' NOT NULL, answer CLOB NOT NULL, assessment_value number(11) default '0' NOT NULL, sortorder number(11) NOT NULL, language varchar2(20) default 'en', scale_id number(3) default '0' NOT NULL, PRIMARY KEY (qid,code,language,scale_id) )");
//$parser->process("USE DATABASE `sdbprod`");
//$parser->process("insert into SETTINGS_GLOBAL (stg_value,stg_name) values('','force_ssl')");

//$parser->process("SELECT * FROM SETTINGS_GLOBAL");
//$parser->process("SELECT stg_value FROM SETTINGS_GLOBAL where stg_name='force_ssl'");
//$parser->process("update SETTINGS_GLOBAL set stg_value='' where stg_name='force_ssl'");
//$parser->process("SELECT * FROM FAILED_LOGIN_ATTEMPTS WHERE ip='172.18.47.211'");
