// @ts-nocheck
/* tslint:disable */
/* eslint-disable */
/**
 * TRViS用 時刻表管理用API
 * No description provided (generated by Openapi Generator https://github.com/openapitools/openapi-generator)
 *
 * The version of the OpenAPI document: 1.0.0
 * 
 *
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

import { exists, mapValues } from '../runtime';
/**
 * 
 * @export
 * @interface Work
 */
export interface Work {
    /**
     * Workの説明
     * @type {string}
     * @memberof Work
     */
    description: string;
    /**
     * Workの名前
     * @type {string}
     * @memberof Work
     */
    name: string;
    /**
     * WorkのID (UUID)
     * @type {string}
     * @memberof Work
     */
    readonly worksId?: string;
    /**
     * WorkGroupのID (UUID)
     * @type {string}
     * @memberof Work
     */
    readonly workGroupsId?: string;
    /**
     * 作成日時
     * @type {Date}
     * @memberof Work
     */
    readonly createdAt?: Date;
    /**
     * 発効日
     * @type {Date}
     * @memberof Work
     */
    affectDate?: Date;
    /**
     * affix_content のファイル形式
     * @type {string}
     * @memberof Work
     */
    affixContentType?: WorkAffixContentTypeEnum;
    /**
     * 「行路添付」に表示させる内容
     * 
     * ContentTypeに従って、Plain Text、URI、Binary のいずれかを使用する
     * 
     * @type {string}
     * @memberof Work
     */
    affixContent?: string;
    /**
     * 「注意事項」に表示させる内容
     * @type {string}
     * @memberof Work
     */
    remarks?: string;
    /**
     * 「E電時刻表」を表示させるかどうか
     * @type {boolean}
     * @memberof Work
     */
    hasETrainTimetable?: boolean;
    /**
     * e_train_timetable_content のファイル形式
     * @type {string}
     * @memberof Work
     */
    eTrainTimetableContentType?: WorkETrainTimetableContentTypeEnum;
    /**
     * 「E電時刻表」に表示させる内容
     * 
     * ContentTypeに従って、Plain Text、URI、Binary のいずれかを使用する
     * 
     * @type {string}
     * @memberof Work
     */
    eTrainTimetableContent?: string;
}


/**
 * @export
 */
export const WorkAffixContentTypeEnum = {
    Text: 'text',
    Uri: 'URI',
    Png: 'PNG',
    Pdf: 'PDF',
    Jpg: 'JPG'
} as const;
export type WorkAffixContentTypeEnum = typeof WorkAffixContentTypeEnum[keyof typeof WorkAffixContentTypeEnum];

/**
 * @export
 */
export const WorkETrainTimetableContentTypeEnum = {
    Text: 'text',
    Uri: 'URI',
    Png: 'PNG',
    Pdf: 'PDF',
    Jpg: 'JPG'
} as const;
export type WorkETrainTimetableContentTypeEnum = typeof WorkETrainTimetableContentTypeEnum[keyof typeof WorkETrainTimetableContentTypeEnum];


/**
 * Check if a given object implements the Work interface.
 */
export function instanceOfWork(value: object): boolean {
    let isInstance = true;
    isInstance = isInstance && "description" in value;
    isInstance = isInstance && "name" in value;

    return isInstance;
}

export function WorkFromJSON(json: any): Work {
    return WorkFromJSONTyped(json, false);
}

export function WorkFromJSONTyped(json: any, ignoreDiscriminator: boolean): Work {
    if ((json === undefined) || (json === null)) {
        return json;
    }
    return {
        
        'description': json['description'],
        'name': json['name'],
        'worksId': !exists(json, 'works_id') ? undefined : json['works_id'],
        'workGroupsId': !exists(json, 'work_groups_id') ? undefined : json['work_groups_id'],
        'createdAt': !exists(json, 'created_at') ? undefined : (new Date(json['created_at'])),
        'affectDate': !exists(json, 'affect_date') ? undefined : (new Date(json['affect_date'])),
        'affixContentType': !exists(json, 'affix_content_type') ? undefined : json['affix_content_type'],
        'affixContent': !exists(json, 'affix_content') ? undefined : json['affix_content'],
        'remarks': !exists(json, 'remarks') ? undefined : json['remarks'],
        'hasETrainTimetable': !exists(json, 'has_e_train_timetable') ? undefined : json['has_e_train_timetable'],
        'eTrainTimetableContentType': !exists(json, 'e_train_timetable_content_type') ? undefined : json['e_train_timetable_content_type'],
        'eTrainTimetableContent': !exists(json, 'e_train_timetable_content') ? undefined : json['e_train_timetable_content'],
    };
}

export function WorkToJSON(value?: Work | null): any {
    if (value === undefined) {
        return undefined;
    }
    if (value === null) {
        return null;
    }
    return {
        
        'description': value.description,
        'name': value.name,
        'affect_date': value.affectDate === undefined ? undefined : (value.affectDate.toISOString().substring(0,10)),
        'affix_content_type': value.affixContentType,
        'affix_content': value.affixContent,
        'remarks': value.remarks,
        'has_e_train_timetable': value.hasETrainTimetable,
        'e_train_timetable_content_type': value.eTrainTimetableContentType,
        'e_train_timetable_content': value.eTrainTimetableContent,
    };
}

