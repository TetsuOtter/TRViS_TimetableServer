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
 * Train
 *
 * @package dev_t0r\trvis_backend\model
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class Train extends BaseModel
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
  "title" : "Train",
  "required" : [ "day_count", "description", "direction", "train_number" ],
  "type" : "object",
  "properties" : {
    "trains_id" : {
      "type" : "string",
      "description" : "TrainのID (UUID)",
      "format" : "uuid",
      "readOnly" : true
    },
    "works_id" : {
      "type" : "string",
      "description" : "WorkのID (UUID)",
      "format" : "uuid",
      "readOnly" : true
    },
    "created_at" : {
      "type" : "string",
      "description" : "作成日時",
      "format" : "date-time",
      "readOnly" : true
    },
    "description" : {
      "type" : "string",
      "description" : "Train (列車) の説明",
      "example" : "Train (列車) の説明が入ります"
    },
    "train_number" : {
      "type" : "string",
      "description" : "列車番号",
      "example" : "試9999M"
    },
    "max_speed" : {
      "type" : "string",
      "description" : "最高速度 (km/h)",
      "example" : "130\nシク〜 60\n"
    },
    "speed_type" : {
      "type" : "string",
      "description" : "速度種別",
      "example" : "停電A9\nシク〜 特定\n"
    },
    "nominal_tractive_capacity" : {
      "type" : "string",
      "description" : "けん引定数",
      "example" : "999系\n9M1T\n"
    },
    "car_count" : {
      "type" : "integer",
      "description" : "編成両数 (0以下で非表示)",
      "example" : 10
    },
    "destination" : {
      "type" : "string",
      "description" : "行先",
      "example" : "東  京"
    },
    "begin_remarks" : {
      "type" : "string",
      "description" : "乗車前の注意事項 (「乗継」など)",
      "example" : "(乗継)"
    },
    "after_remarks" : {
      "type" : "string",
      "description" : "降車後の注意事項 (「乗継」など)",
      "example" : "(乗継)"
    },
    "remarks" : {
      "type" : "string",
      "description" : "注意事項",
      "example" : "XXXX ~ YYYY 徐行 30km/h\nAAAA ~ BBBB 車掌省略\n"
    },
    "before_departure" : {
      "type" : "string",
      "description" : "発前",
      "example" : "転線 5分          転線"
    },
    "after_arrive" : {
      "type" : "string",
      "description" : "着後",
      "example" : "転線 5分          転線"
    },
    "train_info" : {
      "type" : "string",
      "description" : "列車に関する情報",
      "example" : "<div style=\"color: red\">車掌省略</div>"
    },
    "direction" : {
      "type" : "integer",
      "description" : "進行方向 (0~1: 下り, -1: 上り)",
      "example" : 1
    },
    "day_count" : {
      "minimum" : 0,
      "type" : "integer",
      "description" : "仕業の初日からの経過日数 (0で初日/日勤、1で明け)",
      "example" : 1
    },
    "is_ride_on_moving" : {
      "type" : "boolean",
      "description" : "添乗での移動かどうか",
      "example" : false
    }
  }
}
SCHEMA;
}
