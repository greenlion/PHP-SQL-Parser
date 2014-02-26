<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

try {
    $sql = "CREATE TABLE IF NOT EXISTS `engine4_urdemo_causebug` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `extra` int(11)  NOT NULL DEFAULT 56,
  PRIMARY KEY (`id`),
  INDEX client_idx (id)
) ENGINE=InnoDB;";
    $parser = new PHPSQLParser($sql);
    $p = $parser->parsed;
} catch (Exception $e) {
    $p = array();
}
ok($p['TABLE']['create-def']['sub_tree'][1]['sub_tree'][1]['sub_tree'][5]['expr_type'] === ExpressionType::DEF_VALUE,
        'column definition with DEFAULT value');

?>
