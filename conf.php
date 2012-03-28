<?php
/**
 * Constant definitions
 * 
 * @author Bruno Morency bruno@context.io
 * @copyright DokDok inc.
 * @license GNU Affero General Public License v3 http://www.gnu.org/licenses/agpl.html
 */

define('APP_URL','https://emailtobox.com');
define('DB_NAME', 'EmailToBox');

define('BASE_DIR', dirname(__FILE__));
define('LIBS_DIR', BASE_DIR.'/libs');
define('HANDLERS_DIR', BASE_DIR.'/handlers');
define('INTERFACE_DIR', BASE_DIR.'/interface');

define('TMP_FILE_DIR',BASE_DIR."/etc/tmp");
define('SESSION_PATH',BASE_DIR."/etc/sess");
define('WEBHOOKS_TRACE_LOG',BASE_DIR."/etc/logs/webhooks.log");
define('APP_LOG',BASE_DIR."/etc/logs/app.log");
define('PHP_ERROR_LOG',BASE_DIR."/etc/logs/php-error.log");

define('BOX_PHP_SDK', LIBS_DIR."/box-php/Box_Rest_Client.php");
define('CONTEXTIO',LIBS_DIR."/PHP-ContextIO/class.contextio.php");
define('UTILS',LIBS_DIR."/utils.inc");

define('INTERFACE_SIGNUP_STEP_BOX',INTERFACE_DIR."/signup-step-box.phtml");
define('INTERFACE_SIGNUP_STEP_EMAIL',INTERFACE_DIR."/signup-step-email.phtml");
define('INTERFACE_SIGNUP_STEP_FINAL',INTERFACE_DIR."/signup-step-final.phtml");
define('INTERFACE_SIGNUP_ERROR',INTERFACE_DIR."/signup-error.phtml");
define('INTERFACE_HOME',INTERFACE_DIR."/home.phtml");
define('INTERFACE_APP',INTERFACE_DIR."/app.phtml");

define('HANDLERS_SIGNUP', HANDLERS_DIR."/signup.php");
define('HANDLERS_WEBHOOK', HANDLERS_DIR."/webhook.php");
define('HANDLERS_APP', HANDLERS_DIR."/app.php");
define('HANDLERS_LOGIN', HANDLERS_DIR."/login.php");

define('LIBRARY_VERSION_JQUERY','1.7.0');
define('LIBRARY_VERSION_JQUERYUI','1.8.16');

define('DEFAULT_FOLDERPAIR_NAME_BOX','EmailToBox');
define('DEFAULT_FOLDERPAIR_NAME_EMAIL','EmailToBox');
define('ALLOW_UNKNOWN_WEBHOOKS_DELETE',false);
define('FOLDERPAIR_HISTORY_LIST_SIZE',20);
define('USAGE_QUOTA_FREE',250*1024*1024);

$keys = json_decode(file_get_contents(BASE_DIR."/auth-keys.json"), true);
define('BOX_API_KEY', $keys['box']['apiKey']);
define('CONTEXTIO_CONSUMER_KEY', $keys['ContextIO']['key']);
define('CONTEXTIO_CONSUMER_SECRET', $keys['ContextIO']['secret']);
unset($keys);

function __autoload($className) {
	$className = strtolower($className);
	if (file_exists(LIBS_DIR.'/class.'. $className .'.inc')) require_once(LIBS_DIR.'/class.'. $className .'.inc');
}
