<?php
ini_set('display_errors', '0');
//ini_set('display_errors', 'on');
//error_reporting(E_ALL);

require_once(dirname(__FILE__)."/const/const.inc");
require_once(dirname(__FILE__)."/func.inc");
//require_once(dirname(__FILE__)."/const/login_func.inc");
//$result = check_user($db, "1");

//ini_set('include_path', CLIENT_LIBRALY_PATH);
ini_set('include_path', get_include_path() . PATH_SEPARATOR . CLIENT_LIBRALY_PATH);
//ini_set('include_path', get_include_path() . PATH_SEPARATOR . realpath(str_replace('\\', '/', dirname(__FILE__)).'/../vendor'));

require_once "Google/autoload.php";
//require_once("GoogleDriveAuth.php");
//require_once('../vendor/autoload.php');
require_once(dirname(__FILE__)."/../vendor/autoload.php");
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
//set_time_limit(60);

class google_drive_util {

	var $client;
	var $spreadsheetFeed;
	var $spreadsheetService;

	function __construct() {

		// --- createClient ---
		$this->client = new Google_Client();
		$this->client->setApplicationName('calender-project');
		$this->client->setClientId(CLIENT_ID);
		$cred = new Google_Auth_AssertionCredentials(
		    SERVICE_ACCOUNT_NAME,
		    array('https://spreadsheets.google.com/feeds'),
		    file_get_contents(KEY_FILE)
		);
		$this->client->setAssertionCredentials($cred);

		// --- getAccessToken ---
		if($this->client->isAccessTokenExpired()) {
	    $this->client->getAuth()->refreshTokenWithAssertion($cred);
		}
		$obj_token  = json_decode($this->client->getAccessToken());
		$accessToken = $obj_token->access_token;
		//$obj = json_decode($client->getAccessToken());
    //$token = $obj->{'access_token'};
    //write($accessToken);
    //read($accessToken);

		// --- initServiceRequest ---
		$serviceRequest = new DefaultServiceRequest($accessToken);
		ServiceRequestFactory::setInstance($serviceRequest);

		$this->spreadsheetService = new Google\Spreadsheet\SpreadsheetService();
		$this->spreadsheetFeed = $this->spreadsheetService->getSpreadsheets();

	}

/*
session_start();
$client = new Google_Client();
$client->setClientId('�N���C�A���gID');
$client->setClientSecret('�N���C�A���g�V�[�N���b�g');
$client->setRedirectUri('���_�C���N�gURI');

// ������ă��_�C���N�g������ URL �� code ���t������Ă���
// code ����������󂯎���āA�F�؂���
if (isset($_GET['code'])) {
    // �F��
    $client->authenticate($_GET['code']);
    $_SESSION['token'] = $client->getAccessToken();
    // ���_�C���N�g GET�p�����[�^�������Ȃ����邽�߁i���Ȃ��Ă�OK�j
    header('Location: http://'.$_SERVER['HTTP_HOST']."/");
    exit;
}

// �Z�b�V��������A�N�Z�X�g�[�N�����擾
if (isset($_SESSION['token'])) {
    // �g�[�N���Z�b�g
    $client->setAccessToken($_SESSION['token']);
}

// �g�[�N�����Z�b�g����Ă�����
if ($client->getAccessToken()) {
    try {
        echo "Google Drive Api �A�g�����I<br>";
        $obj = json_decode($client->getAccessToken());
        $token = $obj->{'access_token'};
        write($token);
        read($token);
    } catch (Google_Exception $e) {
        echo $e->getMessage();
    }
} else {
    // �F�؃X�R�[�v(�͈�)�̐ݒ�
    $client->setScopes(Google_Service_Drive::DRIVE);
    // �ꗗ���擾����ꍇ��https://spreadsheets.google.com/feeds���K�v
    $client->addScope('https://spreadsheets.google.com/feeds');

    $authUrl = $client->createAuthUrl();
    echo '<a href="'.$authUrl.'">�A�v���P�[�V�����̃A�N�Z�X�������Ă��������B</a>';
}
*/

}
?>

