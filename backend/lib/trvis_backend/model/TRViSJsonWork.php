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
 * TRViSJsonWork
 *
 * @package dev_t0r\trvis_backend\model
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class TRViSJsonWork extends BaseModel
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
  "title" : "TRViS_Work",
  "required" : [ "Name", "Trains" ],
  "type" : "object",
  "properties" : {
    "Name" : {
      "type" : "string",
      "description" : "Workの名前",
      "example" : "123行路"
    },
    "AffectDate" : {
      "type" : "string",
      "description" : "この仕業の施行日",
      "format" : "date",
      "nullable" : true,
      "example" : "2020-01-01"
    },
    "AffixContentType" : {
      "type" : "integer",
      "description" : "AffixContentのContent-Type",
      "nullable" : true,
      "example" : 0
    },
    "AffixContent" : {
      "type" : "string",
      "description" : "行路添付に表示する内容",
      "nullable" : true,
      "example" : ""
    },
    "Remarks" : {
      "type" : "string",
      "description" : "仕業の注意事項に表示する内容",
      "nullable" : true,
      "example" : "2023年1月1日 12時34分56秒作成"
    },
    "HasETrainTimetable" : {
      "type" : "boolean",
      "description" : "この行路にE電時刻表が存在するかどうか",
      "example" : true
    },
    "ETrainTimetableContentType" : {
      "type" : "integer",
      "description" : "E電時刻表のContent-Type",
      "nullable" : true,
      "example" : 0
    },
    "ETrainTimetableContent" : {
      "type" : "string",
      "description" : "E電時刻表に表示する内容",
      "nullable" : true,
      "example" : ""
    },
    "Trains" : {
      "type" : "array",
      "description" : "このWorkに属するTrainの配列",
      "items" : {
        "$ref" : "#/components/schemas/TRViS_json_Train"
      }
    }
  }
}
SCHEMA;
}
