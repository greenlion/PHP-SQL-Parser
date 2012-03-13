<?php

require_once(dirname(__FILE__) . "/../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../test-more.php");

$parser = new PHPSQLParser();

$sql = "select usr_id, usr_login, case id_tipousuario when 1 then 'Usuario CVE' when 2 then concat('Usuario Vendedor -', codigovendedor, '-') when 3 then concat('Usuario Vendedor Meson (', codigovendedor, ')') end tipousuario, CONCAT( usr_nombres, ' ', usr_apellidos ) as nom_com, cod_local from usuarios where usr_estado <> 2 order by 3, 1, 4";
$p = $parser->parse($sql);

print_r($p);
echo serialize($p);

$expected = getExpectedValue('issue15.serialized');
eq_array($p, $expected, 'parenthesis problem on issue 15');

