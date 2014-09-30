<?php
// config start

$path = '/home/trinity/getandcheck/'; // Change path here! Need absolute path - solve any crontab problems

// config end

if (file_exists("{$path}getandcheck-flush-config.php")) {
	include("{$path}getandcheck-flush-config.php");
} else die ("Config error. Edit and copy getandcheck-flush-config.php.example to getandcheck-flush-config.php \n");

$file_lock = $path.'/getandcheck.lock';

$content = '<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Awesome WoW server</title>

    <!-- Bootstrap core CSS -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
<style>
.navbar-collapse.collapse {
display: block!important;
}

.navbar-nav>li, .navbar-nav {
float: left !important;
}


.navbar-nav.navbar-right:last-child {
margin-right: -15px !important;
}

.navbar-right {
float: right!important;
}</style>

  </head>

  <body>
    <div class="container">
      <!-- Static navbar -->
      <nav class="navbar navbar-default navbar-fixed-bottom" role="navigation">
  <div class="container" style="overflow: scroll;" >
          <div class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
              <li class="active"><a href="#info" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-info-sign"> Information</a></li>
              <li><a href="#account" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-user"> Account</span></a></li>
            </ul>
          </div><!--/.nav-collapse -->
  </div>
</nav>


<div class="tab-content">
  <div class="tab-pane active" id="info">

<h3>Awesome server</h3>
      <table class="table table-striped">
      <thead>
        <tr>
          <th>Realm</th>
          <th>Status</th>
          <th>Online</th>
		  <th>Uptime</th>
        </tr>
      </thead>
      <tbody>';


// preventing race condition
if (!file_exists($file_lock)) {
	$lock = fopen($path."getandcheck.lock", "w");
} else die ("Race condition alert - file ".$path."getandcheck.lock is exists. Exit.\n");

/* check connection */
$mysqli_auth = @new mysqli($config_auth['host'], $config_auth['user'], $config_auth['password'], $config_auth['db']);
if ($mysqli_auth->connect_errno) {
    die("Connection to auth database is unavailable");
}

$mysqli_auth->query("SET NAMES utf8"); // preventing any problems with names in utf-8, you know


$realms = $mysqli_auth->query("select id, name, address, port from realmlist");
while ($realm = $realms->fetch_array()) {
	if (in_array($realm['id'], $ignoredRealms)) {
		continue;
	} else {
		// lets connect to realm specific databases
		if (array_key_exists($realm['id'], $config_realms)) {
			$mysqli_world = @new mysqli($config_realms[$realm['id']]['host'], $config_realms[$realm['id']]['user'], $config_realms[$realm['id']]['password'], $config_realms[$realm['id']]['db_world']);
				if ($mysqli_world->connect_errno) {
					// just notify, it is not critical
					die("Connection problems to world database, realm id ".$realm['id']);
				}
				
			$mysqli_char = @new mysqli($config_realms[$realm['id']]['host'], $config_realms[$realm['id']]['user'], $config_realms[$realm['id']]['password'], $config_realms[$realm['id']]['db_chars']);
			if (!$mysqli_char->connect_errno) {
					$mysqli_char->query("SET NAMES utf8");
			} else die("Connection problems to characters database, realm id ".$realm['id']);
		} else die("Realm {$realm['id']} is not in ignored list and not exist in config.\n");
		
	}
    $isOnline = @fsockopen($realm['address'], $realm['port'], $err, $errstr, 3);
    if ($isOnline) {
	$isOnline = '<span class="label label-success">Online</div>';
	// online stats
			$online = $mysqli_char->query("select count(1) as online from characters where online > 0;")->fetch_array(); // preventing incorrect stats if bots enabled and in online (online = 2)
			$online = $online['online'];
			
			$uptime = $mysqli_auth->query("select uptime from uptime where realmid = {$realm['id']} order by starttime desc limit 1")->fetch_array();
			$days = floor($uptime['uptime'] / (60 * 60 * 24));
			if (!isset($uptime['uptime'])) {
				$uptime['uptime'] = 0; // if no records for this realm
			}
			$uptime['uptime'] -= $days * (60 * 60 * 24);
			$hours = floor($uptime['uptime'] / (60 * 60));
			$uptime['uptime'] -= $hours * (60 * 60);
			$minutes = floor($uptime['uptime'] / 60);
			$uptime = "{$days} d {$hours}h {$minutes}m";
    } else {
		$isOnline = '<span class="label label-default">Offline</div>';
		$online = "N/A";
		$uptime = "N/A";
    }
    $content .= "<tr>
	    <td>{$realm['name']}</td>
	    <td>{$isOnline}</td>
	    <td>{$online}</td>
	    <td>{$uptime}</td>
	  </tr>";
	  
	// lets check unread mails for this realm
	$file_last_id = $path.'/getandcheck-lastid-realm-'.$realm['id'].'.txt';
	if (!file_exists($file_last_id)) {
		// init part - first run. We just want to store current max id of mail
		$fileWithLastID = fopen($file_last_id, "w") or die("Unable to save data to temp file! Try: chmod 777 {$filename}");
		$result = $mysqli_char->query("select max(id) as max from mail")->fetch_array();
		printf("Max id is: %s. In next run we will start flush push notifications!\n", $result['max']);
		fwrite($fileWithLastID, $result['max']);
		fclose($fileWithLastID);
	} else {
		$lastID = file_get_contents($file_last_id);
		// select unread mails for users who have linked account with getandcheck.com
		// todo: deliver_time !?
		$sql = "select g.getandcheck_device_key, c.name as receiver_name, m.subject, m.stationery, m.sender from mail as m inner join characters as c on m.receiver = c.guid inner join characters_getandcheck as g on c.account=g.wow_account_id where (m.checked &1 )= 0 and m.id > {$lastID} and c.online <= {$checkOnlinePlayers}";
		$pushs = $mysqli_char->query($sql);
		while ($push = $pushs->fetch_array()) {
			$sender = $mysqli_char->query("select name from characters where guid = {$push['sender']}")->fetch_array();
			switch ($push['stationery']) {
				case 41: // Normal mail layout
					$message = "New mail from {$sender['name']} to {$push['receiver_name']}: {$push['subject']}";
					break;
				case 61: // GM (Blizzard)
					$message = "New mail from [Blizzard] to {$push['receiver_name']}: {$push['subject']}";
					break;
				case 62: // Auction
					$item = explode(":",$push['subject']);
					// todo, need dbc data. Currently - only item name
					$item = $mysqli_world->query("select name from item_template where entry = ".$item[0])->fetch_array();
					$message = "New mail from [Auction] to {$push['receiver_name']}: {$item['name']}";
					break;
					
				default:
					$message = "New mail from {$push['name']} to {$receiver['name']}: {$push['subject']}";
			}
			// send pushs to users!
			$getParams = array ('developer_key' => $getandcheck_config['developer_key'],
							'method' => 'sendPrivatePushNotification',
							'user_token' => $push['getandcheck_device_key'],
							'community_id' => $getandcheck_config['community_id'],
							'api_version'=>'1.0');
				$url = 'http://getandcheck.com/api/?'.http_build_query($getParams);
				if ($ch = curl_init()) {
				 $ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url );
					curl_setopt($ch, CURLOPT_POST, true );
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, 'message='.urlencode($message));
					$result = curl_exec($ch);
					if (curl_errno($ch)) {
						echo 'Error: ' . curl_error( $ch );
					}
					curl_close( $ch );
					// echo $result;
				} else echo 'Error while init cURL';
		}
		// save new lastID
		$fileWithLastID = fopen($file_last_id, "w") or die("Unable to save data to temp file! Try: chmod 777 {$file_last_id}");
		$result = $mysqli_char->query("select max(id) as max from mail")->fetch_array();
		fwrite($fileWithLastID, $result['max']);
		fclose($fileWithLastID);
	}
	// unread mails check end
}


$content .= '</tbody>
    </table>
	
	<h3>Last messages</h3>
	<table class="table" id="table_messages">
      <thead>
        <tr>
          <th>Date</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody>
      </tbody>
    </table>
  </div>
 
  <div class="tab-pane" id="account">
  <h2>Account</h2>
<div class="panel-group" id="link" style="display:none">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-parent="#accordion" href="#linkAccount">
          <button class="btn-primary">Link your mobile device with WoW account!</button>
        </a>
      </h4>
    </div>
    <div id="linkAccount" class="panel-collapse collapse">
      <div class="panel-body">
		  <div class="form-group">
			<label>Login</label>
			<input type="text" class="form-control" id="username" placeholder="Login">
		  </div>
		  <div class="form-group">
			<label>Password</label>
			<input type="password" class="form-control" id="password" placeholder="Password">
		  </div>
		  <button class="btn btn-default" onclick="linkAccount_()">Submit</button>
      </div>
    </div>
  </div><div id="link_status"></div>
</div>
  </div>
</div>

    </div> <!-- /container -->


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <script src="/js/bootstrap.min.js"></script>
	<script src="'.$getandcheck_config['endpoint_url'].'getandcheck-endpoint.js"></script>
	<script>
  $(function () {
    $(\'#myTab a:last\').tab(\'show\')
  })
</script>
	<script>
	$.getJSON( "/api/?method=getLastMessages&device_token=$$device_token$$", function( data ) {
	  var items = [];
	  $.each( data, function( key, val ) {
		t = $.parseJSON(JSON.stringify(val));
		var row=\'<tr><td>\'+t.timestamp+\'</td><td>\'+t.message+\'</td></tr>\';
		$(\'#table_messages tbody\').append(row);
	  });
});
</script>
<script>
	/*
		lets start WoW part
		change only this param
	*/
	var base_url = \''.$getandcheck_config['endpoint_url'].'\';
	
	function linkAccount_() {
		data = linkAccount($(\'#username\').val(), $(\'#password\').val(), \'$$device_token$$\', \'$$os$$\', base_url);
		response = $.parseJSON(data);
		if (response.status == \'ok\') {
			$(\'#account\').html(\'<h2>Welcome, \'+response.username+\'!</h2>\');
		} else {
			$(\'#link_status\').html(\'<div class="alert alert-warning alert-dismissible" role="alert">\
  <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>\
  <strong>Error!</strong> Username or password is not valid</div>\');
		}
	}
	
	
	function logout_() {
		data = logout(\'$$device_token$$\', base_url);
		response = $.parseJSON(data);
		if (response.status == \'ok\') {
			$(\'#account\').html(\'<h2>This device has been successfully unlinked</h2>\');
		} else {
			$(\'#link_status\').html(\'<div class="alert alert-warning alert-dismissible" role="alert">\
  <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>\
  <strong>Error!</strong> Something went wrong while unlinking...</div>\');
		}
	}
	
	
	function showPrivateMessages() {
	$.getJSON( "/api/?method=getLastMessages&device_token=$$device_token$$&isPrivate=1", function( data ) {
	  var items = [];
	  $.each( data, function( key, val ) {
		t = $.parseJSON(JSON.stringify(val));
		message = $(\'<div/>\').text(t.message).html(); /* prevent xss, only here, because there is not trusted data */
		var row=\'<tr><td>\'+t.timestamp+\'</td><td>\'+message+\'</td></tr>\';
		$(\'#table_messages_private tbody\').append(row);
	  });
	});
	}
	
	var state = isLinked(\'$$device_token$$\', base_url);
	state = $.parseJSON(state);
	if (state.status ==\'ok\') {
		$(\'#account\').html(\'<h2>Welcome, \'+state.username+\'!</h2>\');
		$(\'<button type="button" onclick = "logout_()" class="btn btn-default">Logout</button>\').appendTo(\'#account\');
		$(\'<h3>Last messages</h3>\' +
	\'<table class="table" id="table_messages_private">\' +
      \'<thead>\' +
        \'<tr>\' +
          \'<th>Date</th>\' +
          \'<th>Message</th>\' +
        \'</tr>\' +
      \'</thead>\' +
      \'<tbody>\' +
      \'</tbody>\' +
    \'</table>\').appendTo(\'#account\');
	 showPrivateMessages();
	 
		/* todo
		// password change form
		// new ingame mails (depends from server method)
		// battle.net auth (depends from trinitycore)
		// logout
		*/
	} else {
		$(\'#link\').show();
	}
</script>
  </body>
</html>';

$getParams = array ('developer_key' => $getandcheck_config['developer_key'],
					'method' => 'updateContent',
					'community_id' => $getandcheck_config['community_id'],
					'api_version'=>'1.0');
$url = 'http://getandcheck.com/api/?'.http_build_query($getParams);
if ($ch = curl_init()) {
 $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_POST, true );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'content='.urlencode($content));
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error( $ch );
    }
    curl_close( $ch );
    // echo $result;
} else echo 'Error while init cURL';

unlink($file_lock);
?>
