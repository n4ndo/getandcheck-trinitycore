## getandcheck-trinitycore

Scripts for integration trinitycore server with http://getandcheck.com service

Get & Check -  it's a service for delivering your content to users (without registration!) with mobile devices. It can be everything: timetable of impending conference, status of online game server or something else. It's very simple: you need to create community, users download mobile app and join to your community

### Steps to install

Step 1 - Get&Check

	1) Register/login here http://getandcheck.com/
	2) Open http://getandcheck.com/communities/new/
	3) Fill form and click "create"
	4) Remember your community id (listed on main page)
	5) Open http://getandcheck.com/for-developers/ and copy-paste developer API key (available only for logged in users)
    
That it! You got community id and developer API key

Step 2 - server side

	1) Copy getandcheck-endpoint.js and getandcheck-endpoint.php to your htdocs dir
	2) Edit getandcheck-endpoint.php config (connection to database)
	3) Copy getandcheck-flush.php to your home dir
	4) Edit getandcheck-flush.php config
	3) Copy getandcheck-flush-config.php.example getandcheck-flush-config.php to your same dir
	4) Edit getandcheck-flush.php config
	5) Open it with editor and update config section ($getandcheck_config and database properties)
	6) Import getandcheck.sql to characters database
	7) Add getandcheck-flush.php script to crontab
    		*/1 * * * * php /home/trinity/getandcheck/getandcheck-flush.php

Step 3 - client side (mobile devices)

	1) Download Get&Check mobile app from Google Play or AppStore
	2) Join to your community (community key from step 1)
	3) That it! Optionally - try to send push notification from web admin panel to devices.
