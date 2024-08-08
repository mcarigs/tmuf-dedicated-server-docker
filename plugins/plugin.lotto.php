<?php
/**
 * Lottery-plugin for XASECO
 * v1.0
 * original code by: Mistral
 * IgnoredPlayers mod and some tweaks by: Dr. Kaputnik
 */

Aseco::registerEvent('onStartup', 'initlotto');   
Aseco::registerEvent('onEndRace', 'BillNewChallenge');
Aseco::registerEvent('onPlayerFinish', 'AddFinish');

global $finishlist, $lotto_settings;

$finishlist = array();


// Initializes the plugin
function initlotto($aseco)
{
	global $lotto_settings;

	// Load settings

	$lotto_settings = array();
	$count = 0;
	$settingsXML = simplexml_load_file('lotto_config.xml');

	$lotto_settings["ServerName"] = strval($settingsXML->servername);
	$lotto_settings["WinPercentage"] = intval($settingsXML->winpercentage);
	$lotto_settings["MaxWin"] = intval($settingsXML->maxwin);
	$lotto_settings["MinWin"] = intval($settingsXML->minwin);
	$lotto_settings["PayAlways"] = intval($settingsXML->payalways);
	$lotto_settings["Pay50pc"] = intval($settingsXML->pay50chance);
	$lotto_settings["FinishMinPlayers"] = intval($settingsXML->finishminplayers);
	$lotto_settings["OnlineMinPlayers"] = intval($settingsXML->onlineminplayers);
	foreach ($settingsXML->ignoredplayers->ignoredlogin as $login)
		{
		$lotto_settings["IgnoredPlayers"][$count] = $login;
		$count++;
		}
	$lotto_settings["IgnoredRetries"] = intval($settingsXML->ignoredretries);
	
	if ($lotto_settings["MaxWin"] < 0) $lotto_settings["MaxWin"] = 0;
	if ($lotto_settings["MinWin"] < 0) $lotto_settings["MinWin"] = 0;
	if ($lotto_settings["MinWin"] > $lotto_settings["MaxWin"]) $lotto_settings["MinWin"] = $lotto_settings["MaxWin"];
	if ($lotto_settings["PayAlways"] < 0) $lotto_settings["PayAlways"] = 0;
	if ($lotto_settings["Pay50pc"] < 0) $lotto_settings["Pay50pc"] = 0;
	if ($lotto_settings["PayAlways"] < $lotto_settings["Pay50pc"]) $lotto_settings["Pay50pc"] = $lotto_settings["PayAlways"];
	if ($lotto_settings["IgnoredRetries"] > 10) $lotto_settings["IgnoredRetries"] = 10;

	$aseco->console("## ## ##  Lottery initialized! Ignored players: $count  ## ## ##");
}


// just add player to list - if player finishes more than once he has higher chances (often in the list)
function AddFinish($aseco, $finish_item)
{
	global $finishlist;
 	
	if ($finish_item->score > 0)
		$finishlist[] = $finish_item->player;	
}


function BillNewChallenge($aseco, $challenge)
{
	global $lotto_settings, $finishlist;
 	
	$pcount = count($finishlist);
	$ocount = count($aseco->server->players->player_list);
	$preq = $lotto_settings["FinishMinPlayers"];
	$oreq = $lotto_settings["OnlineMinPlayers"];

	if ($pcount < 1) return;  // no finishers - no lottery^^
	
	if ($pcount < $preq || $ocount < $oreq)
		{
		$pdiff = $preq - $pcount;
		if ($pdiff < 0) $pdiff = 0;
		$odiff = $oreq - $ocount;
		if ($odiff < 0) $odiff = 0;
		
		$aseco->addCall("ChatSendServerMessage", array("\$ff0>> \$0F0No Lottery: \$FFF$pdiff\$z\$0F0\$s more \$0f0finishes and/or \$FFF$odiff\$z\$0F0\$s more \$0f0players required!")); 
			
		//reset list 
		$dummy = array();
		$finishlist = $dummy;
		return;
		}
	
	// get a random player from the finishlist but do not pay ignored players (admins^^)
	$retries = -1;
	do  
		{
		$player = $finishlist[array_rand($finishlist)];
		$playerlogin = $player->login;
		$retries++;
		}
	while (in_array ($playerlogin, $lotto_settings["IgnoredPlayers"]) && ($retries < $lotto_settings["IgnoredRetries"]));
	
	if (in_array ($playerlogin, $lotto_settings["IgnoredPlayers"]))
		{
		$nickname = $player->nickname;
		$aseco->addCall("ChatSendServerMessage", array("\$FF0>> $nickname \$z\$0F0\$swould have won the lottery, but he's too rich \$F04:p"));
		}
	else
		{
		payLottery($aseco, $player, $player->nickname);
		}
		
	// reset list after lottery
	$dummy = array();
	$finishlist = $dummy;
}


function payLottery($aseco, $player, $nickname)
{
	global $lotto_settings, $finishlist;

	$aseco->client->query("GetServerCoppers");
	$coppers = $aseco->client->getResponse();

	$win = $coppers*($lotto_settings["WinPercentage"]/100);
	if ($win > $lotto_settings["MaxWin"])
		$win = $lotto_settings["MaxWin"];
	if ($win < $lotto_settings["MinWin"])
		$win = $lotto_settings["MinWin"];
	if ($win < 5) $win = 5;	
	$tax = intval($win/20)+3;     // nadeo tax for coppers transactions

	$lottery = false;
	$low = "";
	
	// always pay
	if (($coppers > $lotto_settings["PayAlways"]) && ($coppers > ($win+$tax)))
	{
		$lottery = true;
	}
	// 50% chance to pay
	elseif (($coppers > $lotto_settings["Pay50pc"]) && ($coppers > ($win+$tax)))
	{
		$low = " (Coppers getting low)";
		$lucky = rand(1,2);
		if ($lucky==1)
		{
			$lottery=true;
		}
		else
		{
			$message = "\$ff0>> \$0F0Lottery: no lottery this time.$low";			
		}
	}
	// if enough available - 25% chance to pay
	elseif ($coppers > ($win+$tax))
	{
		$low = " (Server out of coppers soon)";
		$lucky = rand(1,4);
		if ($lucky==1)
		{
			$lottery=true;
		}
		else
		{
			$message = "\$ff0>> \$0F0Lottery: no lottery this time.$low";			
		}
	}
	// else - no lottery
	else
	{
		$message = "\$ff0>> \$0F0Lottery: no lottery. (Server out of coppers)";
	}

	if (!$lottery)
		{
		$aseco->addCall("ChatSendServerMessage", array($message));
		return;
		}

	if ($aseco->server->getGame() == 'TMF') {
		// check for TMUF server
		if ($aseco->server->rights) {
			// check for TMUF player
			if ($player->rights) {
				$pcount=count($finishlist);
				$win=intval($win/5)*5;
				settype($win,'integer');
				$aseco->client->query('Pay', $player->login, $win, "You won in the ".$lotto_settings['ServerName']."\$z lottery!");
				$aseco->addCall("ChatSendServerMessage", array("\$ff0>> \$0F0Lottery: \$FFF$nickname\$z\$0F0\$s won \$fff$win \$0f0coppers (\$fff$pcount \$0F0finishes)"));
			} else {
				$message = $aseco->getChatMessage('FOREVER_ONLY');
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
			}
		}
	}
}
?>