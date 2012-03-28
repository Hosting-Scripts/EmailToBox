<?php
/**
 * Library of utility functions
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

function appLogEntry($message) {
	if (preg_match('/\n$/',$message) == 0) $message .= "\n";
	file_put_contents(APP_LOG, date('r').": ".$message, FILE_APPEND);
}

/**
 * Generates a key to be used to validate sessions
 * @param int $length Specify desired key lenght, if null, random length between 32 and 64 characters will be generated
 * @param boolean $mixedCase If true, a miz of upper case and lower-case will be used in the key, if false, only lower case will be used
 * @return string
 */
function generateKey($length=null,$mixedCase=true) {
	
	if ($mixedCase) $chars = str_split('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
	else $chars = str_split('0123456789abcdefghijklmnopqrstuvwxyz');
	
	$keyLen = (is_null($length)) ? mt_rand(32,64) : intval ($length);

	$key = '';
	while (strlen($key) < $keyLen) {
		$key .= $chars[mt_rand(0,(count($chars) - 1))];
	}
	
	return $key;
}



function formatByteSize($numBytes, $decimal=null) {
	// format file size
	$sizeSuffix = array('B','KB','MB','GB');
	$size = $numBytes;
	$returnVal = array();
	do {
		if (!is_null($decimal)) {
			$multiplier = 10 * $decimal;
			$returnVal = array('value'=> (round($size*$multiplier) / $multiplier), 'scale'=> array_shift($sizeSuffix), 'str'=> '');
		}
		elseif ($size < 10) {
			// by default, for files with size less than 10, show 1 decimal points in formatted size
			$returnVal = array('value'=> (round($size*10) / 10), 'scale'=> array_shift($sizeSuffix), 'str'=> '');
		}
		else {
			$returnVal = array('value'=> (round($size)), 'scale'=> array_shift($sizeSuffix), 'str'=> '');
		}
		$size = ($size / 1024);
	} while ($size >= 1 && count($sizeSuffix) > 0);
	
	$returnVal['str'] = $returnVal['value'] . ' ' . $returnVal['scale'];
	return $returnVal;
};

