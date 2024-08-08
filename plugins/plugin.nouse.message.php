<?php

/* ----------------------------------------------------------------------------------
 * 
 * Xaseco plugin to display a ingame manialink with message text.
 * everything is configureable via the config file, all values in config are initial values.
 * Everything like text, interval, position, size and style can be changed onthefly 
 * via admin chat commands. the chatcommand is made of 3 parts.
 * 
 * /[initial] [param] [value]
 * 
 * Command list:
 * /msgmod... for the initial command, have to be in front.
 * ....text "text"  will add a new message to the existing ones
 * ....text(1,2,3....) to display the particular message 
 * ....text(1,2,3....) 'text' to edit the particular message
 * ....text(1,2,3....) '""' to delete the particular message 
 * ....textcount to display the amount of messages stored
 * ....interval "seconds" for the interval, 0 for static text.
 * ....random "0/1" for ordered or random messages
 * ....toggle "0/1" to enable/disable the toggle function (F7) show/hide
 * ....mpos "x y" for the main position (n.nn, n = numbers)
 * ....msize "x y" for the window size of window (n.nn, n = numbers)
 * ....tsize "n" (1 - 10) 
 * ....style "Mania Style" for the window style
 * ....substyle "Mania substyle" for the substyle
 *
 * find styles at http://fish.stabb.de/styles/
 *
 * if no param given, a little help text will be displayed.
 *
 * all new values given by chat commands are saved in config. 
 *
 * ----------------------------------------------------------------------------------
 *
 * Author: 			nouseforname @ http://www.tm-forum.com
 * Home: 			http://nouseforname.de
 * Date: 			12.05.2012
 * Version:			2.0.1
 * Dependencies: 	none
 *
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
 */

class NouseMessage { 

	private $msginfo, $msgstyle, $msgsubstyle, $toggle;
	private $msgmainposition, $msgmsize, $msgtextsize, $msginterval;
	private $xml_message_on, $xml_message_off, $msgreset, $random, $count=0;
	var $msgactive_players = array();
	var $msginfotext = array();
	
	// startup 
	function startUp($aseco){
		$this->Aseco = $aseco;
	}
	
    function upToDate($aseco) {
        $aseco->plugin_versions[] = array(
            'plugin'	=> 'plugin.nouse.message.php',
            'author'	=> 'Nouseforname',
            'version'	=> '2.0.1'
        );
    }
    
	// load xml configs
	function message_loadSettings(){

		$file = file_get_contents('nouse_message_config.xml');
		$xml = @simplexml_load_string($file);
		
		$this->msginfotext = array();
		foreach ($xml->infotext as $info) {
			if ($info != '') $this->msginfotext[] = strval($info);
		}
		
		$this->msginfo = $this->msginfotext[0];
		$this->msginterval = intval($xml->msginterval);
		$this->random = intval($xml->random);
		$this->toggle = intval($xml->toggle);
		$this->msgmainposition = strval($xml->mainposition);
		$this->msgmsize = strval($xml->size);
		$this->msgtextsize = strval($xml->textsize);
		$this->msgstyle = strval($xml->style);
		$this->msgsubstyle = strval($xml->substyle);
	}

	// save new settings in config
	function message_savesettings() {
		
		$file = 'nouse_message_config.xml';
		
		$config = '<?xml version="1.0" encoding="utf-8" ?>' . CRLF;
		$config .= '<settings>' . CRLF;
		$config .= CRLF;
		$config .= "\t<!-- config file for plugin.nouse.info.php -->" . CRLF;
		$config .= "\t<!-- all values here are initial values and can be changed via admin command -->" . CRLF;
		
		foreach ($this->msginfotext as $info) {
			if ($info != '') $config .= "\t<infotext>".$info."</infotext>" . CRLF;
		}
			
		$config .= CRLF;
		$config .= "\t<msginterval>".$this->msginterval."</msginterval> <!-- interval in seconds, set to \"0\" for static text -->" . CRLF;
		$config .= "\t<random>".$this->random."</random> <!-- 1 = random , 0 = order (1..2..3..etc -->" . CRLF;
		$config .= "\t<toggle>".$this->toggle."</toggle> <!-- 0 = no Hide function (F7), 1 = show/hide with F7 -->" . CRLF;
		$config .= CRLF;
		$config .= "\t<mainposition>".$this->msgmainposition."</mainposition>" . CRLF;
		$config .= "\t<size>".$this->msgmsize."</size>" . CRLF;
		$config .= "\t<textsize>".$this->msgtextsize."</textsize>" . CRLF;
		$config .= CRLF;
		$config .= "\t<style>".$this->msgstyle."</style>" . CRLF;
		$config .= "\t<substyle>".$this->msgsubstyle."</substyle>" . CRLF;
		$config .= CRLF;
		$config .= '</settings>';
		
		//** write out XML file **//
		if (!@file_put_contents($file, $config)) {
			trigger_error('Could not write info config file ' . $file . ' !', E_USER_WARNING);
		}
	}

	function msg_random_message() {
		if ($this->msginterval) {
			$this->msgreset++;
			if ($this->msgreset == $this->msginterval) {
				if ($this->random) {
					$this->msginfo = $this->msginfotext[rand(0,count($this->msginfotext)-1)];
				}
				else {
					$this->msginfo = $this->msginfotext[$this->count];
					$this->count++;
					if ($this->count == count($this->msginfotext)) $this->count = 0;
				}
				$this->init_message_manialinks();
				$this->message_on();
			}
		}
	}

	// admin chat commands to  mod message window
	function chat_msgmod_($aseco, $command) {
		$admin = $command['author'];
		$login = $admin->login;
		$nick = $admin->nickname;
		//$command['params'] = strtolower($command['params']);
		$com = explode(' ', $command['params'], 2);
		
		// check if chat command was allowed for a masteradmin/admin/operator
		if ($aseco->isMasterAdmin($admin) || $aseco->isAdmin($admin)) {
			// check for unlocked password (or unlock command)
			if ($aseco->settings['lock_password'] == '' || $admin->unlocked) {
				if ($com[0]) {
					// check if chat command 'text'
					if (strpos($com[0], 'text') !== false) {
						$text = explode('text', $com[0]);
						// only text with no number, to add a message
						if ($text[1] == '' && $com[1]) {
							$this->msginfotext[] = $com[1];
							$this->msginfo = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						}
						// edit or show message no 'n'
						elseif ($text[1]) {
							$text[1]--;
							// if new message given edit old to new
							if ($com[1]) {
								if ($com[1] == '""') $com[1] = '';
								$this->msginfotext[$text[1]] = $com[1];
								$this->msginfo = $com[1];
								$this->init_message_manialinks();
								$this->message_savesettings();
								$this->message_loadSettings();
							}
							// display given message no 'n'
							else {
								$this->msginfo = $this->msginfotext[$text[1]];
								$this->init_message_manialinks();
							}
						}
					}
					
					switch ($com[0]) {
						case 'interval':
							$this->msginterval = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'random':
							$this->random = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'toggle':
							$this->toggle = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'mpos':
							$this->msgmainposition = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'msize':
							$this->msgmsize = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'tsize':
							$this->msgtextsize = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'style':
							$this->msgstyle = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'substyle':
							$this->msgsubstyle = $com[1];
							$this->init_message_manialinks();
							$this->message_savesettings();
						break;
						case 'textcount':
							$message = '$ff0>> $f80$oThere are '.count($this->msginfotext).' messages stored!';
							$this->Aseco->client->query('ChatSendServerMessageToLogin', $this->Aseco->formatColors($message), $login);
						break;
					}
					$this->message_on();
				} else {
					$message = '{#server}> {#error}Missing parameter. Usage like $fff"/msgmod param value" $f80param: $i$ff0"msgtext(n)=text - msginterval=sec - msgpos=x y - msgmsize=x y - msgtsize=x y - msgstyle=STYLE - msgsubstyle=SUBSTYLE {#error}!';
					$this->Aseco->client->query('ChatSendToLogin', $this->Aseco->formatColors($message), $login);
				}
			} else {
				// write warning in console
				$this->Aseco->console($login . ' tried to use admin chat command (not unlocked!): "/msgmod"');
				// show chat message
				$this->Aseco->client->query('ChatSendToLogin', $this->Aseco->formatColors('{#error}You don\'t have the required admin rights to do that, unlock first!'), $login);
			}
		} else {
			// write warning in console
			$this->Aseco->console($login . ' tried to use admin chat command (no permission!): "/msgmod" ');
			// show chat message
			$this->Aseco->client->query('ChatSendToLogin', $this->Aseco->formatColors('{#error}You don\'t have the required admin rights to do that!'), $login);
		}
	}

	function init_message_manialinks() {

		$this->xml_message_on = '<manialink id="0815471122122">
			<frame posn="'.$this->msgmainposition.'">
				<format style="TextPlayerCardName" />
				<quad posn="0 0 0" sizen="'.$this->msgmsize.'"  halign="center" valign="center" style="'.$this->msgstyle.'" substyle="'.$this->msgsubstyle.'" action="382009003" actionkey="3" />
				<label posn="0 0.2 1" sizen="'.$this->msgmsize.'" halign="center" valign="center" textsize="'.$this->msgtextsize.'" text="'.$this->msginfo.'" action="382009003" actionkey="3" />
			</frame>
		</manialink>';

		$this->xml_message_off = '<manialink id="0815471122122">
		<frame posn="0 0 0">
		<quad posn="0 0 0" sizen="0 0" halign="center" valign="center" action="382009003" actionkey="3" /> 
		</frame>
		</manialink>';
	}

	// display manialink
	function message_on() {
		$this->msgreset = 0;
		$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array(implode(',', $this->msgactive_players), $this->xml_message_on, 0, false));
		//$this->Aseco->client->addCall('SendDisplayManialinkPage', array($this->xml_message_on, 0, false));
	}  

	// switch off manialink at roundsend
	function message_off() {
		$this->msgreset = -900;
		$this->Aseco->client->addCall('SendDisplayManialinkPage', array($this->xml_message_off, 0, false));
	} 

	// put playerlogins into array at player connect, and display manialink
	function message_deal_with_players($aseco, $player) {
		$this->msgactive_players[] = $player->login;
		$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player->login, $this->xml_message_on, 0, false));
	}
	 
	// remove leaving players from array
	function msg_remove_player($aseco, $player) {
		$login = $player->login;
		if (in_array($login, $this->msgactive_players)) {
				$key = array_search($login, $this->msgactive_players);
				unset($this->msgactive_players[$key]);
				sort($this->msgactive_players);
		}
	}
	 
	// F7 key press action id for widgets 382009003
	// button click from menu "toggle widgets" 3831330
	function message_handle_buttonclick($aseco, $command) {
		$login = $command[1];
		$action = $command[2];
		if ( $action == 382009003 || $action == 3831330 ) {
			if ($this->toggle) $this->message_change_player_status($aseco, $login);
		}
	}

	// change display status to specifig login
	function message_change_player_status($aseco, $login) {
		if (in_array($login, $this->msgactive_players)) {
				$key = array_search($login, $this->msgactive_players);
				unset($this->msgactive_players[$key]);
				sort($this->msgactive_players);
				$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array( $login, $this->xml_message_off, 0, false));
			}
			else {
				$this->msgactive_players[] = $login;
				$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array($login, $this->xml_message_on, 0, false));
			}
	}

	// get chat command /togglewidgets from any player
	function message_toggle_command($aseco, $command) {
		$playerid = $command[0];
		$login = $command[1];
		$action = $command[2];
		$state = $command[3];
		if ($playerid != 0 && $action == '/togglewidgets' && $state == 1) {
			if ($this->toggle) $this->message_change_player_status($aseco, $login);
		}
	}

}

global $NouseMessage;
$NouseMessage = new NouseMessage(false);

Aseco::registerEvent('onStartup', array($NouseMessage, 'startUp'));
Aseco::registerEvent('onSync', array($NouseMessage, 'init_message_manialinks'));
Aseco::registerEvent('onSync', array($NouseMessage, 'upToDate'));
Aseco::registerEvent('onStartup', array($NouseMessage, 'message_loadSettings'));
Aseco::registerEvent('onNewChallenge', array($NouseMessage,'message_on'));
Aseco::registerEvent('onEndRace', array($NouseMessage,'message_off'));
Aseco::registerEvent('onEverySecond', array($NouseMessage, 'msg_random_message'));
Aseco::registerEvent('onPlayerConnect', array($NouseMessage, 'message_deal_with_players'));
Aseco::registerEvent('onChat', array($NouseMessage,'message_toggle_command'));
Aseco::registerEvent('onPlayerDisconnect', array($NouseMessage,'msg_remove_player'));
Aseco::registerEvent('onPlayerManialinkPageAnswer', array($NouseMessage,'message_handle_buttonclick'));

Aseco::addChatCommand('msgmod', 'Change Message window apperance.');

function chat_msgmod($aseco,$command) {
	global $NouseMessage;
	$NouseMessage->chat_msgmod_($aseco, $command);
}

?>