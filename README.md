# Trackmania United Forever Dedicated Server

This repository provides the tools and Docker image needed to set up a customizable Trackmania Nations/United Forever server bundled with XAseco in a Docker container. The provided Docker image simplifies server deployment and management, while allowing extensive configuration and customization options for both the Trackmania server and the XAseco plugin manager.

# How to use this image

### docker run

`docker run --env-file <path/to/env-file> -p 2350/2350:udp -p 3450/3450:udp [-v {volumes}] mcarigs/tmserver`

### docker-compose

Check the default [`docker-compose.yml`](./docker-compose.yml) and adjust it to your needs and according to the documentation below, then run:

`docker-compose up -d`

## Configuration - Trackmania

Add a new file named `.env` with the following variables:

### Mandatory

```
SERVER_LOGIN                | Server account login
SERVER_LOGIN_PASSWORD       | Server account password
MASTERADMIN_LOGIN           | Login name of the player to assume MasterAdmin role for XAseco
MYSQL_HOST                  | Host of MySQL database -> Default : db
MYSQL_LOGIN                 | Username of MySQL database -> Default : trackmania
MYSQL_PASSWORD (Mandatory)  | Password of MySQL user
MYSQL_DATABASE              | Name of MySQL database -> Default : trackmania
```

### Optional

```
SERVER_PASSWORD             | Password required for players to join the server. Omit this value to allow anyone to join
SERVER_SA_PASSWORD          | Password for SuperAdmin credential -> when left empty will be randomly generated
SERVER_ADM_PASSWORD         | Password for Admin credential -> when left empty will be randomly generated
SERVER_PORT                 | Port for server communications -> Default : 2350
SERVER_P2P_PORT             | Port for peer2peer communication -> Default : 3450
SERVER_NAME                 | Server name in ingame browser -> Default : "Trackmania Server"
SERVER_COMMENT              | Server description -> Default : "This is a Trackmania Server"
SERVER_PASSWORD             | If you want to secure your server against unwanted logins, set a server password
HIDE_SERVER                 | Whether you want your server public or not -> Default : 0 (public)
MAX_PLAYERS                 | Max player count -> Default : 32
PACKMASK                    | Leave empty to change server mode to United -> Default : stadium (Nations)
```

### Gamemodes

```
GAMEMODE                    | 0 (Rounds), 1 (TimeAttack), 2 (Team), 3 (Laps), 4 (Stunts) -> Default : 1
CHATTIME                    | Chat time value in milliseconds -> Default : 10000
FINISHTIMEOUT               | Finish timeout value in milliseconds (0 = classic, 1 = adaptive -> Default : 1)
DISABLERESPAWN              | 0 (respawns enabled), 1 (respawns disabled) -> Default : 0
```

#### Gamemode : Rounds

```
ROUNDS_POINTSLIMIT          | Points limit for rounds mode -> Default : 30
```

#### Gamemode : TimeAttack

```
TIMEATTACK_LIMIT            | Time limit in milliseconds for time attack mode -> Default : 180000
```

#### Gamemode : Team

```
TEAM_POINTSLIMIT            | Points limit for team mode -> Default : 50
TEAM_MAXPOINTS              | Number of maximum points per round for team mode -> Default : 6
```

#### Gamemode : Laps

```
LAPS_NBLAPS                 | Number of laps for laps mode -> Default : 5
LAPS_TIMELIMIT              | Time limit in milliseconds for laps mode -> Default : 0
```

#### Gamemode : Cup

```
CUP_POINTSLIMIT             | Points limit for cup mode -> Default : 100
CUP_ROUNDSPERCHALLENGE      | Rounds per challenge -> Default : 5
CUP_NBWINNERS               | Number of Winners -> Default : 3
CUP_WARMUPDURATION          | Warmup duration -> Default : 2
```

#### Custom Music

```
CUSTOM_MUSIC_ENABLED        | Whether or not you want to enable custom music in your server
MUSIC_SERVER                | Web server URI or file path relative to '/var/lib/tmserver/GameData' containing your custom music
AUTO_NEXT_SONG              | Whether or not to automatically load the next song when the next track is loaded
AUTO_SHUFFLE                | Whether or not to automatically shuffle songs on server start-up & reload
ALLOW_JUKEBOX               | Whether or not to allow players to add songs to the queue
```
  - If using a web server, it must allow direct downloads

# Customization

Apart from the configuration possibilities, I've included some scripts to add a custom tracklist, configuration files, plugins and a blacklist to disable unwanted default plugins.

### Custom Tracks

While the Nadeo tracks are available in this repository and accessible under `GameData/Tracks/Challenges/Nadeo/`, adding custom tracks from e.g. [Trackmania Exchange](https://tmuf.exchange/) is as simple as placing the files
in the `tracks/` folder and mounting it to `/var/lib/tmserver/GameData/Tracks/Custom/`.

```
[...]
  tmserver:
    image: mcarigs/tmserver
    [...]
    volumes:
     - ./tracks:/var/lib/tmserver/GameData/Tracks/Custom
[...]
```

### Custom Track Playlist

Creating your own playlist is as easy as adding tracks to the `tracks` folder and running the included [`update_tracks.sh`](./update_tracks.sh) script which will add each track on a separate line in the [`playlist.txt`](./playlist.txt) file using its relative path to the `/var/lib/tmserver/GameData/Tracks` folder.

#### Example:

```
├── docker-compose.yml
├── playlist.txt
├── tmserver
├── tracks
│  ├── mini01.Challenge.Gbx
│  ├── SpeedxZxZ.Challenge.Gbx
└── xaseco
```

Contents of `playlist.txt` after running `./update_tracks.sh`:
```
Custom/mini01.Challenge.Gbx
Custom/SpeedxZxZ.Challenge.Gbx
```
### Custom Music

To add custom music to your server, place song files in `.ogg` format in the `music/` directory. Make sure to avoid spaces and special characters in the filename. For the songs to load properly, you'll also need to modify `xaseco/musicserver.xml` and `xaseco/musictagscache.xml` (the latter is optional but recommended so that the song name & artist are displayed properly in-game). I've included a Python script, `update_music.py`, to automate this process for you. To ensure this script works properly, the song files should follow this naming convention: `Artist_Name-Song_Name.ogg`. Note that underscores are used in place of spaces and a dash is used to separate the artist and song name.

I'm aware that storing audio files in git isn't a great idea, especially if you have a lot of songs, so I recommend using Git LFS to track these files if you decide to store them this way.
```
git lfs install
git lfs track "*.ogg"
git add .gitattributes
git add file.ogg
git commit -m "Add song file"
git push origin
```
### Custom configuration files

Most plugins need you to provide valid configuration files to function in the first place. Place these in the `config/` folder and mount it to `/var/lib/xaseco/config/`. All files will be linked to XAseco's root folder. **Careful, as this will overwrite exisiting default files and [`localdatabase.xml`](./xaseco/localdatabase.xml) as well.**

### Custom plugins

I've included a few of my favorite custom plugs if you wish to use them. You can download more custom plugins from https://plugins.xaseco.org/browse.php. After downloading a plugin, place the `<plugin_name>.xml` file in the [`xaseco/`](./xaseco/) folder, then place the `plugin.<plugin_name>.php` file in the [`plugins/`](./plugins/) folder and mount it to `/var/lib/xaseco/plugins/custom/`.

### Plugin blacklist

Use the included [`blacklist`](./blacklist) file and list plugins by filename that you want ignored on XAseco's boot. Mount this file at `/var/lib/xaseco/blacklist`.

#### Example:
```
plugin.rasp_irc.php
plugin.alternate_scoretable.php
plugin.best_checkpoint_times.php
plugin.finishpayout.php
plugin.flexitime.php
plugin.greeting_dude.php
plugin.lotto.php
plugin.mania_karma.php
plugin.nouse.betting.php
plugin.nouse.message.php
plugin.records_eyepiece.php
```
By default, the custom plugins I've included are in the blacklist. Remove any of them from the file to have XAseco load it at startup
