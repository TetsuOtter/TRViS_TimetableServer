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
 */
namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;

/**
 * TRViSJsonTimetableRow
 *
 * @package dev_t0r\trvis_backend\model
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class TRViSJsonTimetableRow extends BaseModel
{
    /**
     * @var string Models namespace.
     * Can be required for data deserialization when model contains referenced schemas.
     */
    protected const MODELS_NAMESPACE = '\dev_t0r\trvis_backend\model';

    /**
     * @var string Constant with OAS schema of current class.
     * Should be overwritten by inherited class.
     */
    protected const MODEL_SCHEMA = <<<'SCHEMA'
{
  "title" : "TRViS_TimetableRow",
  "required" : [ "Location_m", "StationName" ],
  "type" : "object",
  "properties" : {
    "StationName" : {
      "maxLength" : 4,
      "minLength" : 1,
      "type" : "string",
      "description" : "駅名 (4文字まで)",
      "example" : "東京"
    },
    "Location_m" : {
      "type" : "number",
      "description" : "駅の位置 (m)",
      "format" : "double",
      "example" : 0
    },
    "Longitude_deg" : {
      "maximum" : 180,
      "minimum" : -180,
      "type" : "number",
      "description" : "駅の経度 (度)",
      "format" : "double",
      "nullable" : true,
      "example" : 139.766944
    },
    "Latitude_deg" : {
      "maximum" : 90,
      "minimum" : -90,
      "type" : "number",
      "description" : "駅の緯度 (度)",
      "format" : "double",
      "nullable" : true,
      "example" : 35.680833
    },
    "OnStationDetectRadius_m" : {
      "type" : "number",
      "description" : "その駅にいるかどうかを判定する円の半径 (m)",
      "format" : "double",
      "nullable" : true,
      "example" : 123.45
    },
    "FullName" : {
      "type" : "string",
      "description" : "駅のフルネーム",
      "nullable" : true,
      "example" : "東京駅"
    },
    "RecordType" : {
      "type" : "integer",
      "description" : "駅の種類",
      "example" : 0
    },
    "TrackName" : {
      "type" : "string",
      "description" : "駅の番線名",
      "nullable" : true,
      "example" : "上1"
    },
    "DriveTime_MM" : {
      "maximum" : 99,
      "minimum" : 0,
      "type" : "integer",
      "description" : "駅間運転時間 (分)",
      "example" : 3
    },
    "DriveTime_SS" : {
      "maximum" : 59,
      "minimum" : 0,
      "type" : "integer",
      "description" : "駅間運転時間 (秒)",
      "example" : 15
    },
    "IsOperationOnlyStop" : {
      "type" : "boolean",
      "description" : "運転停車かどうか",
      "example" : false
    },
    "IsPass" : {
      "type" : "boolean",
      "description" : "通過駅かどうか",
      "example" : false
    },
    "HasBracket" : {
      "type" : "boolean",
      "description" : "到着時刻に括弧を付けるかどうか",
      "example" : false
    },
    "IsLastStop" : {
      "type" : "boolean",
      "description" : "終着駅かどうか",
      "example" : false
    },
    "Arrive" : {
      "pattern" : "^(.*|[0-9]{0,2}:[0-9]{0,2}:[0-9]{0,2})$",
      "type" : "string",
      "description" : "到着時刻",
      "example" : "12:34:56"
    },
    "Departure" : {
      "pattern" : "^(.*|[0-9]{0,2}:[0-9]{0,2}:[0-9]{0,2})$",
      "type" : "string",
      "description" : "出発時刻",
      "example" : "::56"
    },
    "RunInLimit" : {
      "maximum" : 1000,
      "exclusiveMaximum" : true,
      "minimum" : 0,
      "type" : "integer",
      "description" : "進入制限 (km/h)",
      "example" : 15
    },
    "RunOutLimit" : {
      "maximum" : 1000,
      "exclusiveMaximum" : true,
      "minimum" : 0,
      "type" : "integer",
      "description" : "進出制限 (km/h)",
      "example" : 15
    },
    "Remarks" : {
      "type" : "string",
      "description" : "注意事項",
      "example" : "通過設定"
    },
    "MarkerColor" : {
      "pattern" : "^[0-9a-fA-F]{6}$",
      "type" : "string",
      "description" : "マーカーの色",
      "example" : "ff0000"
    },
    "MarkerText" : {
      "type" : "string",
      "description" : "マーカー部分に表示する文字列",
      "example" : "合図"
    },
    "WorkType" : {
      "type" : "integer",
      "description" : "作業種別 (実装準備中)"
    }
  }
}
SCHEMA;
}
