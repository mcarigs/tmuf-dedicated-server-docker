#!/bin/bash

TRACKS=(tracks/*)

> playlist.txt

for track in "${TRACKS[@]}"; do
  t=$(echo ${track} | sed 's/tracks\///g')
  printf "${BLUE}Adding track:${YLW} ${t} ${NC}\n"
  echo "Challenges/Custom/${t}" >> playlist.txt
done

printf "\n${GREEN}Added ${YLW}${#TRACKS[@]}${GREEN} tracks to the playlist${NC}\n"
