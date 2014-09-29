<?php

$config = array('host'	=> 'localhost', 
		'user'		=> 'trinity',
		'password'	=> 'trinity',
		'db_auth'		=> 'auth',
		'db_chars'		=> 'characters');

header('Access-Control-Allow-Origin: *'); 
		
$mysqli_auth = @new mysqli($config['host'], $config['user'], $config['password'], $config['db_auth']);
if ($mysqli_auth->connect_errno) {
    die("Connection to auth database is unavailable");
}

$mysqli_chars = @new mysqli($config['host'], $config['user'], $config['password'], $config['db_chars']);
if ($mysqli_auth->connect_errno) {
    die("Connection to characters database is unavailable");
}

if (isset($_GET['method'], $_GET['device_token'])) {
    if (!preg_match("/^([0-9]+)_([0-9a-f]{40})$/", $_GET['device_token'], $device_token)) { // after that we don't need to check these values for possible sqli
	die ('device_token format error!');
    } else {
		$getandcheck_device_key = $_GET['device_token'];
	}
} else die ('Method and/or device_token is not set');

// link get and check device with trinity account
if ($_GET['method'] == 'accountLink') {
	// todo: bruteforce protection
	if (isset($_GET['os']) && in_array($_GET['os'], array('ios', 'android', 'win'))) {
		$os = $_GET['os'];
	} else die('os param is not set or not in [ios|win|android]');

	if (isset($_POST['username'], $_POST['sha_pass_hash'])) {
	    $username = $mysqli_auth->real_escape_string($_POST['username']);
	    $sha_pass_hash = $mysqli_auth->real_escape_string($_POST['sha_pass_hash']);
	    $account = $mysqli_auth->query("select id,username from account where username = '{$username}' and sha_pass_hash = '{$sha_pass_hash}'");
	    if ($account->num_rows > 0) {
		$account = $account->fetch_array();
		echo json_encode(array('status'=>'ok','username'=>$account['username']));
		if ($mysqli_chars->query("select 1 from characters_getandcheck where getandcheck_device_key = '".$getandcheck_device_key."'")->num_rows==0) {
		    // new link
		    $mysqli_chars->query("insert into characters_getandcheck values ('".$getandcheck_device_key."', ".$account['id'].", now(), now(), '".$_SERVER['REMOTE_ADDR']."', '".$os."')"); 
		} else {
		    // update account id
			// if by some reason we don't removed link for this device
		    $mysqli_chars->query("update characters_getandcheck set wow_account_id = ".$account['id']." where getandcheck_device_key = '".$getandcheck_device_key."'");
		}
	    } else echo json_encode(array('status'=>'error','message'=>'username or password is not correct'));
	} else echo json_encode(array('status'=>'error','message'=>'username or password is not set'));
}

if ($_GET['method'] == 'isLinked') {
    $account = $mysqli_chars->query("select wow_account_id from characters_getandcheck where getandcheck_device_key = '{$getandcheck_device_key}'");
    if ($account->num_rows > 0) {
	$account = $account->fetch_array();
	$username = $mysqli_auth->query("select username from account where id = ".$account['wow_account_id']);
	if ($username->num_rows > 0) {
	    $username = $username->fetch_array();
	    echo json_encode(array('status'=>'ok','username'=>$username['username']));
		// update last login
		$mysqli_chars->query("update characters_getandcheck set last_login = NOW(), last_ip = '".$_SERVER['REMOTE_ADDR']."' where wow_account_id = ".$account['wow_account_id']." and getandcheck_device_key = '{$getandcheck_device_key}'");
	}
    } else echo json_encode(array('status'=>'error'));
}

if ($_GET['method'] == 'unlink') {
    $account = $mysqli_chars->query("select wow_account_id from characters_getandcheck where getandcheck_device_key = '{$getandcheck_device_key}'");
    if ($account->num_rows > 0) {
	$account = $account->fetch_array();
	$mysqli_chars->query("delete from characters_getandcheck where wow_account_id = ".$account['wow_account_id']." and getandcheck_device_key = '{$getandcheck_device_key}'");
	    echo json_encode(array('status'=>'ok'));
    } else echo json_encode(array('status'=>'error'));
}