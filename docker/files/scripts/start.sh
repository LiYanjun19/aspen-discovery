#!/bin/bash
set -e

echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#+*#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%*=:::-+#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#=-::::::::-*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#+::::-=-::=-::::-*#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#+-::::=*%#=::+%#+-::::=*#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%+-::::::#%%%#=::+%%%%+::::::=#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#=::::=#-::=#%%#=::+%%%*-::++-:::-+#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#+-:::-*#%%#-::-*%#=::+%%+:::+#%%#=::::-*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%#=::::::*%%%%%#=:::=#=::+*-:::*%%%%%%-:::::-*%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%#+:::-*=::-#%%%%%%#-:::-::-:::=#%%%%%%*::-*=-::-*%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%+-::-*#%#-::-#%%%%%%#+:::::::-*%%%%%%%*-:-+%%#=:::=#%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%#=:::-#%%%%#=::-*%%%%%%%#-::::+%%%%%%%%=:::+%%%%%+:::-+%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%*-::-::*%%%%%%+-::=#%%%%%%#-:-=%%%%%%%*-::-#%%%%%#-::-::=#%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%+---+*---*%%%%%%#=---*%%%%%#=--+%%%%%#=---+#%%%%%#=---*----#%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%*---+%%+---+%%%%%%#+---=#%%%#=--+%%%%*----#%%%%%%#=---#%#=--=#%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%#---+%%%%*---=#%%%%%%#=---+%%#=--+%%#=---+%%%%%%%*----#%%%#=--=#%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%---=%%%%%%#=---*%%%%%%#*---=##=--+%*---=#%%%%%%#+---+%%%%%%#---*%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%+---#%%%%%%%#+---=%%%%%%%#=---*=--++---*%%%%%%%#----#%%%%%%%%=---%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%#----+***++=--------*%%%%%%%+----------*%%%%%%#=--------=++***----*%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%*--------------------=#%%%%%%+--------#%%%%%%*--------------------=%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%*--=##########%%%##----*%%%%%%*-----=#%%%%%#=---+##%%%#########*---#%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%+--=#%%%%%%%%%%%%%%%*---=#%%%%%+----#%%%%%*---=#%%%%%%%%%%%%%%%*---#%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%+--=#%%%%%%%%%%%###**+----*%%%%#=--+%%%%#+---=***###%%%%%%%%%%%*---#%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%*---*%%%##*+==-------------+#%%#=--+%%%*=------------===+*#%%%#=---#%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%*=-------------===+***+==---=#%#=--+%%*----=++**++==--------------=%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%#=--=+=+++**#%%%%%%%%%%%%#=--=##=--+#+---*#%%%%%%%%%%%##*+++==+---*%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%*---#%%%%%%%%%%%%%%%%%%%%%+---+=-=++--=#%%%%%%%%%%%%%%%%%%%%%+--=%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%+===#%%%%%%%%%%%%%#+=-===--===-=-=-======-=+*##%%%%%%%%%%%%+=-=#%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%#+===+#####**+===================================++*#####*+===*%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%*==============+*###%%%%##=======+#%%%%%###*+==============#%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%#======+**#%%%%%%%%%%%%%%%+====*%%%%%%%%%%%%%%%#*+======*%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%#+=====+*##%%%%%%%%%%%%%#===+#%%%%%%%%%%%%##*+=====+*#%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%#*========++++++++***#+==*#**++++++++========+*#%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##+++++++++++++++++++++++++++++++++++*#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#######*++++++*#######%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#*++*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#*++*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#*++*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#*++*%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#**%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%"
echo "%   Aspen Discovery starting for: ${SITE_NAME}"
echo "%"

export CONFIG_DIRECTORY="/usr/local/aspen-discovery/sites/$SITE_NAME"

# Adjust permissions if required
if [[ ! -z "${LOCAL_USER_ID}" && "${LOCAL_USER_ID}" != "33" ]]; then
	echo "%   Setting www-data to UID=${LOCAL_USER_ID}"
    usermod -o -u ${LOCAL_USER_ID} "www-data"
    # Fix permissions due to UID change
    chown -R "www-data" "/var/log/apache2"
fi

# Move to docker directory
cd "/usr/local/aspen-discovery/docker/files/scripts" || exit

# Check if site configuration exists
confSiteFile="$CONFIG_DIRECTORY/conf/config.ini"
if [ ! -f "$confSiteFile" ] ; then
	echo "%   * $confSiteFile not found, generating"
	mkdir -p "$CONFIG_DIRECTORY"
	if ! php createConfig.php "$CONFIG_DIRECTORY" ; then
		echo "%   ERROR: Failed to create instance config"
		exit 1
	fi
fi

# Initialize Aspen database
echo "%   * Initializing database";
if ! php initDatabase.php ; then
	echo "%   ERROR: Database initialization failed"
	exit 1
fi

# Initialize Koha Connection
echo "%   * Initializing Koha link";
if ! php initKohaLink.php ; then
	echo "%   ERROR: Koha link error"
	exit 1
fi

# Create missing dirs and fix ownership and permissions if needed
echo "%   * Setting up data and log directories";
if ! php createDirs.php ; then
	echo "%   ERROR: Directories creation and permission fixes failed"
	exit 1
fi

# FIXME: This seems to be creating dirs like images/images, etc
#        It should be put outside the codebase, and mounted accordingly

# Move and create temporarily sym-links to data directory
dataDir="/data/aspen-discovery/$SITE_NAME"
localDir="/usr/local/aspen-discovery/code/web"

ln -s "$dataDir/images" "$localDir/images"
ln -s "$dataDir/files" "$localDir/files"
ln -s "$dataDir/fonts" "$localDir/fonts"

echo "%   * Starting Apache"
# Start apache
service apache2 start

# Run any pending database updates
echo "127.0.0.1    $SITE_NAME" >> /etc/hosts
echo "%   * Triggering pending database updates"
curl -k http://"$SITE_NAME"/API/SystemAPI?method=runPendingDatabaseUpdates

echo "%"
echo "%   Aspen Discovery ready to use!"
echo "%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"

# FIXME: We should probably run Apache in foreground instead. We need to
#        figure an approach to the curl above in order to do it
/bin/bash -c "trap : TERM INT; sleep infinity & wait"