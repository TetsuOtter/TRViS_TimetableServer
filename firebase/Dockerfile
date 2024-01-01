FROM node:21-alpine

# ref: https://zenn.dev/cbcloud_blog/articles/6256f1a3d05a18

WORKDIR /emulator

RUN apk add --update --no-cache\
  openjdk21-jre

RUN npm install -g firebase-tools

COPY \
  ./.firebaserc\
  ./firebase.json\
  ./

EXPOSE 9099 4000

CMD [ "npx", "firebase", "emulators:start" ]
