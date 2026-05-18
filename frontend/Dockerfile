FROM node:24.15.0-alpine as build

WORKDIR /app
# yarn workspaces はインストール時に全 workspace の package.json を要求する。
# trvis-api の package.json も install レイヤに含め、ソース変更で install が
# 再実行されないようにキャッシュ境界を分ける。
COPY package.json yarn.lock .yarnrc.yml ./
COPY packages/trvis-api/package.json ./packages/trvis-api/package.json
RUN corepack enable && yarn install --immutable
COPY \
  ./.env \
  ./index.html \
  ./tsconfig.json \
  ./tsconfig.node.json \
  ./vite.config.ts \
  ./
# trvis-api は workspace。yarn は workspace の prepare を自動実行しないため、
# yarn build (tsc -b packages/trvis-api/tsconfig.build.json) で dist を生成する。
# .dockerignore で node_modules/dist/.yarn を除外済み。
COPY ./packages ./packages
COPY ./src ./src

RUN yarn build

FROM httpd:2.4.58-alpine

COPY ./public/ /usr/local/apache2/htdocs/
COPY --from=build /app/dist/ /usr/local/apache2/htdocs/

EXPOSE 80
