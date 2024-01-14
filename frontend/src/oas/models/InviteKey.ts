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
 * @interface InviteKey
 */
export interface InviteKey {
    /**
     * 招待キーの説明
     * @type {string}
     * @memberof InviteKey
     */
    description: string;
    /**
     * Invite Key (UUID)
     * @type {string}
     * @memberof InviteKey
     */
    readonly inviteKeysId?: string;
    /**
     * 対応するWorkGroupのID (UUID)
     * @type {string}
     * @memberof InviteKey
     */
    readonly workGroupsId?: string;
    /**
     * 作成日時
     * @type {Date}
     * @memberof InviteKey
     */
    readonly createdAt?: Date;
    /**
     * キーの有効期限 (開始)
     * @type {Date}
     * @memberof InviteKey
     */
    validFrom?: Date;
    /**
     * キーの有効期限 (終了)
     * @type {Date}
     * @memberof InviteKey
     */
    expiresAt?: Date;
    /**
     * キーの使用回数の上限
     * @type {number}
     * @memberof InviteKey
     */
    useLimit?: number;
    /**
     * キーが無効になった日時 (Expireした場合はexpires_atと同じ値)
     * @type {Date}
     * @memberof InviteKey
     */
    readonly disabledAt?: Date;
    /**
     * 招待キーで付与される権限
     * @type {string}
     * @memberof InviteKey
     */
    privilegeType?: InviteKeyPrivilegeTypeEnum;
}


/**
 * @export
 */
export const InviteKeyPrivilegeTypeEnum = {
    Read: 'read',
    Write: 'write',
    Admin: 'admin'
} as const;
export type InviteKeyPrivilegeTypeEnum = typeof InviteKeyPrivilegeTypeEnum[keyof typeof InviteKeyPrivilegeTypeEnum];


/**
 * Check if a given object implements the InviteKey interface.
 */
export function instanceOfInviteKey(value: object): boolean {
    let isInstance = true;
    isInstance = isInstance && "description" in value;

    return isInstance;
}

export function InviteKeyFromJSON(json: any): InviteKey {
    return InviteKeyFromJSONTyped(json, false);
}

export function InviteKeyFromJSONTyped(json: any, ignoreDiscriminator: boolean): InviteKey {
    if ((json === undefined) || (json === null)) {
        return json;
    }
    return {
        
        'description': json['description'],
        'inviteKeysId': !exists(json, 'invite_keys_id') ? undefined : json['invite_keys_id'],
        'workGroupsId': !exists(json, 'work_groups_id') ? undefined : json['work_groups_id'],
        'createdAt': !exists(json, 'created_at') ? undefined : (new Date(json['created_at'])),
        'validFrom': !exists(json, 'valid_from') ? undefined : (new Date(json['valid_from'])),
        'expiresAt': !exists(json, 'expires_at') ? undefined : (new Date(json['expires_at'])),
        'useLimit': !exists(json, 'use_limit') ? undefined : json['use_limit'],
        'disabledAt': !exists(json, 'disabled_at') ? undefined : (new Date(json['disabled_at'])),
        'privilegeType': !exists(json, 'privilege_type') ? undefined : json['privilege_type'],
    };
}

export function InviteKeyToJSON(value?: InviteKey | null): any {
    if (value === undefined) {
        return undefined;
    }
    if (value === null) {
        return null;
    }
    return {
        
        'description': value.description,
        'valid_from': value.validFrom === undefined ? undefined : (value.validFrom.toISOString()),
        'expires_at': value.expiresAt === undefined ? undefined : (value.expiresAt.toISOString()),
        'use_limit': value.useLimit,
        'privilege_type': value.privilegeType,
    };
}

