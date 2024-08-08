<?php

/* ----------------------------------------------------------------------------------
 *
 * Xaseco plugin to serve betting function. all bet participants are fighting for the
 * stake made by all of them. so it's all about beeing in front of others again^^.
 * there are several manialink buttons to serve the chatcommands.
 * this plugin is tested only in TA mode, but should also work in Rounds & Stunt.
 *
 * -----------------------------------------------------------------------------------
 *
 * Chatcommands:
 * /bet "n" 		- to bet any amount of coppers above the "minbet" limit (n = coppers)
 * /accept 			- to accept the started bet with given stake
 * /betstake		- to view a list of players and theire bet stakes
 * /betwin			- to view a list of players and theire bet wins (coppers and count)
 * /bettotalstake	- to view list of all participants
 *
 *	MasterAdmin and Admin only:
 * /betstate ON/OFF	- to enable/disable betting from next new challenge
 *
 * -----------------------------------------------------------------------------------
 *
 * Function:
 * At new challenge server coppers are checked, if below "minservercoppers" betting
 * will be disabled. It will also be disabled by admin command. With "betenabled" you
 * decide if betting is enabled/disabled  with xaseco startup.
 *
 * If nobody started bet during "timelimitbet" seconds, betting will be not allowed for
 * running round.
 * If bet started but nobody accepted in "timelimitbet" seconds, bet starter get back
 * their stake. Nadeo tax will be deducted.
 * If bet started and accepted by players but nobody won, stake can be refund or not,
 * depending on "paybacknowin" option. Nadeo tax will be deducted before.
 * Bet participants must have finished the track to be able to win.
 * There are two options ("winneronly") for winning conditions. one is Winner only
 * can win the bet, second is, bet winner need to be in front of other bet participants.
 * If there is a bet winner he/she will get the stake, in this case nadeo tax is splitted.
 * half is payed by  server, half is payed by winner.
 * This system will avoid the server losing too much coppers.
 * The ingame message with coppers and winmessage could take a while, the time is not
 * influenced by the plugin.
 *
 * There are 4 different manialink windows:
 * - bet panel, serving the start panel with 5 buttons to bet different amounts.
 * amount can be configured vie betting_config.xml.
 * - accept panel, serving the accept button and display the stake.
 * - win panel, giving only a message of who win and how much, cause chat message only
 * is not enough at end of race.
 * - state panel, displays the total stake, click it to see a list of all bet participants.
 *
 * all main positions can be adjusted via the config file.
 * if you want to edit the whole apperance, check out the manialink section somewhere below.
 * Same for colors and text of any chatmessage, check out the chat command section at bottom.
 *
 * The plugin will create a new table "betting" in your database, this is necessary to
 * save the betting data like player, nickname, stake, win, wincount.
 *
 * No need to modify any other existing file.
 *
 * ----------------------------------------------------------------------------------
 *
 * Installation:
 * Copy plugin.nouse.betting.php into plugins folder.
 * Copy nouse_betting_config.xml into xaseco root
 * Add "<plugin>plugin.nouse.betting.php</plugin> into plugins.xml
 * Configure to ur needs.
 * Enjoy
 *
 * ----------------------------------------------------------------------------------
 *
 * Author: 			ML aka RookieNouse aka nouseforname @ http://www.tm-forum.com
 * Home: 			http://nouseforname
 * Date: 			10.01.2020
 * Version:			1.8.2
 * Fix by L3cKy:	Database creation failed on startUp
 *					changed TYPE=MyISAM into ENGINE=MyISAM at line 184
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

class betting_coppers {
	var $valid_players = array();
	var $acceptors = array();
	var $ranks = array();
	var $showaccept = array();
	var $bets = array();
	private $Aseco, $index, $servername;
	private $minbet, $maxbet, $enabled, $minservercoppers, $state;
	private $timelimit_bet, $timelimit_accept;
	private $bet_active, $bet_starter, $bet_starter_nick, $bet_start, $bet_amount;
	private $bet1, $bet2, $bet3, $bet4, $bet5;
	private $timebet, $countsec, $player, $clicktime, $clicked;
	private $nickname, $coppers, $paybackstarter, $paybackbetstarter;
	private $checkbet, $checkaccept, $betbill;
	private $paybacknowin, $winneronly;
	private $bet_starter_factor, $bet_nowin_factor, $bet_winpayment_factor;
	private $betpanelmainpos, $acceptpanelmainpos, $bwinpanelmainpos;

	// debug
	function betting_coppers ($debug){
		$this->debug = $debug;
	}

    function upToDate($aseco) {
        $aseco->plugin_versions[] = array(
            'plugin'	=> 'plugin.nouse.betting.php',
            'author'	=> 'Nouseforname',
            'version'	=> '1.8.1'
        );
    }

	// startup
	function startUp($aseco){
		$this->Aseco = $aseco;
		$this->loadSettings();
		$this->bet_mysql_create();
	}

	// load xml configs
	function loadSettings(){
		$file = file_get_contents('nouse_betting_config.xml');
		$xml = simplexml_load_string($file);

		$this->enabled = intval($xml->betenabled);

		$this->minservercoppers = intval($xml->minservercoppers);

		$this->minbet = intval($xml->minbet);
		$this->maxbet = intval($xml->maxbet);

		$this->timelimit_bet = intval($xml->timelimit_bet);
		$this->timelimit_accept = intval($xml->timelimit_accept);

		$this->bet1 = intval($xml->bet1);
		$this->bet2 = intval($xml->bet2);
		$this->bet3 = intval($xml->bet3);
		$this->bet4 = intval($xml->bet4);
		$this->bet5 = intval($xml->bet5);

		$this->winneronly = intval($xml->winneronly);
		$this->paybacknowin = intval($xml->paybacknowin);
		$this->paybackbetstarter = intval($xml->paybackstarter);

		$this->bet_starter_factor = intval($xml->betstarterpayback);
		$this->bet_nowin_factor = intval($xml->betnowinpayback);
		$this->bet_winpayment_factor = intval($xml->betwinpayment);

		$this->betpanelmainpos = strval($xml->bet_panel->mainpos);
		$this->acceptpanelmainpos = strval($xml->accept_panel->mainpos);
		$this->winpanelmainpos = strval($xml->win_panel->mainpos);
		$this->statepanelmainpos = strval($xml->state_panel->mainpos);

		$this->debug = intval($xml->debug);
	}

	// create database at startup
	function bet_mysql_create() {
		$query = "CREATE TABLE IF NOT EXISTS betting (
					ID mediumint(9) NOT NULL auto_increment,
					login varchar(100),
					nickname varchar(100),
					stake mediumint(9),
					wins mediumint(9),
					countwins mediumint(9),
					PRIMARY KEY (ID),
					UNIQUE (login)
			     ) ENGINE=MyISAM";
		mysql_query($query);
	}

	// insert data into database
	function bet_mysql_insert($login, $nickname, $stake, $win, $countwins) {
		$query = 'INSERT INTO betting (`login`, `nickname`, `stake`, `wins`, `countwins`)
		VALUES (\''.$login.'\', \''.$nickname.'\', \''.$stake.'\', \''.$win.'\', \''.$countwins.'\')
		ON DUPLICATE KEY UPDATE
		stake=stake + '.$stake.',
		wins=wins + '.$win.',
		countwins=countwins + '.$countwins.'
		';
		mysql_query($query);
	}

	function reset_bet_counter() {
		$this->countsec = -3;
		if (!$this->index) {
			$this->bet_ml_on();
			$this->index = 1;
		}
	}

	function reset_bet_counter2() {
		$this->countsec = -90;
	}
	// reset all states at begin round
	function reset_bet() {
		$this->bet_active = 0;
		$this->bet_amount = 0;
		$this->bet_start = 0;
		$this->paybackstarter = 0;
		$this->checkaccept = 0;
		$this->bet_starter = NULL;
		$this->bet_starter_nick = NULL;
		$this->bets = array();
		$this->acceptors = array();
		$this->checkbet = 1;
		$this->index = 0;
		$this->ranks = array();
		$this->showaccept = array();
		$this->bet_winner_ml_off();
		$this->bet_nowinner_ml_off();
		$this->accept_ml_off_all();
		$this->bet_state_ml_off();
	if (!$this->enabled) {
		$message = '$ff0> $3c0$i$sBetting is disabled for this round!!!';
		$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
	} elseif (!$this->state) {
		$message = '$ff0> $3c0$i$sDue to low coppers betting is disabled! Please donate some. :))';
		$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
	}
	}

	// count seconds and check if bet/accept still available
	function on_everysecond() {
		$this->countsec++;

		if ($this->countsec == $this->timelimit_bet && $this->checkbet && !$this->bet_active && $this->enabled && $this->state) {
			$this->checkbet = 0;
			$this->bet_ml_off();
			$message = '$ff0> $cf0$iTimelimit exceeded. Nobody started bet! Try it again next round.'; // chat message if nobody started bet
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), implode(',', $this->valid_players)));
		}
		elseif ($this->countsec == $this->timelimit_accept && !$this->acceptors && $this->bet_active && !$this->checkaccept) {
			$this->checkaccept = 1;
			if ($this->paybackbetstarter) {
				$this->payback_bet_starter();
			}
			foreach ($this->valid_players as $login) {
				$this->accept_ml_off($login);
			}
			$message = '$ff0> $9f0$s$iNobody accepted bet from '. $this->bet_starter_nick.' $g$z$9f0$s$i with '. $this->bet_amount.' Coppers!'; // chat message if nobody accepted
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), implode(',', $this->valid_players)));
		}
		elseif ($this->countsec == $this->timelimit_accept && $this->acceptors && $this->bet_active) {
			foreach ($this->valid_players as $login) {
				$this->accept_ml_off($login);
			}
		}

		// reset login of double click block
		if (time() >= $this->clicktime + 5) $this->clicked = NULL;

	}

	// check if player login is TMU account and put into array
	function validate_players() {
		$this->valid_players = array();
		foreach ($this->Aseco->server->players->player_list as $player) {
			if ($player->rights) {
				$this->valid_players[] = $player->login;
			}
		}
	}

	function getServerCoppers() {
		$this->Aseco->client->query('GetServerCoppers');
		$coppers = $this->Aseco->client->getResponse();
		$this->Aseco->client->query('GetServerName');
		$this->servername = $this->Aseco->client->getResponse();
		if ($coppers <= $this->minservercoppers) {
		 $this->state = 0;
		} else {
			$this->state = 1;
		}
	}

	// refund coppers to bet starter if nobody accepted (nadeo tax deducted to avoid server lose coppers
	function payback_bet_starter() {
		$coppers = $this->bet_amount;
		if ($this->bet_starter_factor) {
			for ($i = 1; $i <= $this->bet_starter_factor; $i++) {
				$coppers = $coppers - 2 - $coppers * 0.05;
			}
			$coppers = floor($coppers);
		}
		$message = 'Refund '.$this->bet_amount.' coppers, due to nobody accepted your bet at '.$this->servername.'$g$z! Nadeo tax was deducted.'; // text in mail for refund coppers to bet starter
		$this->Aseco->client->addCall('Pay', array($this->bet_starter, (int)$coppers, $this->Aseco->formatColors($message)));
		$this->paybackstarter = 1;
	}

	// refund coppers if nobody won
	function bet_paybacknowin() {
		$coppers = $this->bet_start;
		if ($this->bet_nowin_factor) {
			for ($i = 1; $i <= $this->bet_nowin_factor; $i++) {
				$coppers = $coppers - 2 - $coppers * 0.05;
			}
			$coppers = floor($coppers);
		}
		$message = 'Refund '.$this->bet_start.' coppers, due to nobody won the stake at '.$this->servername.'$g$z! Nadeo tax was deducted.'; // text in mail for refund coppers to all participants
		foreach ($this->acceptors as $nick => $login) {
			$this->Aseco->client->addCall('Pay', array($login, (int)$coppers, $this->Aseco->formatColors($message)));
		}
	}

	// pay winner, decuct one time tax
	function bet_paywin($winner) {
		$coppers = $this->bet_amount;
		if ($this->bet_winpayment_factor) {
			for ($i = 1; $i <= $this->bet_winpayment_factor; $i++) {
				$coppers = $coppers - 2 - $coppers * 0.05;
			}
			$coppers = floor($coppers);
		}
		$message = 'You won the stake of '.$this->bet_amount.' coppers at '.$this->servername.'$g$z! Nadeo tax was deducted.'; // text in mail to winner
		$this->Aseco->client->addCall('Pay', array($winner[0][0], (int)$coppers, $this->Aseco->formatColors($message)));
	}

	// get winner of bet and pay win or refund all coppers back if no win
	function get_winner() {
		$this->bet_ml_off();
		$this->accept_ml_off_all();
		$this->bet_state_ml_off();
		// reset counter for case of restart and skip
		$this->countsec = -30;
		// do action only if somebody accepted bet
		if ($this->acceptors) {
			// get ranking at rounds end
			$this->Aseco->client->query('GetCurrentRanking', 100, 0);
			$ranking = $this->Aseco->client->getResponse();
			// put all players in array
			foreach ($ranking as $key => $var) {
				$this->ranks[$key] = array($var[Login], $var[BestTime], $var[NickName], $var[Rank], $var[Score]);
			}
			// put bet starter into same array as acceptors
			$this->acceptors[betstarter] = $this->bet_starter;
			$this->bet_starter = NULL;
			$winner = array();
			// check for option winner only and if a participant is rank 1
			if ($this->winneronly) {
				 if ((in_array($this->ranks[0][0], $this->acceptors)) && ($this->ranks[0][1] > 0 || $this->ranks[0][4] > 0)) {
					$winner[] = array($this->ranks[0][0], $this->ranks[0][2]);
				 }
			} else {
				// check if bet participants finished challenge and put them in array
				foreach ($this->ranks as $result) {
					if (($result[1] > 0 || $result[4] > 0) && (in_array($result[0], $this->acceptors))) {
						$winner[] = array($result[0], $result[2]);
					}
				}
			}

			// check who is winner, if no winner pay back coppers or not
			if ($winner) {
				$this->bet_winner_ml_on($winner);
				$this->bet_paywin($winner);
				$message = '$ff0> '. $winner[0][1] .'$g$z$6c0$s$i won the stake with total amount of '.$this->bet_amount.' coppers!'; // chat message winner
				$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
				$this->bet_mysql_insert($winner[0][0], $winner[0][1], $coppers=0, $this->bet_amount, $countwins=1);
			} else {
				$this->bet_nowinner_ml_on();
				$message = '$ff0> $3c0$i$sNobody won the last stake!'; //chat message no winner
				$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
				if ($this->paybacknowin) {
					$this->bet_paybacknowin();
				}
			}
		// payback if no acceptors and next/skip was executed
		} elseif ($this->bet_starter && !$this->paybackstarter) {
			$message = '$ff0> $9f0$s$iNobody accepted bet from '. $this->bet_starter_nick .'$g$z$9f0$s$i with '. $this->bet_amount.' Coppers! .'; // chat message in case of skip
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), implode(',', $this->valid_players)));
			if ($this->paybackbetstarter) {
				$this->payback_bet_starter();
			}
		}
	}

	// get bill and set bet state
	function bet_bill_upd($bill) {
		$billid = $bill[0];

		if (array_key_exists($billid, $this->bets)) {

			// get bill info
			$login = $this->bets[$billid][0];
			$nickname = $this->bets[$billid][1];
			$coppers = $this->bets[$billid][2];
			$checkbet = $this->bets[$billid][3];
			// check bill state
				switch($bill[1]) {
				case 4:  // Payed (Paid)
					if ($coppers > 0) {
						if (!$this->bet_active and $login != $this->bet_starter and ($checkbet === true)) {
							$this->bet_active = 1;
							$this->bet_starter = $login;
							$this->bet_starter_nick = $nickname;
							$this->bet_start = $coppers;
							$this->bet_amount = $coppers;
							$this->bet_ml_off();
							$this->accept_ml_on();
							$message = '$ff0> '. $nickname.'$g$z$6c0$s$i set '.$coppers.' coppers for next bet!'; // chat message if somebody started bet
							$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
							$this->bet_mysql_insert($login, $nickname, $coppers, $win=0, $countwins=0);
							//$this->bet_state_ml_on();
						}
						elseif (!in_array($login, $this->acceptors) and ($login != $this->bet_starter) and ($checkbet === false)) {
							$this->acceptors[$nickname] = $login;
							$this->accept_ml_off($login);
							$count = count($this->acceptors) + 1;
							$this->bet_amount = $count * $this->bet_start;
							$message = '$ff0> '.$nickname.'$g$z$9c0$s$i accepted bet! Total win is '.$this->bet_amount.' coppers now!'; // chat message if somebody accepted bet
							$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
							$this->accept_ml_off($login);
							$this->bet_mysql_insert($login, $nickname, $coppers, $win=0, $countwins=0);
							$this->bet_state_ml_on();
						}
						elseif ($this->bet_active and $checkbet === true) {
							$message = '$ff0> '.$nickname.'$g$z$9c0$s$i with login:'. $login .' tried to cheat or is just to slow to bet!'; // chat message if somebody try to cheat
							$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
							$this->Aseco->console('$ff0> '. $nickname .'$g$z$9c0$s$i with login:'. $login .' tried to cheat or is just to slow to bet!');
						}
					}
					unset($this->bets[$billid]);
					break;
				case 5:  // Refused
					$message = '{#server}> {#error}Transaction refused, no bet placed!';
					$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
					unset($bets[$billid]);
					break;
				case 6:  // Error
					$message = '{#server}> {#error}Transaction failed: {#highlite}$i ' . $bill[2];
					if ($login != '')
						$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
					else
						$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
					unset($this->bets[$billid]);
					break;
				default:  // CreatingTransaction/Issued/ValidatingPay(e)ment
					break;
				}
			}
	} // bet_updated end

	// excute button click as chat command
	function bet_handle_buttons($command){
		$playerid = $command[0];
		$login = $command[1];
		$action = $command[2];

		// only go ahead if not same login or 5 seconds later than first click
		if ($this->clicked != $login) {
			// try to avoid any action for doubleclick
			$this->clicked = $login;
			$this->clicktime = time();

			switch ($action) {
				case 27378501:
					$chat = array();
					$chat[0] = $playerid;
					$chat[1] = $login;
					$chat[2] = '/bet '. $this->bet1 .'';
					$chat[3] = true;
					$this->Aseco->playerChat($chat);
					//$this->clicked = $login;
				break;
				case 27378502:
					$chat = array();
					$chat[0] = $playerid;
					$chat[1] = $login;
					$chat[2] = '/bet '. $this->bet2 .'';
					$chat[3] = true;
					$this->Aseco->playerChat($chat);
					//$this->clicked = $login;
				break;
				case 27378503:
					$chat = array();
					$chat[0] = $playerid;
					$chat[1] = $login;
					$chat[2] = '/bet '. $this->bet3 .'';
					$chat[3] = true;
					$this->Aseco->playerChat($chat);
					//$this->clicked = $login;
				break;
				case 27378504:
					$chat = array();
					$chat[0] = $playerid;
					$chat[1] = $login;
					$chat[2] = '/bet '. $this->bet4 .'';
					$chat[3] = true;
					$this->Aseco->playerChat($chat);
					//$this->clicked = $login;
				break;
				case 27378505:
					$chat = array();
					$chat[0] = $playerid;
					$chat[1] = $login;
					$chat[2] = '/bet '. $this->bet5 .'';
					$chat[3] = true;
					$this->Aseco->playerChat($chat);
					//$this->clicked = $login;
				break;
				case 27378506:
					$chat = array();
					$chat[0] = $playerid;
					$chat[1] = $login;
					$chat[2] = '/accept';
					$chat[3] = true;
					$this->Aseco->playerChat($chat);
					//$this->clicked = $login;
				break;
				case 27378507:
					$chat = array();
					$chat[0] = $playerid;
					$chat[1] = $login;
					$chat[2] = '/bettotalstake';
					$chat[3] = true;
					$this->Aseco->playerChat($chat);
					//$this->clicked = $login;
				break;
				default:
				break;
			}
		}
	} // placebet end

	/************************** MANIALINKS START *********************************/

	// display manialink for betting buttons
	function bet_ml_on() {
		if ($this->enabled && $this->state) {
			$xml = '<manialink id="471108157051">
				<frame posn="'.$this->betpanelmainpos.'">
				<format style="TextCardInfoSmall" textsize="3" />
				<quad sizen="21 5 0" style="Bgs1" substyle="NavButton" halign="center" valign="center"/>
				<label posn="0 1.1 0" sizen="8 2" halign="center" valign="center"  text="$s$o$i$c90Place bet" />
				<label posn="-8 -1 0" sizen="8 2" halign="center" valign="center" text="$i$s$cf0'.$this->bet1.'C" action="27378501"/>
				<label posn="-4 -1 0" sizen="8 2" halign="center" valign="center" text="$i$s$cf0'.$this->bet2.'C" action="27378502"/>
				<label posn="0 -1 0" sizen="8 2" halign="center" valign="center" text="$i$s$cf0'.$this->bet3.'C" action="27378503"/>
				<label posn="4 -1 0" sizen="8 2" halign="center" valign="center" text="$i$s$cf0'.$this->bet4.'C" action="27378504"/>
				<label posn="8 -1 0" sizen="8 2" halign="center" valign="center" text="$i$s$cf0'.$this->bet5.'C" action="27378505"/>
				</frame>
				</manialink>';
				$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array(implode(',', $this->valid_players), $xml, 0, false));
		}
	}  // display_manialink

	// display manialink for betting buttons off
	function bet_ml_off() {
		$xml = '<manialink id="471108157051">
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

	// display manialink for accept button
	function accept_ml_on() {
		foreach ($this->valid_players as $login) {
			if ($login != $this->bet_starter) {
				$this->showaccept[] = $login;
			}
		}
		$xml = '<manialink id="471108157052">
			<frame posn="'.$this->acceptpanelmainpos.'">
			<format style="TextCardInfoSmall" textsize="3" />
			<quad sizen="12 5 0" style="Bgs1" substyle="NavButton" halign="center" valign="center"/>
			<label posn="0 1.1 1" sizen="10 4" halign="center" valign="center"  text="$i$o$s$c90Accept Bet" action="27378506"/>
			<label posn="0 -1 1" sizen="10 4" halign="center" valign="center"  text="$i$o$s$c80Stake '.$this->bet_start.' C" />
			</frame>
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array(implode(',', $this->showaccept), $xml, 0, false));
	}  // display_manialink

	// display manialink for accept button off
	function accept_ml_off($login) {
		$xml = '<manialink id="471108157052">
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPageToLogin', array($login, $xml, 0, false));
	}  // display_manialink

	// display manialink for accept button off to all
	function accept_ml_off_all() {
		$xml = '<manialink id="471108157052">
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

	// display manialink for betting winner
	function bet_winner_ml_on($winner) {
		$xml = '<manialink id="471108157053">
			<frame posn="'.$this->winpanelmainpos.'">
			<format style="TextCardMedium" textsize="3" />
			<label posn="0 0 0" sizen="40 3" halign="center" valign="center"  text="$i$s$o'.$winner[0][1].' $g$z$i$s$o$c90won  '.$this->bet_amount.' coppers!" />
			</frame>
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

	// display manialink for betting winner off
	function bet_winner_ml_off() {
		$xml = '<manialink id="471108157053">
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

	// display manialink for betting if no winner
	function bet_nowinner_ml_on() {
		$xml = '<manialink id="471108157054">
			<frame posn="'.$this->winpanelmainpos.'">
			<format style="TextCardMedium" textsize="3" />
			<label posn="0 0 0" sizen="30 5" halign="center" valign="center"  text="$i$s$o$f90Nobody won the last stake!" />
			</frame>
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

	// display manialink for betting if no winner winner off
	function bet_nowinner_ml_off() {
		$xml = '<manialink id="471108157054">
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

	// display manialink state of bet
	function bet_state_ml_on() {
		$xml = '<manialink id="471108157055">
			<frame posn="'.$this->statepanelmainpos.'">
			<format style="TextCardInfoSmall" textsize="1" />
			<label posn="0 0 0" sizen="13 3" halign="center" valign="center"  text="$i$s$o$b70Total win '.$this->bet_amount.' C" action="27378507" />
			</frame>
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

	// display manialink state of bet off
	function bet_state_ml_off() {
		$xml = '<manialink id="471108157055">
			</manialink>';
			$this->Aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
	}  // display_manialink

/************************** MANIALINKS END *********************************/

/*********************** CHAT COMMANDS START *******************************/

// proceed chat command bet and get bill id
	function bet_command($command) {
		$player = $command['author']; 	// get author
		$login = $player->login; 		// get login of author
		$nickname = $player->nickname;	// get nickname
		$coppers = $command['params']; 	// get parameter

		if ($this->Aseco->server->getGame() == 'TMF') {
			// check for TMUF server
			if ($this->Aseco->server->rights) {
				// check for TMUF player
				if ($player->rights) {
					// check if betting is enabled
					if ($this->enabled && $this->state) {
						// check for valid amount
						if ($coppers != '' && is_numeric($coppers)) {
							// check for betting time limit
							if (($this->countsec <= $this->timelimit_bet-2) && ($this->bet_active == 0)) {
								// check for minimum donation
								if ($coppers >= $this->minbet and $coppers <= $this->maxbet) {
									// check for double command
									if ($login != $this->bet_starter and !in_array($login, $this->acceptors)) {
										// start the transaction
										$message = '$f80$iYou have to pay '.$coppers.' coppers to set the next bet!$g$z'; // text in bill popup "start bet"
										$this->Aseco->client->query('SendBill', $login, (int)$coppers, $this->Aseco->formatColors($message), '');
										$billid = $this->Aseco->client->getResponse();
										$this->bets[$billid] = array($login, $nickname, $coppers, true);
									}
									else {
										$message = formatText('{#server}> {#error}You don\'t need to double click or double execute the chat command!!!!');
										$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
									}
								} else {
									$message = formatText('{#server}> {#error}You\'ll have to set {#highlite}minimum '.$this->minbet.' {#error}coppers and not more than {#highlite}'. $this->maxbet .' {#error}to proceed the bet!');
									$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
								}
							} else {
								$message = formatText('{#server}> {#error}Time limit for bet expired or bet already placed, wait till next round!');
								$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
							}
						} else {
							$message = formatText('{#server}> {#error}No amount of coppers defined. Please use {#interact}"/bet [coppers]"');
							$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
						}
					} else {
						$message = formatText('{#server}> {#error}Betting is disabled!');
						$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
					}
				} else {
					$message = formatText($this->Aseco->getChatMessage('UNITED_ONLY'), 'account');
					$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
				}
			} else {
				$message = formatText($this->Aseco->getChatMessage('UNITED_ONLY'), 'server');
				$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
			}
		} else {
			$message = $this->Aseco->getChatMessage('FOREVER_ONLY');
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
		}
	} //chat commmand bet coppers end

	// proceed chat command accept bet and get bill id
	function accept_command($command) {
		$player = $command['author']; // get author
		$login = $player->login; // get login of author
		$nickname = $player->nickname; // get nickname
		$coppers = $command['params']; // get parameter

		if ($this->Aseco->server->getGame() == 'TMF') {
			// check for TMUF server
			if ($this->Aseco->server->rights) {
				// check for TMUF player
				if ($player->rights) {
					// check if acceptor is not bet starter
					if ($login != $this->bet_starter) {
						// check if bet is set
						if ($this->bet_active) {
							// check if player already accepted
							if (!$this->acceptors || !in_array($login, $this->acceptors)) {
								// check for betting time limit
								if (($this->countsec <= $this->timelimit_accept)) {
										// start the transaction
										$message = '$f80$s$iYou have to pay '.$this->bet_start.' coppers to accept the bet!$g$z'; // text in bill popup "accept bet"
										$this->Aseco->client->query('SendBill', $login, (int)$this->bet_start, $this->Aseco->formatColors($message), '');
										$billid = $this->Aseco->client->getResponse();
										$this->bets[$billid] = array($login, $nickname, $this->bet_start, false);
								} else {
									$message = formatText('{#server}> {#error}Time limit to accept expired, wait till next round!');
									$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
								}
							} else {
								$message = formatText('{#server}> {#error}You accepted already!!!');
								$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
							}
						} else {
								$message = formatText('{#server}> {#error}No bet started yet!!!');
								$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
							}
					} else {
							$message = formatText('{#server}> {#error}You\'ve just started the bet by yourself!');
							$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
						}
				} else {
					$message = formatText($this->Aseco->getChatMessage('UNITED_ONLY'), 'account');
					$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
				}
			} else {
				$message = formatText($this->Aseco->getChatMessage('UNITED_ONLY'), 'server');
				$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
			}
		} else {
			$message = $this->Aseco->getChatMessage('FOREVER_ONLY');
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
		}
	} //chat commmand accept bet end

	// proceed chat command betcount and display list
	function betstake_command($command) {

		$player = $command['author'];
		$login = $player->login;

		if ($this->Aseco->server->getGame() == 'TMF') {
			// check for TMUF server
			if ($this->Aseco->server->rights) {
				$bgn = '{#black}';  // nickname begin
				$query = 'SELECT nickname, stake FROM betting WHERE stake != 0 ORDER BY stake DESC';
				$res = mysql_query($query);

				if (mysql_num_rows($res) > 0) {
					$stakes = array();
					$player->msgs = array();
					$total = 0;
					$i = 1;
					while($row = mysql_fetch_row($res)) {
						$nick = $row[0];
						if (!$this->Aseco->settings['lists_colornicks']) $nick = stripColors($nick);
						$stakes[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.', $bgn . $nick, $row[1].' C');
						$i++;
						$n++;
						$total = $total + $row[1];
					}
					$head = '{#welcome}List Total Player Stakes. Over all stake: $i'.$total.' coppers';
					$player->msgs[0] = array(1, $head, array(1.0, 0.2, 0.6, 0.2), array('Icons128x128_1', 'Coppers'));
					$n = 0;
					$send = array();
					foreach ($stakes as $stake) {
						if ($n == 14) {
							$player->msgs[] = $send;
							$n = 0;
							$send = array();
						}
						$send[] = $stake;
						$n++;
					}
					$player->msgs[] = $send;
					display_manialink_multi($player);

				} else {
					$message = '{#server}> {#error}No player(s) found!';
					$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
				}
				mysql_free_result($res);
			} else {
				$message = formatText($this->Aseco->getChatMessage('UNITED_ONLY'), 'server');
				$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
			}
		} else {
			$message = $this->Aseco->getChatMessage('FOREVER_ONLY');
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
		}
	}

	// proceed chat command betcount and display list
	function betwin_command($command) {
		$player = $command['author'];
		$login = $player->login;

		if ($this->Aseco->server->getGame() == 'TMF') {
			// check for TMUF server
			if ($this->Aseco->server->rights) {
				$bgn = '{#black}';  // nickname begin
				$query = 'SELECT nickname, wins, countwins FROM betting WHERE wins != 0 ORDER BY wins DESC';
				$res = mysql_query($query);

				if (mysql_num_rows($res) > 0) {
					$stakes = array();
					$player->msgs = array();
					$total = 0;
					$i = 1;
					while($row = mysql_fetch_row($res)) {
						$nick = $row[0];
						if (!$this->Aseco->settings['lists_colornicks']) $nick = stripColors($nick);
						$stakes[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.', $bgn . $nick, $row[1].' C', $row[2].' Wins');
						$i++;
						$n++;
						$total = $total + $row[1];
					}
					$head = '{#welcome}List Total Player Wins. Over all wins: $i'.$total.' coppers';
					$player->msgs[0] = array(1, $head, array(1.1, 0.15, 0.55, 0.2, 0.2), array('Icons128x128_1', 'Coppers'));
					$n = 0;
					$send = array();
					foreach ($stakes as $stake) {
						if ($n == 14) {
							$player->msgs[] = $send;
							$n = 0;
							$send = array();
						}
						$send[] = $stake;
						$n++;
					}
					$player->msgs[] = $send;
					display_manialink_multi($player);

				} else {
					$message = '{#server}> {#error}No player(s) found!';
					$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
				}
				mysql_free_result($res);
			} else {
				$message = formatText($this->Aseco->getChatMessage('UNITED_ONLY'), 'server');
				$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
			}
		} else {
			$message = $this->Aseco->getChatMessage('FOREVER_ONLY');
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
		}
	}

	// list all participants of actual bet
	function totalstake_command($command) {
		$player = $command['author'];
		$login = $player->login;

		if ($this->Aseco->server->getGame() == 'TMF') {
			// check for TMUF server
			if ($this->Aseco->server->rights) {
				// check for acitve bet
				if ($this->acceptors) {
					$bgn = '{#black}';  // nickname begin
					$player->msgs = array();
					$head = '{#welcome}List Bet Participants. Total Stake: $i'.$this->bet_amount.' coppers';
					$player->msgs[0] = array(1, $head, array(1.0, 0.2, 0.8), array('Icons128x128_1', 'Coppers'));
					$party = array();
					$i = 2;
					$nick = $this->bet_starter_nick;
					if (!$this->Aseco->settings['lists_colornicks']) $nick = stripColors($nick);
					$party[] = array(str_pad(1, 2, '0', STR_PAD_LEFT) . '.', $bgn . $nick );

					foreach ($this->acceptors as $nick => $login) {
						if (!$this->Aseco->settings['lists_colornicks']) $nick = stripColors($nick);
						$party[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.', $bgn . $nick );
						if ($i >= 13) {
							$player->msgs[] = $party;
							$party = array();
						}
						$i++;
					}
					$player->msgs[] = $party;
					display_manialink_multi($player);

				} else {
					$message = '{#server}> {#error}No active bet found!';
					$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
				}
			} else {
				$message = formatText($this->Aseco->getChatMessage('UNITED_ONLY'), 'server');
				$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array($this->Aseco->formatColors($message), $login));
			}
		} else {
			$message = $this->Aseco->getChatMessage('FOREVER_ONLY');
			$this->Aseco->client->addCall('ChatSendServerMessageToLogin', array( $this->Aseco->formatColors($message), $login));
		}
	}

	// admin chat command enable bettting
	function enable_command($command) {
		$admin = $command['author'];
		$login = $admin->login;
		$nick = $admin->nickname;
		$com = $command['params'];
		$com = strtolower($com);
		// check if chat command was allowed for a masteradmin/admin/operator
		if ($this->Aseco->isMasterAdmin($admin) || $this->Aseco->isAdmin($admin)) {
					// check for unlocked password (or unlock command)
			if ($this->Aseco->settings['lock_password'] == '' || $admin->unlocked) {
				if ($com) {
					switch ($com) {
						case 'on':
							$this->enabled = 1;
							$message = formatText('{#server}> '.$nick.' $g$cf0enabled betting. Start next round!');
							$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
						break;
						case 'off':
							$this->enabled = 0;
							$message = formatText('{#server}> '.$nick.' $g$cf0disabled betting. This is the last round!');
							$this->Aseco->client->addCall('ChatSendServerMessage', array($this->Aseco->formatColors($message)));
						break;
						default:
						break;
					}
				} else {
					$message = '{#server}> {#error}Missing parameter. Usage like $fff"/betstate on/off"{#error}!';
					$this->Aseco->client->query('ChatSendToLogin', $this->Aseco->formatColors($message), $login);
				}
			} else {
				// write warning in console
				$this->Aseco->console($login . ' tried to use admin chat command (not unlocked!): "/beton"');
				// show chat message
				$this->Aseco->client->query('ChatSendToLogin', $this->Aseco->formatColors('{#error}You don\'t have the required admin rights to do that, unlock first!'), $login);
			}
		} else {
			// write warning in console
			$this->Aseco->console($login . ' tried to use admin chat command (no permission!): "/beton" ');
			// show chat message
			$this->Aseco->client->query('ChatSendToLogin', $this->Aseco->formatColors('{#error}You don\'t have the required admin rights to do that!'), $login);
		}
	}

/*********************** CHAT COMMANDS END *******************************/

} // end class

// chat commands
Aseco::addChatCommand('bet', 'Bet amount of coppers "/bet coppers"');
Aseco::addChatCommand('accept', 'Accept the bet with given amount of coppers.');
Aseco::addChatCommand('betstake', 'Display players  stake.');
Aseco::addChatCommand('betwin', 'Display player wins.');
Aseco::addChatCommand('bettotalstake', 'List actual bet participants');
Aseco::addChatCommand('betstate', 'Enable/Disable betting "/betstate On/Off".');

global $bcp;
$bcp = new betting_coppers(false);

// events
Aseco::registerEvent('onSync', array($bcp, 'upToDate'));
Aseco::registerEvent('onStartup', 'betting_coppers_startup');
Aseco::registerEvent('onEverySecond', 'bet_counter');
Aseco::registerEvent('onNewChallenge', 'start_bet_round');
Aseco::registerEvent('onRestartChallenge', 'start_bet_round');
Aseco::registerEvent('onBillUpdated', 'bet_bill_update');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'bet_handle_buttonclick');
Aseco::registerEvent('onEndRace', 'pay_bet_winner');

Aseco::registerEvent('onBeginRound', array($bcp, 'reset_bet_counter'));
Aseco::registerEvent('onRestartChallenge', array($bcp, 'reset_bet_counter2'));

function betting_coppers_startup($aseco){
	global $bcp;
	$bcp->startUp($aseco);
}

function chat_bet($aseco,$command) {
	global $bcp;
	$bcp->bet_command($command);
}

function chat_accept($aseco,$command) {
	global $bcp;
	$bcp->accept_command($command);
}

function chat_betstake($aseco,$command) {
	global $bcp;
	$bcp->betstake_command($command);
}

function chat_betwin($aseco,$command) {
	global $bcp;
	$bcp->betwin_command($command);
}

function chat_bettotalstake($aseco,$command) {
	global $bcp;
	$bcp->totalstake_command($command);
}

function chat_betstate($aseco,$command) {
	global $bcp;
	$bcp->enable_command($command);
}

function bet_counter() {
	global $bcp;
	$bcp->on_everysecond();
}

function start_bet_round($aseco, $player) {
	global $bcp;
	$bcp->validate_players();
	$bcp->getServerCoppers();
	$bcp->reset_bet();
}

function bet_bill_update($aseco, $bill) {
	global $bcp;
	$bcp->bet_bill_upd($bill);
}

function bet_handle_buttonclick($aseco, $command) {
	global $bcp;
	$bcp->bet_handle_buttons($command);
}

function pay_bet_winner($aseco) {
	global $bcp;
	$bcp->get_winner();
}

?>