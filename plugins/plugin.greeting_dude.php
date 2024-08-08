<?php

/*
 * Plugin: Greeting Dude
 * ~~~~~~~~~~~~~~~~~~~~~
 * For a detailed description and documentation, please refer to:
 * http://labs.undef.de/XAseco1+2/Greeting-Dude.php
 *
 * ----------------------------------------------------------------------------------
 * Author:		undef.de
 * Version:		0.9.2
 * Date:		2012-03-21
 * Copyright:		2012 by undef.de
 * System:		XAseco/1.14+ or XAseco2/1.00+
 * Game:		Trackmania Forever (TMF) or Trackmania2 (ManiaPlanet)
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies:
 *  - none
 */

Aseco::registerEvent('onSync',			'grdu_onSync');
Aseco::registerEvent('onPlayerConnect2',	'grdu_onPlayerConnect2');

Aseco::addChatCommand('dudereload',		'Reload the "Greeting Dude" settings.', true);

global $grdu_config;

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function grdu_onSync ($aseco) {
	global $grdu_config;


	$xaseco1_min_version = '1.14';
	$xaseco2_min_version = '1.00';
	if (defined('XASECO_VERSION') && version_compare(XASECO_VERSION, $xaseco1_min_version, '<') ) {
		trigger_error('[plugin.greeting_dude.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco1_min_version .'!', E_USER_ERROR);
	}
	else if (defined('XASECO2_VERSION') && version_compare(XASECO2_VERSION, $xaseco2_min_version, '<') ) {
		trigger_error('[plugin.greeting_dude.php] Not supported XAseco2 version ('. XASECO2_VERSION .')! Please update to min. version '. $xaseco2_min_version .'!', E_USER_ERROR);
	}
	else if ( !defined('XASECO_VERSION') && !defined('XASECO2_VERSION') ) {
		trigger_error('[plugin.greeting_dude.php] Can not identify the System, "XASECO_VERSION" or "XASECO2_VERSION" is unset! This plugin runs only with XAseco/'. $xaseco1_min_version .'+ or XAseco2/'. $xaseco2_min_version .'+', E_USER_ERROR);
	}


	// Read Configuration
	if (!$grdu_config = $aseco->xml_parser->parseXML('greeting_dude.xml', true, true)) {
		trigger_error('[plugin.greeting_dude.php] Could not read/parse config file "greeting_dude.xml"!', E_USER_ERROR);
	}
	$grdu_config = $grdu_config['GREETING_DUDE'];


	// Transform 'TRUE' or 'FALSE' from string to boolean
	$grdu_config['ONLY_PERSONAL_GREETINGS'][0]	= ((strtoupper($grdu_config['ONLY_PERSONAL_GREETINGS'][0]) == 'TRUE')	? true : false);
	$grdu_config['PUBLIC_GREETINGS'][0]		= ((strtoupper($grdu_config['PUBLIC_GREETINGS'][0]) == 'TRUE')		? true : false);


	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'plugin.greeting_dude.php',
		'author'	=> 'undef.de',
		'version'	=> '0.9.2'
	);


	// Build the array of personal greetings for special Players
	$grdu_config['PersonalMessages'] = array();
	foreach ($grdu_config['PLAYERS'][0]['PLAYER'] as &$item) {
		foreach (explode('|', $item['LOGIN'][0]) as $login) {
			$grdu_config['PersonalMessages'][$login] = $item['GREETING'][0];
		}
	}
	unset($item);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function grdu_onPlayerConnect2 ($aseco, $player) {
	global $grdu_config;


	$message = false;
	$nickname = '{#highlite}'. grdu_handleSpecialChars($player->nickname) .'$Z'. $grdu_config['TEXT_FORMATTING'][0];
	if ( isset($grdu_config['PersonalMessages'][$player->login]) ) {
		// Setup the personal greeting
		$message = $grdu_config['GREETER_NAME'][0] .'$Z '. $grdu_config['TEXT_FORMATTING'][0] . $grdu_config['PersonalMessages'][$player->login];
		$message = str_replace('{nickname}', $nickname, $message);
	}
	else if ($grdu_config['ONLY_PERSONAL_GREETINGS'][0] == false) {
		// Setup the global greeting
		$message = $grdu_config['GREETER_NAME'][0] .'$Z '. $grdu_config['TEXT_FORMATTING'][0] . $grdu_config['MESSAGES'][0]['GREETING'][rand(0,count($grdu_config['MESSAGES'][0]['GREETING'])-1)];
		$message = str_replace('{nickname}', $nickname, $message);
	}
	if ($message != false) {
		if ($grdu_config['PUBLIC_GREETINGS'][0] == true) {
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
		}
		else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		}
	}
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function chat_dudereload ($aseco, $command) {


	// Bailout if Player is not an MasterAdmin/Admin
	if ( (!$aseco->isMasterAdmin($command['author'])) && (!$aseco->isAdmin($command['author'])) ) {
		return;
	}

	// Reload the "greeting_dude.xml"
	grdu_onSync($aseco);

	$message = '{#admin}>> Reload of the configuration "greeting_dude.xml" done.';
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
}

/*
#///////////////////////////////////////////////////////////////////////#
#									#
#///////////////////////////////////////////////////////////////////////#
*/

function grdu_handleSpecialChars ($string) {

	// Remove links, e.g. "$(L|H|P)[...]...$(L|H|P)"
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)\$(L|H|P)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)/i', '$2', $string);
	$string = preg_replace('/\${1}(L|H|P)(.*?)/i', '$2', $string);

	// Remove $S (shadow)
	// Remove $H (manialink)
	// Remove $W (wide)
	// Remove $I (italic)
	// Remove $L (link)
	// Remove $O (bold)
	// Remove $N (narrow)
	$string = preg_replace('/\${1}[SHWILON]/i', '', $string);

	$string = stripNewlines($string);	// stripNewlines() from basic.inc.php

	return validateUTF8String($string);	// validateUTF8String() from basic.inc.php
}

?>
