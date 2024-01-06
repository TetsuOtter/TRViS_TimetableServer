# trvis_backend\main - PHP Slim 4 Server library for TRViS用 時刻表管理用API

* [OpenAPI Generator](https://openapi-generator.tech)
* [Slim 4 Documentation](https://www.slimframework.com/docs/v4/)

This server has been generated with [Slim PSR-7](https://github.com/slimphp/Slim-Psr7) implementation.
[PHP-DI](https://php-di.org/doc/frameworks/slim.html) package used as dependency container.

## Requirements

* Web server with URL rewriting
* PHP 7.4 or newer

This package contains `.htaccess` for Apache configuration.
If you use another server(Nginx, HHVM, IIS, lighttpd) check out [Web Servers](https://www.slimframework.com/docs/v3/start/web-servers.html) doc.

## Installation via [Composer](https://getcomposer.org/)

Navigate into your project's root directory and execute the bash command shown below.
This command downloads the Slim Framework and its third-party dependencies into your project's `vendor/` directory.
```bash
$ composer install
```

## Add configs

[PHP-DI package](https://php-di.org/doc/getting-started.html) helps to decouple configuration from implementation. App loads configuration files in straight order(`$env` can be `prod` or `dev`):
1. `config/$env/default.inc.php` (contains safe values, can be committed to vcs)
2. `config/$env/config.inc.php` (user config, excluded from vcs, can contain sensitive values, passwords etc.)
3. `lib/App/RegisterDependencies.php`

## Start devserver

Run the following command in terminal to start localhost web server, assuming `./php-slim-server/public/` is public-accessible directory with `index.php` file:
```bash
$ php -S localhost:8888 -t php-slim-server/public
```
> **Warning** This web server was designed to aid application development.
> It may also be useful for testing purposes or for application demonstrations that are run in controlled environments.
> It is not intended to be a full-featured web server. It should not be used on a public network.

## Tests

### PHPUnit

This package uses PHPUnit 8 or 9(depends from your PHP version) for unit testing.
[Test folder](tests) contains templates which you can fill with real test assertions.
How to write tests read at [2. Writing Tests for PHPUnit - PHPUnit 8.5 Manual](https://phpunit.readthedocs.io/en/8.5/writing-tests-for-phpunit.html).

#### Run

Command | Target
---- | ----
`$ composer test` | All tests
`$ composer test-apis` | Apis tests
`$ composer test-models` | Models tests

#### Config

Package contains fully functional config `./phpunit.xml.dist` file. Create `./phpunit.xml` in root folder to override it.

Quote from [3. The Command-Line Test Runner — PHPUnit 8.5 Manual](https://phpunit.readthedocs.io/en/8.5/textui.html#command-line-options):

> If phpunit.xml or phpunit.xml.dist (in that order) exist in the current working directory and --configuration is not used, the configuration will be automatically read from that file.

### PHP CodeSniffer

[PHP CodeSniffer Documentation](https://github.com/squizlabs/PHP_CodeSniffer/wiki). This tool helps to follow coding style and avoid common PHP coding mistakes.

#### Run

```bash
$ composer phpcs
```

#### Config

Package contains fully functional config `./phpcs.xml.dist` file. It checks source code against PSR-1 and PSR-2 coding standards.
Create `./phpcs.xml` in root folder to override it. More info at [Using a Default Configuration File](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Advanced-Usage#using-a-default-configuration-file)

### PHPLint

[PHPLint Documentation](https://github.com/overtrue/phplint). Checks PHP syntax only.

#### Run

```bash
$ composer phplint
```

## Show errors

Switch your app environment to development in `public/.htaccess` file:
```ini
## .htaccess
<IfModule mod_env.c>
    SetEnv APP_ENV 'development'
</IfModule>
```

## Mock Server
Since this feature should be used for development only, change environment to `development` and send additional HTTP header `X-dev_t0r-Mock: ping` with any request to get mocked response.
CURL example:
```console
curl --request GET \
    --url 'http://localhost:8888/v2/pet/findByStatus?status=available' \
    --header 'accept: application/json' \
    --header 'X-dev_t0r-Mock: ping'
[{"id":-8738629417578509312,"category":{"id":-4162503862215270400,"name":"Lorem ipsum dol"},"name":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem i","photoUrls":["Lor"],"tags":[{"id":-3506202845849391104,"name":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectet"}],"status":"pending"}]
```

Used packages:
* [Openapi Data Mocker](https://github.com/ybelenko/openapi-data-mocker) - first implementation of OAS3 fake data generator.
* [Openapi Data Mocker Server Middleware](https://github.com/ybelenko/openapi-data-mocker-server-middleware) - PSR-15 HTTP server middleware.
* [Openapi Data Mocker Interfaces](https://github.com/ybelenko/openapi-data-mocker-interfaces) - package with mocking interfaces.

## Logging

Build contains pre-configured [`monolog/monolog`](https://github.com/Seldaek/monolog) package. Make sure that `logs` folder is writable.
Add required log handlers/processors/formatters in `lib/App/RegisterDependencies.php`.

## API Endpoints

All URIs are relative to *http://localhost:8080/api/v1*

> Important! Do not modify abstract API controllers directly! Instead extend them by implementation classes like:

```php
// src/Api/PetApi.php

namespace dev_t0r\trvis_backend\api;

use dev_t0r\trvis_backend\api\AbstractPetApi;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class PetApi extends AbstractPetApi
{
    public function addPet(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        // your implementation of addPet method here
    }
}
```

When you need to inject dependencies into API controller check [PHP-DI - Controllers as services](https://github.com/PHP-DI/Slim-Bridge#controllers-as-services) guide.

Place all your implementation classes in `./src` folder accordingly.
For instance, when abstract class located at `./lib/Api/AbstractPetApi.php` you need to create implementation class at `./src/Api/PetApi.php`.

Class | Method | HTTP request | Description
------------ | ------------- | ------------- | -------------
*AbstractApiInfoApi* | **getApiInfo** | **GET** / | APIの情報を取得する
*AbstractAuthApi* | **issueToken** | **POST** /auths | 認証トークンを発行
*AbstractColorApi* | **createColor** | **POST** /colors | 作成する
*AbstractColorApi* | **deleteColor** | **DELETE** /colors/{colorId} | 削除する
*AbstractColorApi* | **getColor** | **GET** /colors/{colorId} | 1件取得する
*AbstractColorApi* | **getColorList** | **GET** /colors | 複数件取得する
*AbstractColorApi* | **updateColor** | **PUT** /colors/{colorId} | 更新する
*AbstractDumpApi* | **dumpTimetable** | **GET** /dump/{workGroupId} | まとめて出力する
*AbstractInviteKeyApi* | **getMyInviteKeyList** | **GET** /invite_keys | 一覧を取得する
*AbstractInviteKeyApi* | **createInviteKey** | **POST** /work_groups/{workGroupId}/invite_keys | 作成する
*AbstractInviteKeyApi* | **deleteInviteKey** | **DELETE** /invite_keys/{inviteKeyId} | 無効化する
*AbstractInviteKeyApi* | **getInviteKey** | **GET** /invite_keys/{inviteKeyId} | 1件取得する
*AbstractInviteKeyApi* | **getInviteKeyList** | **GET** /work_groups/{workGroupId}/invite_keys | 一覧を取得する
*AbstractInviteKeyApi* | **updateInviteKey** | **PUT** /invite_keys/{inviteKeyId} | 更新する
*AbstractInviteKeyApi* | **useInviteKey** | **POST** /invite_keys/{inviteKeyId} | 使用する
*AbstractStationApi* | **createStation** | **POST** /work_groups/{workGroupId}/stations | 作成する
*AbstractStationApi* | **deleteStation** | **DELETE** /work_groups/{workGroupId}/stations/{stationId} | 削除する
*AbstractStationApi* | **getStation** | **GET** /work_groups/{workGroupId}/stations/{stationId} | 1件取得する
*AbstractStationApi* | **getStationList** | **GET** /work_groups/{workGroupId}/stations | 複数件取得する
*AbstractStationApi* | **updateStation** | **PUT** /work_groups/{workGroupId}/stations/{stationId} | 更新する
*AbstractStationTrackApi* | **createStationTrack** | **POST** /work_groups/{workGroupId}/stations/{stationId}/tracks | 作成する
*AbstractStationTrackApi* | **deleteStationTrack** | **DELETE** /work_groups/{workGroupId}/stations/{stationId}/tracks/{stationTrackId} | 削除する
*AbstractStationTrackApi* | **getStationTrack** | **GET** /work_groups/{workGroupId}/stations/{stationId}/tracks/{stationTrackId} | 1件取得する
*AbstractStationTrackApi* | **getStationTrackList** | **GET** /work_groups/{workGroupId}/stations/{stationId}/tracks | 複数件取得する
*AbstractStationTrackApi* | **updateStationTrack** | **PUT** /work_groups/{workGroupId}/stations/{stationId}/tracks/{stationTrackId} | 更新する
*AbstractTimetableRowApi* | **createTimetableRow** | **POST** /work_groups/{workGroupId}/works/{workId}/trains/{trainId}/timetable_rows | 作成する
*AbstractTimetableRowApi* | **deleteTimetableRow** | **DELETE** /work_groups/{workGroupId}/works/{workId}/trains/{trainId}/timetable_rows/{timetableRowId} | 削除する
*AbstractTimetableRowApi* | **getTimetableRow** | **GET** /work_groups/{workGroupId}/works/{workId}/trains/{trainId}/timetable_rows/{timetableRowId} | 1件取得する
*AbstractTimetableRowApi* | **getTimetableRowList** | **GET** /work_groups/{workGroupId}/works/{workId}/trains/{trainId}/timetable_rows | 複数件取得する
*AbstractTimetableRowApi* | **updateTimetableRow** | **PUT** /work_groups/{workGroupId}/works/{workId}/trains/{trainId}/timetable_rows/{timetableRowId} | 更新する
*AbstractTrainApi* | **createTrain** | **POST** /work_groups/{workGroupId}/works/{workId}/trains | 作成する
*AbstractTrainApi* | **deleteTrain** | **DELETE** /work_groups/{workGroupId}/works/{workId}/trains/{trainId} | 削除する
*AbstractTrainApi* | **getTrain** | **GET** /work_groups/{workGroupId}/works/{workId}/trains/{trainId} | 1件取得する
*AbstractTrainApi* | **getTrainList** | **GET** /work_groups/{workGroupId}/works/{workId}/trains | 複数件取得する
*AbstractTrainApi* | **updateTrain** | **PUT** /work_groups/{workGroupId}/works/{workId}/trains/{trainId} | 更新する
*AbstractWorkApi* | **createWork** | **POST** /work_groups/{workGroupId}/works | 作成する
*AbstractWorkApi* | **deleteWork** | **DELETE** /work_groups/{workGroupId}/works/{workId} | 削除する
*AbstractWorkApi* | **getWork** | **GET** /work_groups/{workGroupId}/works/{workId} | 1件取得する
*AbstractWorkApi* | **getWorkList** | **GET** /work_groups/{workGroupId}/works | 複数件取得する
*AbstractWorkApi* | **updateWork** | **PUT** /work_groups/{workGroupId}/works/{workId} | 更新する
*AbstractWorkGroupApi* | **createWorkGroup** | **POST** /work_groups | 作成する
*AbstractWorkGroupApi* | **getWorkGroupList** | **GET** /work_groups | 複数件取得する
*AbstractWorkGroupApi* | **deleteWorkGroup** | **DELETE** /work_groups/{workGroupId} | 削除する
*AbstractWorkGroupApi* | **getPrivilege** | **GET** /work_groups/{workGroupId}/privileges | 権限情報を取得する
*AbstractWorkGroupApi* | **getWorkGroup** | **GET** /work_groups/{workGroupId} | 1件取得する
*AbstractWorkGroupApi* | **updatePrivilege** | **PUT** /work_groups/{workGroupId}/privileges | 権限を更新する
*AbstractWorkGroupApi* | **updateWorkGroup** | **PUT** /work_groups/{workGroupId} | 更新する


## Models

* dev_t0r\trvis_backend\model\ApiInfo
* dev_t0r\trvis_backend\model\Color
* dev_t0r\trvis_backend\model\Color8bit
* dev_t0r\trvis_backend\model\ColorReal
* dev_t0r\trvis_backend\model\InviteKey
* dev_t0r\trvis_backend\model\Schema
* dev_t0r\trvis_backend\model\Station
* dev_t0r\trvis_backend\model\StationLocationLonlat
* dev_t0r\trvis_backend\model\StationTrack
* dev_t0r\trvis_backend\model\TRViSJsonTimetableRow
* dev_t0r\trvis_backend\model\TRViSJsonTrain
* dev_t0r\trvis_backend\model\TRViSJsonWork
* dev_t0r\trvis_backend\model\TRViSJsonWorkGroup
* dev_t0r\trvis_backend\model\TimetableRow
* dev_t0r\trvis_backend\model\TokenRequest
* dev_t0r\trvis_backend\model\TokenResponse
* dev_t0r\trvis_backend\model\Train
* dev_t0r\trvis_backend\model\Work
* dev_t0r\trvis_backend\model\WorkGroup
* dev_t0r\trvis_backend\model\WorkGroupsPrivilege


## Authentication

### Advanced middleware configuration
Ref to used Slim Token Middleware [dyorg/slim-token-authentication](https://github.com/dyorg/slim-token-authentication/tree/1.x#readme)
