#!/command/with-contenv bash
ls -la
config=( )
database=( )

# Config
SERVER_NAME=${SERVER_NAME:-Trackmania Server} && config+=( "SERVER_NAME" )

SERVER_NATION=${SERVER_NATION:-USA} && config+=( "SERVER_NATION" )

MASTERADMIN_LOGIN=${MASTERADMIN_LOGIN:?ERROR | One player needs to be assigned the MasterAdmin role.} && \
    config+=( "MASTERADMIN_LOGIN" )

ADMIN_LOGIN=${ADMIN_LOGIN} && \
    config+=( "ADMIN_LOGIN" )

MASTERADMIN_IP=${MASTERADMIN_IP} && config+=( "MASTERADMIN_IP" )

SERVER_LOGIN=${SERVER_LOGIN?:ERROR | ServerLogin is missing. Server cannot start.} && \
    config+=( "SERVER_LOGIN" )

SERVER_LOGIN_PASSWORD=${SERVER_LOGIN_PASSWORD?:ERROR | ServerLoginPassword is missing. Server cannot start.} && \
    config+=( "SERVER_LOGIN_PASSWORD" )

SERVER_SA_PASSWORD=${SERVER_SA_PASSWORD:?ERROR | SuperAdminPassword was not given. Please refer to your TMServer configuration.} && \
    config+=( "SERVER_SA_PASSWORD" )

# Local Database
MYSQL_HOST=${MYSQL_HOST:-db} && \
    database+=( "MYSQL_HOST" )

MYSQL_LOGIN=${MYSQL_LOGIN:?-trackmania} && \
    database+=( "MYSQL_LOGIN" )

MYSQL_PASSWORD=${MYSQL_PASSWORD:?ERROR | MySQL password was not given...} && \
    database+=( "MYSQL_PASSWORD" )

MYSQL_DATABASE=${MYSQL_DATABASE:-trackmania} && \
    database+=( "MYSQL_DATABASE" )

# Optional
TMSERVER_HOST=${TMSERVER_HOST:-localhost} && \
    config+=( "TMSERVER_HOST" )
echo "INFO | TMSERVER_HOST: ${TMSERVER_HOST}"

TMSERVER_PORT=${TMSERVER_PORT:-5000} && \
    config+=( "TMSERVER_PORT" )
echo "INFO | TMSERVER_PORT: ${TMSERVER_PORT}"

CUSTOM_MUSIC_ENABLED=${CUSTOM_MUSIC_ENABLED:-False} && \
    config+=( "CUSTOM_MUSIC_ENABLED" )
echo "INFO | CUSTOM_MUSIC_ENABLED: ${CUSTOM_MUSIC_ENABLED}"

AUTO_NEXT_SONG=${AUTO_NEXT_SONG:-True} && \
    config+=( "AUTO_NEXT_SONG" )
echo "INFO | AUTO_NEXT_SONG: ${AUTO_NEXT_SONG}"

AUTO_SHUFFLE=${AUTO_SHUFFLE:-False} && \
    config+=( "AUTO_SHUFFLE" )
echo "INFO | AUTO_SHUFFLE: ${AUTO_SHUFFLE}"

ALLOW_JUKEBOX=${ALLOW_JUKEBOX:-False} && \
    config+=( "ALLOW_JUKEBOX" )
echo "INFO | ALLOW_JUKEBOX: ${ALLOW_JUKEBOX}"

MUSIC_SERVER=${MUSIC_SERVER:?WARN | MUSIC_SERVER is not defined. Custom music will not be enabled} && \
    config+=( "MUSIC_SERVER" )

# Parse config.xml
for idx in "${!config[@]}"; do
    arg=${config[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" config.xml
done

# Parse adminops.xml
for idx in "${!config[@]}"; do
    arg=${config[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" adminops.xml
done

# Parse deadimania.xml
for idx in "${!config[@]}"; do
    arg=${config[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" dedimania.xml
done

# Parse musicserver.xml
for idx in "${!config[@]}"; do
    arg=${config[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" musicserver.xml
done

# Parse lotto_config.xml
for idx in "${!config[@]}"; do
    arg=${config[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" lotto_config.xml
done

# Parse finish_config.xml
for idx in "${!config[@]}"; do
    arg=${config[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" finish_config.xml
done

# Parse mania_karma.xml
for idx in "${!config[@]}"; do
    arg=${config[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" mania_karma.xml
done

# Parse localdatabase.xml
for idx in "${!database[@]}"; do
    arg=${database[$idx]}
    sed -i -e "s/@$arg@/${!arg}/g" localdatabase.xml
done
