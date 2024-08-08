<?php
/**
 * Finish-Payout-plugin for XASECO
 * v1.0
 * huge parts of the code are stolen from lotto original code by: Mistral
 * and IgnoredPlayers mod and some tweaks by: Dr. Kaputnik
 * modified by Naturtalent with very dirty code :P

 !!! Plugin must be inserted as very first plugin in front of localdatabase and rasp, 
 otherwise the check for first finish on map won't work as intended, 
 because the time is already inserted into the database !!!

 not tested for stunt mode, but should work
 
 - plugin pays coppers for finishing a map the first time
 - plugin pays coppers for beating the goldtime
 if you want it to be authortime instead of goldtime do a replacement search
 
 there is a hardlimit of maximum 500 copper payment included to prevent config mistakes, 
 if you want more search for it
 */

 // enable finish payout or deactivate it
global $enablethefinishpayout;
$enablethefinishpayout = 1; // 1 activates it, anything else deactivates it, useless but faster than editing plugins.xml :P

// start finish payout
/******************************************************
******************************************************/
if ($enablethefinishpayout == 1) 
{

Aseco::registerEvent('onStartup', 'finishpayout');
Aseco::registerEvent('onPlayerFinish', 'CheckFinishPayment');

global $finish_settings;

// Initializes the plugin
function finishpayout($aseco)
{
	global $finish_settings;

	// Load settings

	$finish_settings = array();
	$count = 0;
	$settingsXML = simplexml_load_file('finish_config.xml');

	$finish_settings["ServerName"] = strval($settingsXML->servername); //name will be send in win message
	$finish_settings["MaxWin"] = intval($settingsXML->maxwin); // used for beating the gold time
	$finish_settings["MinWin"] =  intval($settingsXML->minwin); // used for just finishing a map payout
	$finish_settings["Log"] =  intval($settingsXML->log);
	$finish_settings["PayFirstFinish"] =  intval($settingsXML->payfirstfinish);
	foreach ($settingsXML->ignoredplayers->ignoredlogin as $login)
		{
		$finish_settings["IgnoredPlayers"][$count] = $login;
		$count++;
		}
	
	if ($finish_settings["MaxWin"] < 0) $finish_settings["MaxWin"] = 0;
	if ($finish_settings["MinWin"] < 0) $finish_settings["MinWin"] = 0;
	if ($finish_settings["ServerName"] == "")  $finish_settings["ServerName"] = "a nice server";
	if ($finish_settings["Log"] != 1) $finish_settings["Log"] = 0;
	if ($finish_settings["PayFirstFinish"] != 1) $finish_settings["PayFirstFinish"] = 0;

	$aseco->console("## ## ##  FinishPayout initialized! Ignored players: $count  ## ## ##");
}


// check finish times for gold and first finish
function CheckFinishPayment($aseco, $finish_item)
{
	global $finish_settings;
	if ($finish_item->score > 0)
		{
			global $maxrecs;
			$copperfinishwin = 0;
			$foundatime = false;
			$besttime = 0;
			$loginofcopperhunter = $finish_item->player->login;

			// find ranked record without sql
			for ($i = 0; $i < $maxrecs; $i++) {
				if (($rec = $aseco->server->records->getRecord($i)) !== false) {
					if ($rec->player->login == $loginofcopperhunter) {
					$besttime = $rec->score;
					$foundatime = true;
					break;
					}
				} else {
					break;
					}
			}
			// if no record was found search in the database for unranked times
			if (!$foundatime) {
				$playerindatabase = $finish_item->player->login;
				$trackuid = $finish_item->challenge->uid;
				// get the player id from the database
				$query3 = "SELECT Id FROM players WHERE Login='$playerindatabase'";
				$res3 = mysql_query($query3);
				if ($res3)
				{
				$row3 = mysql_fetch_array($res3);
				$pid = $row3['Id'];
				}
				// now get the challenge id
				mysql_free_result($res3);
				$query = "SELECT Id FROM challenges WHERE Uid='$trackuid'" ;
			    $res = mysql_query($query);
			    if ( $res ){
			    $row = mysql_fetch_array($res);
			    $track = $row['Id'];
				$order = ($aseco->server->gameinfo->mode == 4 ? 'DESC' : 'ASC'); //stunt mode or not
				// now find the best time/score for that player
				$query2 = "SELECT score FROM rs_times WHERE playerID= '$pid'  && challengeID= '$track' ORDER BY score  $order LIMIT 1";
				$res2 = mysql_query($query2);
					// if there is a time, get it
					if (mysql_num_rows($res2) > 0) {
					$row2 = mysql_fetch_array($res2);
					$besttime = $row2['score'];
					$foundatime = true;
					}
				mysql_free_result($res2);
				}
				mysql_free_result($res);
				}
			// check if gold is beaten
			if ($finish_item->score <= $aseco->server->challenge->goldtime)
				{   
					// check if gold was not beaten before
					if ($besttime > $aseco->server->challenge->goldtime || $besttime == 0)
					{
					//copperwin of maxwin
						// but first check if player is excluded from winning
						if (in_array ($loginofcopperhunter, $finish_settings["IgnoredPlayers"]))
						{
						$nickname = $finish_item->player->nickname;
						$aseco->addCall("ChatSendServerMessage", array("\$FF0>> $nickname \$z\$0F0\$sbeats the gold time for the first time, but he's too rich to get coppers \$F04:p"));
						}
						// if player is allowed to win let him win
						else
						{
						$copperfinishwin = $copperfinishwin + $finish_settings["MaxWin"];
						$nickname = $finish_item->player->nickname;
							// there was no time in the database
							if (!$foundatime)
							{
							$aseco->addCall("ChatSendServerMessage", array("\$FF0>> $nickname \$z\$0F0\$sbeats the gold time with the first finish"));
							}
							// there was already a time in the database but worse than gold
							else
							{
							$aseco->addCall("ChatSendServerMessage", array("\$FF0>> $nickname \$z\$0F0\$s finally beats the gold time. Good job."));
							}
						}
					}

				}
			// if gold was not beaten and there was no time found in the database	
			if (!$foundatime)
				{
				// ignored players get nothing
				if (in_array ($loginofcopperhunter, $finish_settings["IgnoredPlayers"]))
						{}
				// others win minwin in that case
				else
				{
				// check if payment for just finishing is enabled
					if ($finish_settings["PayFirstFinish"] == 1){
				// first finish win, it adds up to the previous win if gold was beaten with the first finish 
						$copperfinishwin = $copperfinishwin + $finish_settings["MinWin"];
						if ($copperfinishwin == $finish_settings["MinWin"]){
						// message if finish time was less than gold
						$message = "You win ".$finish_settings["MinWin"]." coppers for finishing the track the first time";
						$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $loginofcopperhunter);
						}
					}
				}
				}

		//time to pay if the player has TMUF
			if (($copperfinishwin > 0) && ($finish_item->player->rights))
			{
			global $finish_settings;

			$aseco->client->query("GetServerCoppers");
			$coppers = $aseco->client->getResponse();

			$win = $copperfinishwin;
			if ($win > 500) $win = 500; // hardlimit to prevent too much payment 
			if ($win < 5) $win = 5;     // set a minimum win
			$tax = intval($win/20)+3;   // add nadeo tax for coppers transactions

			$payout = false;

			// always pay if there are enough coppers
			if ($coppers > ($win+$tax))
			{
				$payout = true;
			}

			// else - no payment
			else
			{
			// message for gold, but no coppers
			$message = "\$ff0>> \$0F0Fantastic! But sorry there are no coppers left on the server.";
			}

			if (!$payout)
			{
				if ($copperfinishwin == $finish_settings["MinWin"]){
				// message for just finishing, but no coppers
				$message = "Nice one, but there are no coppers left on the server :(";
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $loginofcopperhunter);
				}
				else
				{
				$aseco->addCall("ChatSendServerMessage", array($message));
				return;
				}
			}

			if ($aseco->server->getGame() == 'TMF') {
			// check for TMUF server
			if ($aseco->server->rights) {
				$win=intval($win/5)*5;
				settype($win,'integer');
				$aseco->client->query('Pay', $finish_item->player->login, $win, "You won coppers on ".$finish_settings['ServerName']."\$z for your amazing driving skills!");

				if ($win > $finish_settings["MinWin"]){
				$aseco->addCall("ChatSendServerMessage", array("\$ff0>> \$FFF$nickname\$z\$0F0\$s wins \$fff$win \$0f0coppers for superb driving :)"));
				
					if ($finish_settings["Log"] == 1) $aseco->console("Finishpayout: $loginofcopperhunter wins $win coppers for goldtime");
				}
				else
				{
				$aseco->client->query('ChatSendServerMessageToLogin', "\$ff0>> \$FFFYou\$z\$0F0\$s win \$fff$win \$0f0coppers for good driving :)", $player->login);
					if ($finish_settings["Log"] == 1) $aseco->console("Finishpayout: $loginofcopperhunter wins $win coppers for finishing");
				}

			}
		}
	}
}
}
} // end enable the player finish payout
?>
