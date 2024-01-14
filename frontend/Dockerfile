FROM node:21.5.0-alpine as build

WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile
COPY \
  ./index.html \
  ./tsconfig.json \
  ./tsconfig.node.json \
  ./vite.config.ts \
  ./
COPY ./src ./src

RUN yarn build

FROM httpd:2.4.58-alpine

COPY ./public/ /usr/local/apache2/htdocs/
COPY --from=build /app/dist/ /usr/local/apache2/htdocs/

EXPOSE 80
