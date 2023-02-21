#!/bin/sh

URL="https://elbrus.kurisa.ch/"
PKEYFILE="/etc/ssh/ssh_host_rsa_key"
HASHTYPE="sha256"
SERVER="yukari"

UPTIME="`uptime | base64 -w0`"
SIGNATURE="`echo -n "$UPTIME" | openssl dgst -sign $PKEYFILE | base64 -w0`"
curl -X POST --data-urlencode uptime=$UPTIME --data-urlencode sig=$SIGNATURE --data-urlencode hashtype=$HASHTYPE $URL
