#!/bin/sh

EMAIL=$1
PASSWORD=$2

FIREBASE_API_KEY='apikey'
EMU_HOST='localhost:9099'

if [ -z "$EMAIL" ]; then
  EMAIL='a@a.test'
fi
if [ -z "$PASSWORD" ]; then
  PASSWORD='0000'
fi

RESPONSE_JSON=`
curl -s \
  -X POST \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"returnSecureToken\":true}" \
  "http://$EMU_HOST/identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=$FIREBASE_API_KEY"
`

if [ -z "$RESPONSE_JSON" ]; then
  echo "Failed to get response" >&2
  exit 1
fi

TOKEN=`echo $RESPONSE_JSON | sed -E 's/.+"idToken":"([^"]+)".+/\1/'`

if [ -z "$TOKEN" ]; then
  echo "Failed to get token" >&2
  echo $RESPONSE_JSON >&2
else
  echo $TOKEN
fi
