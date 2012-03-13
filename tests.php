<?php

/**
 * Helper function for getting the expected array
 * from a file as serialized string.
 * Returns an unserialized value from the given file.
 *
 * @param unknown_type $filename
 */
function getExpectedValue($filename) {
	$content = file_get_contents(dirname(__FILE__) . "/r/".$filename);
	return unserialize($content);
}

/**
 * execute all tests
 */
require_once(dirname(__FILE__) . '/t/aliases.php');
require_once(dirname(__FILE__) . '/t/backtick.php');
require_once(dirname(__FILE__) . '/t/from.php');
require_once(dirname(__FILE__) . '/t/gtltop.php');
require_once(dirname(__FILE__) . '/t/left.php');
require_once(dirname(__FILE__) . '/t/nested.php');
require_once(dirname(__FILE__) . '/t/select.php');
require_once(dirname(__FILE__) . '/t/update.php');
require_once(dirname(__FILE__) . '/t/zero.php');

exit;
