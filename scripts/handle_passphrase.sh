#!/bin/bash
# Passfile 
PASS=$1

# the following is just a one-liner method of making an executable
# one-line script echoing the password to STDOUT
 echo "echo $PASS" > "$PWD/ps.sh"
 chmod +x "$PWD/ps.sh"

# then the magic happens. NOTE: your DISPLAY variable should be set
# for this method to work (see ssh-add(1))
[[ -z "$DISPLAY" ]] && export DISPLAY=:0
< ~/.ssh/id_ed25519_$2 SSH_ASKPASS="$PWD/ps.sh" ssh-add - && shred -n3 -uz  $PWD/ps.sh

. /home/$(whoami)/aoo-engine/scripts/deploy_$2.sh