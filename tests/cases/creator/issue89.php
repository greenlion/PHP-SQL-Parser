<?php
require_once dirname(__FILE__) . '/../../../src/PHPSQLParser.php';
require_once dirname(__FILE__) . '/../../../src/PHPSQLCreator.php';
require_once dirname(__FILE__) . '/../../test-more.php';


$sql = "select ut.id, ut.numero_cartella, ut.nome, ut.cognome, floor(DATEDIFF(de.`data`,ut.data_di_nascita)/365) as eta,
sx.valore as sesso, cd.valore as diagnosi_prevalente, co.valore as consapevolezza,
DATEDIFF(de.`data`,az.data_inizio_assistenza) as durata_assistenza_giorni, ld.valore as luogo_decesso,
ca.valore as carico_assistenza, if(sa.id is null, null,if(sa.fkey_cod_care_giver_interno__con_chi_vive=1,'si','no')) as vive_solo,
sn.valore as oltre_70
from gen_cms_utenti ut";
$parser = new PHPSQLParser($sql, true);
$creator = new PHPSQLCreator($parser->parsed);
$created = $creator->created;
$expected = getExpectedValue(dirname(__FILE__), 'issue89.sql', false);
ok($created === $expected, 'functions');

?>