<?php

/**
 * TRViS用 時刻表管理用API
 * PHP version 7.4
 *
 * @package dev_t0r
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */

/**
 * No description provided (generated by Openapi Generator https://github.com/openapitools/openapi-generator)
 * The version of the OpenAPI document: 1.0.0
 * Generated by: https://github.com/openapitools/openapi-generator.git
 */

/**
 * NOTE: This class is auto generated by the openapi generator program.
 * https://github.com/openapitools/openapi-generator
 * Do not edit the class manually.
 * Extend this class with your controller. You can inject dependencies via class constructor,
 * @see https://github.com/PHP-DI/Slim-Bridge basic example.
 */
namespace dev_t0r\trvis_backend\api;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpNotImplementedException;

/**
 * AbstractWorkGroupApi Class Doc Comment
 *
 * @package dev_t0r\trvis_backend\api
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
abstract class AbstractWorkGroupApi
{
    /**
     * POST createWorkGroup
     * Summary: 作成する
     * Notes: Workのまとまり (WorkGroup) を新しく作成する  この操作にはサインインが必要です。
     * Output-Formats: [application/json]
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     * @throws HttpNotImplementedException to force implementation class to override this method
     */
    public function createWorkGroup(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $message = "How about implementing createWorkGroup as a POST method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
        throw new HttpNotImplementedException($request, $message);
    }

    /**
     * DELETE deleteWorkGroup
     * Summary: 削除する
     * Notes: 既存の「Workのまとまり (WorkGroup)」を削除する  このデータが属するWorkGroupへのADMIN権限が必要です。
     * Output-Formats: [application/json]
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string $workGroupId WorkGroupのID
     *
     * @return ResponseInterface
     * @throws HttpNotImplementedException to force implementation class to override this method
     */
    public function deleteWorkGroup(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $workGroupId
    ): ResponseInterface {
        $message = "How about implementing deleteWorkGroup as a DELETE method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
        throw new HttpNotImplementedException($request, $message);
    }

    /**
     * GET getPrivilege
     * Summary: 権限情報を取得する
     * Notes: このWorkGroupに関する自身の権限を取得する。  管理者の場合は、指定のユーザの権限を取得することも可能。
     * Output-Formats: [application/json]
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string $workGroupId WorkGroupのID
     *
     * @return ResponseInterface
     * @throws HttpNotImplementedException to force implementation class to override this method
     */
    public function getPrivilege(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $workGroupId
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $uid = (key_exists('uid', $queryParams)) ? $queryParams['uid'] : null;
        $uidAnonymous = (key_exists('uid-anonymous', $queryParams)) ? $queryParams['uid-anonymous'] : null;
        $message = "How about implementing getPrivilege as a GET method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
        throw new HttpNotImplementedException($request, $message);
    }

    /**
     * GET getWorkGroup
     * Summary: 1件取得する
     * Notes: Workのまとまり (WorkGroup) の情報を1件取得する  このデータが属するWorkGroupへのREAD権限が必要です。
     * Output-Formats: [application/json]
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string $workGroupId WorkGroupのID
     *
     * @return ResponseInterface
     * @throws HttpNotImplementedException to force implementation class to override this method
     */
    public function getWorkGroup(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $workGroupId
    ): ResponseInterface {
        $message = "How about implementing getWorkGroup as a GET method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
        throw new HttpNotImplementedException($request, $message);
    }

    /**
     * GET getWorkGroupList
     * Summary: 複数件取得する
     * Notes: 自身が取得できるWorkのまとまり (WorkGroup) の情報を複数件取得する
     * Output-Formats: [application/json]
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     * @throws HttpNotImplementedException to force implementation class to override this method
     */
    public function getWorkGroupList(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $p = (key_exists('p', $queryParams)) ? $queryParams['p'] : null;
        $limit = (key_exists('limit', $queryParams)) ? $queryParams['limit'] : null;
        $top = (key_exists('top', $queryParams)) ? $queryParams['top'] : null;
        $message = "How about implementing getWorkGroupList as a GET method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
        throw new HttpNotImplementedException($request, $message);
    }

    /**
     * PUT updatePrivilege
     * Summary: 権限を更新する
     * Notes: このWorkGroupに対する自身の権限を更新する。(現在の権限以下の権限のみ設定可能)  管理者の場合は、指定のユーザの権限を追加・更新することも可能。(invite_key_idはNULLになります)
     * Output-Formats: [application/json]
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string $workGroupId WorkGroupのID
     *
     * @return ResponseInterface
     * @throws HttpNotImplementedException to force implementation class to override this method
     */
    public function updatePrivilege(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $workGroupId
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $uid = (key_exists('uid', $queryParams)) ? $queryParams['uid'] : null;
        $uidAnonymous = (key_exists('uid-anonymous', $queryParams)) ? $queryParams['uid-anonymous'] : null;
        $body = $request->getParsedBody();
        $message = "How about implementing updatePrivilege as a PUT method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
        throw new HttpNotImplementedException($request, $message);
    }

    /**
     * PUT updateWorkGroup
     * Summary: 更新する
     * Notes: 既存の「Workのまとまり (WorkGroup)」を更新する  このデータが属するWorkGroupへのWRITE権限が必要です。
     * Output-Formats: [application/json]
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string $workGroupId WorkGroupのID
     *
     * @return ResponseInterface
     * @throws HttpNotImplementedException to force implementation class to override this method
     */
    public function updateWorkGroup(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $workGroupId
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $message = "How about implementing updateWorkGroup as a PUT method in dev_t0r\trvis_backend\api\WorkGroupApi class?";
        throw new HttpNotImplementedException($request, $message);
    }
}
