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


import * as runtime from '../runtime';
import type {
  Schema,
  TRViSJsonWorkGroup,
} from '../models/index';
import {
    SchemaFromJSON,
    SchemaToJSON,
    TRViSJsonWorkGroupFromJSON,
    TRViSJsonWorkGroupToJSON,
} from '../models/index';

export interface DumpTimetableRequest {
    workGroupId: string;
}

/**
 * DumpApi - interface
 * 
 * @export
 * @interface DumpApiInterface
 */
export interface DumpApiInterface {
    /**
     * WorkGroupに属するデータをまとめて出力する  指定のWorkGroupへのREAD権限、およびサインインが必要です。 
     * @summary まとめて出力する
     * @param {string} workGroupId WorkGroupのID
     * @param {*} [options] Override http request option.
     * @throws {RequiredError}
     * @memberof DumpApiInterface
     */
    dumpTimetableRaw(requestParameters: DumpTimetableRequest, initOverrides?: RequestInit | runtime.InitOverrideFunction): Promise<runtime.ApiResponse<Array<TRViSJsonWorkGroup>>>;

    /**
     * WorkGroupに属するデータをまとめて出力する  指定のWorkGroupへのREAD権限、およびサインインが必要です。 
     * まとめて出力する
     */
    dumpTimetable(requestParameters: DumpTimetableRequest, initOverrides?: RequestInit | runtime.InitOverrideFunction): Promise<Array<TRViSJsonWorkGroup>>;

}

/**
 * 
 */
export class DumpApi extends runtime.BaseAPI implements DumpApiInterface {

    /**
     * WorkGroupに属するデータをまとめて出力する  指定のWorkGroupへのREAD権限、およびサインインが必要です。 
     * まとめて出力する
     */
    async dumpTimetableRaw(requestParameters: DumpTimetableRequest, initOverrides?: RequestInit | runtime.InitOverrideFunction): Promise<runtime.ApiResponse<Array<TRViSJsonWorkGroup>>> {
        if (requestParameters.workGroupId === null || requestParameters.workGroupId === undefined) {
            throw new runtime.RequiredError('workGroupId','Required parameter requestParameters.workGroupId was null or undefined when calling dumpTimetable.');
        }

        const queryParameters: any = {};

        const headerParameters: runtime.HTTPHeaders = {};

        if (this.configuration && this.configuration.accessToken) {
            const token = this.configuration.accessToken;
            const tokenString = await token("bearerAuth", []);

            if (tokenString) {
                headerParameters["Authorization"] = `Bearer ${tokenString}`;
            }
        }
        const response = await this.request({
            path: `/dump/{workGroupId}`.replace(`{${"workGroupId"}}`, encodeURIComponent(String(requestParameters.workGroupId))),
            method: 'GET',
            headers: headerParameters,
            query: queryParameters,
        }, initOverrides);

        return new runtime.JSONApiResponse(response, (jsonValue) => jsonValue.map(TRViSJsonWorkGroupFromJSON));
    }

    /**
     * WorkGroupに属するデータをまとめて出力する  指定のWorkGroupへのREAD権限、およびサインインが必要です。 
     * まとめて出力する
     */
    async dumpTimetable(requestParameters: DumpTimetableRequest, initOverrides?: RequestInit | runtime.InitOverrideFunction): Promise<Array<TRViSJsonWorkGroup>> {
        const response = await this.dumpTimetableRaw(requestParameters, initOverrides);
        return await response.value();
    }

}
