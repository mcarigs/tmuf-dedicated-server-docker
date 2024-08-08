#!/command/with-contenv bash

set -e

[[ "$(id -u)" == 0 ]] && s6-setuidgid trackmania "$0"

cd /var/lib/tmserver

# Parse config files
./bin/eval_env.sh

# Parse playlist files
./bin/eval_playlist.sh

########## IMPORTANT PLEASE READ ##########
# By default, this server will run in LAN mode making it accessible within your local network only
# Replace "/lan" with "/internet" if you want the server to be publicly accessible via the internet
exec "./TrackmaniaServer" \
     "/nodaemon" \
     "/lan" \
     "/game_settings=MatchSettings/playlist.xml" \
     "/dedicated_cfg=config.xml" \
     "/autoquit"
