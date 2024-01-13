FROM node:21.5.0-alpine as build

WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile
COPY ./src ./src
COPY \
  ./.eslintrc.cjs \
  ./index.html \
  ./tsconfig.json \
  ./tsconfig.node.json \
  ./vite.config.ts \
  ./

RUN yarn build

FROM httpd:2.4.58-alpine

COPY --from=build /app/dist/ /usr/local/apache2/htdocs/
COPY ./public/ /usr/local/apache2/htdocs/

EXPOSE 80
