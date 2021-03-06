#!/bin/bash
# This script will trigger a session on youtube
# Lookup the meta data using only one pull
#
# Add to the playlist as ...
#
# #EXTINF:-1, YouTube Audio
# http://localhost/yt-play/?type=list&src=PLKK4T0Fm7nwGEZUZtQn7hbciVbN6hkVFl ...

sudo echo "#EXTM3U" > /var/www/yt-play/yt-list.m3u
sudo echo "" >> /var/www/yt-play/yt-list.m3u
sudo youtube-dl -U --no-warnings --skip-download -i --playlist-end 5 --playlist-random --sleep-interval 30 --proxy socks5://192.241.187.83:31650 --force-ipv4 -j $1 > sourcelist.json
sudo python sourcelist.py

mpc clear
mpc load YouTube_Play
mpc play