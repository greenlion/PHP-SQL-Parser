<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

try {
    $sql = "CREATE TABLE IF NOT EXISTS `engine4_urdemo_causebug` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `extra` int(11)  NOT NULL DEFAULT 56,
  PRIMARY KEY (`id`),
  INDEX client_idx (id)
) ENGINE=InnoDB;";
    $parser = new PHPSQLParser($sql, true);
    $p = $parser->parsed;
} catch (Exception $e) {
    $p = array();
}
ok(count($p) > 0, 'position calculation should handle INDEX');

?>