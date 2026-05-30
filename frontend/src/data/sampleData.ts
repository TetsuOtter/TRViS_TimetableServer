// Sample seed data. Ported from the design's data.jsx (TRVIS_DATA).
import type { AppData, Station, TimetableRow, Train } from "../types/model";

// Times stored as "HH:MM:SS". Helper pads "HH:MM" → "HH:MM:00".
const t_ = (s: string): string =>
	s ? (/^\d{1,3}:\d{2}$/.exec(s) ? s + ":00" : s) : "";

type RowOpts = {
	track?: string;
	pass?: boolean;
	opOnly?: boolean;
	last?: boolean;
	bracket?: boolean;
	mm?: number;
	ss?: number;
	remarks?: string;
	showHH?: boolean;
};

const mkRow = (
	id: string,
	stName: string,
	arr: string,
	dep: string,
	opts: RowOpts = {}
): TimetableRow => ({
	id,
	stationName: stName,
	arrive: t_(arr),
	departure: t_(dep),
	trackName: opts.track || "1",
	isPass: opts.pass || false,
	isOperationOnlyStop: opts.opOnly || false,
	isLastStop: opts.last || false,
	hasBracket: opts.bracket || false,
	recordType: "station",
	driveTime_MM: opts.mm || 0,
	driveTime_SS: opts.ss || 0,
	runInLimit: "",
	runOutLimit: "",
	remarks: opts.remarks || "",
	workType: "",
	showHH: opts.showHH,
});

const train1001Rows: TimetableRow[] = [
	mkRow("r01", "東京", "", "09:00", { track: "10", bracket: true }),
	mkRow("r02", "品川", "09:05", "09:06", { track: "2" }),
	mkRow("r03", "川崎", "", "09:12", { pass: true }),
	mkRow("r04", "横浜", "09:22", "09:23", { track: "3" }),
	mkRow("r05", "大船", "09:35", "09:36", { track: "1" }),
	mkRow("r06", "藤沢", "", "09:42", { pass: true }),
	mkRow("r07", "辻堂", "", "09:47", { pass: true }),
	mkRow("r08", "茅ヶ崎", "09:53", "09:54", { track: "2" }),
	mkRow("r09", "平塚", "10:01", "10:02", { track: "1" }),
	mkRow("r10", "大磯", "", "10:08", { pass: true }),
	mkRow("r11", "二宮", "", "10:14", { pass: true }),
	mkRow("r12", "国府津", "10:19", "10:20", { track: "2" }),
	mkRow("r13", "鴨宮", "", "10:24", { pass: true }),
	mkRow("r14", "小田原", "10:30", "", { track: "3" }),
];
const train3501Rows: TimetableRow[] = [
	mkRow("q01", "東京", "", "07:30", { track: "9" }),
	mkRow("q02", "品川", "07:35", "07:36", { track: "1" }),
	mkRow("q03", "川崎", "07:42", "07:43", { track: "1" }),
	mkRow("q04", "横浜", "07:52", "07:53", { track: "4" }),
	mkRow("q05", "大船", "08:04", "08:05", { track: "2" }),
	mkRow("q06", "藤沢", "08:11", "08:12", { track: "1" }),
	mkRow("q07", "辻堂", "08:16", "08:17", { track: "2" }),
	mkRow("q08", "茅ヶ崎", "08:22", "08:23", { track: "1" }),
	mkRow("q09", "平塚", "08:29", "08:30", { track: "2" }),
	mkRow("q10", "大磯", "08:36", "08:37", { track: "1" }),
	mkRow("q11", "二宮", "08:42", "08:43", { track: "1" }),
	mkRow("q12", "国府津", "08:48", "08:49", { track: "2" }),
	mkRow("q13", "鴨宮", "08:53", "08:54", { track: "1" }),
	mkRow("q14", "小田原", "09:00", "", { track: "2" }),
];
const train321Rows: TimetableRow[] = [
	mkRow("p01", "東京", "", "08:00", { track: "8" }),
	mkRow("p02", "品川", "08:06", "08:07", { track: "3" }),
	mkRow("p03", "川崎", "08:14", "08:15", { track: "2", opOnly: true }),
	mkRow("p04", "横浜", "08:25", "08:27", {
		track: "5",
		remarks: "乗降扱いあり",
	}),
	mkRow("p05", "大船", "08:39", "08:40", { track: "1" }),
	mkRow("p06", "茅ヶ崎", "08:58", "08:59", { track: "2" }),
	mkRow("p07", "平塚", "09:05", "", { track: "1" }),
];

const sampleStations: Station[] = [
	{
		id: "s01",
		stationName: "東京",
		fullName: "東京駅",
		longitude_deg: 139.7671,
		latitude_deg: 35.6812,
		onStationDetectRadius_m: 400,
		alwaysShowHH: true,
	},
	{
		id: "s02",
		stationName: "品川",
		fullName: "品川駅",
		longitude_deg: 139.7388,
		latitude_deg: 35.6284,
		onStationDetectRadius_m: 300,
	},
	{
		id: "s03",
		stationName: "川崎",
		fullName: "川崎駅",
		longitude_deg: 139.7016,
		latitude_deg: 35.5308,
		onStationDetectRadius_m: 300,
	},
	{
		id: "s04",
		stationName: "横浜",
		fullName: "横浜駅",
		longitude_deg: 139.6218,
		latitude_deg: 35.4658,
		onStationDetectRadius_m: 350,
		alwaysShowHH: true,
	},
	{
		id: "s05",
		stationName: "大船",
		fullName: "大船駅",
		longitude_deg: 139.5334,
		latitude_deg: 35.3474,
		onStationDetectRadius_m: 300,
	},
	{
		id: "s06",
		stationName: "藤沢",
		fullName: "藤沢駅",
		longitude_deg: 139.4907,
		latitude_deg: 35.3389,
		onStationDetectRadius_m: 300,
	},
	{
		id: "s07",
		stationName: "辻堂",
		fullName: "辻堂駅",
		longitude_deg: 139.4587,
		latitude_deg: 35.3268,
		onStationDetectRadius_m: 250,
	},
	{
		id: "s08",
		stationName: "茅ヶ崎",
		fullName: "茅ヶ崎駅",
		longitude_deg: 139.4025,
		latitude_deg: 35.331,
		onStationDetectRadius_m: 300,
	},
	{
		id: "s09",
		stationName: "平塚",
		fullName: "平塚駅",
		longitude_deg: 139.35,
		latitude_deg: 35.3234,
		onStationDetectRadius_m: 300,
	},
	{
		id: "s10",
		stationName: "大磯",
		fullName: "大磯駅",
		longitude_deg: 139.3136,
		latitude_deg: 35.306,
		onStationDetectRadius_m: 250,
	},
	{
		id: "s11",
		stationName: "二宮",
		fullName: "二宮駅",
		longitude_deg: 139.2557,
		latitude_deg: 35.2977,
		onStationDetectRadius_m: 250,
	},
	{
		id: "s12",
		stationName: "国府津",
		fullName: "国府津駅",
		longitude_deg: 139.209,
		latitude_deg: 35.282,
		onStationDetectRadius_m: 250,
	},
	{
		id: "s13",
		stationName: "鴨宮",
		fullName: "鴨宮駅",
		longitude_deg: 139.1794,
		latitude_deg: 35.2716,
		onStationDetectRadius_m: 250,
	},
	{
		id: "s14",
		stationName: "小田原",
		fullName: "小田原駅",
		longitude_deg: 139.155,
		latitude_deg: 35.2563,
		onStationDetectRadius_m: 350,
		alwaysShowHH: true,
	},
	{
		id: "s15",
		stationName: "逗子",
		fullName: "逗子駅",
		longitude_deg: 139.5765,
		latitude_deg: 35.2947,
		onStationDetectRadius_m: 300,
		alwaysShowHH: true,
	},
	{
		id: "s16",
		stationName: "鎌倉",
		fullName: "鎌倉駅",
		longitude_deg: 139.5522,
		latitude_deg: 35.3197,
		onStationDetectRadius_m: 300,
	},
];

const train1001: Train = {
	id: "t1001",
	trainNumber: "1001M",
	direction: 1,
	destination: "小田原",
	maxSpeed: "120",
	speedType: "特急型 A\n（185系）",
	nominalTractiveCapacity: "12",
	carCount: 15,
	workType: "旅客",
	dayCount: 0,
	isRideOnMoving: false,
	beginRemarks: "発車前 行先表示確認",
	afterRemarks: "",
	remarks: "特急踊り子1号",
	beforeDeparture: "",
	afterArrive: "",
	trainInfo: "",
	nextTrainId: "",
	timetableRows: train1001Rows,
};
const train3501: Train = {
	id: "t3501",
	trainNumber: "3501M",
	direction: 1,
	destination: "小田原",
	maxSpeed: "120",
	speedType: "近郊型",
	nominalTractiveCapacity: "10",
	carCount: 10,
	workType: "旅客",
	dayCount: 0,
	isRideOnMoving: false,
	beginRemarks: "",
	afterRemarks: "",
	remarks: "快速アクティー",
	beforeDeparture: "",
	afterArrive: "",
	trainInfo: "",
	nextTrainId: "",
	timetableRows: train3501Rows,
};
const train321: Train = {
	id: "t321",
	trainNumber: "321M",
	direction: 1,
	destination: "平塚",
	maxSpeed: "100",
	speedType: "近郊型",
	nominalTractiveCapacity: "10",
	carCount: 15,
	workType: "旅客",
	dayCount: 0,
	isRideOnMoving: false,
	beginRemarks: "",
	afterRemarks: "",
	remarks: "普通",
	beforeDeparture: "",
	afterArrive: "",
	trainInfo: "",
	nextTrainId: "",
	timetableRows: train321Rows,
};

export function createInitialData(): AppData {
	return {
		projects: [
			{
				id: "p1",
				name: "東海道本線 ダイヤ2024",
				description: "2024年3月改正ダイヤ",
				workGroups: [
					{
						id: "wg1",
						name: "平日ダイヤ",
						description: "月〜金 運転",
						works: [
							{
								id: "w1",
								name: "2024年3月改正",
								affectDate: "2024-03-16",
								remarks: "春のダイヤ改正",
								trains: [train1001, train3501, train321],
							},
						],
					},
					{
						id: "wg2",
						name: "休日ダイヤ",
						description: "土・日・祝 運転",
						works: [
							{
								id: "w2",
								name: "2024年3月改正（休日）",
								affectDate: "2024-03-16",
								remarks: "",
								trains: [],
							},
						],
					},
				],
			},
			{
				id: "p2",
				name: "横須賀線・総武快速線",
				description: "2024年改正",
				workGroups: [],
			},
		],
		lines: [
			{ id: "l1", name: "東海道本線", description: "東京〜小田原間" },
			{
				id: "l2",
				name: "横須賀線",
				description: "東京〜逗子間（品川・横浜経由）",
			},
		],
		stations: sampleStations,
		stationsOnLine: [
			...sampleStations.slice(0, 14).map((s, i) => ({
				id: `sol${i}`,
				lineId: "l1",
				stationId: s.id,
				location_m: i * 5000,
			})),
			{ id: "sol14", lineId: "l2", stationId: "s01", location_m: 0 },
			{ id: "sol15", lineId: "l2", stationId: "s02", location_m: 6800 },
			{ id: "sol16", lineId: "l2", stationId: "s04", location_m: 28800 },
			{ id: "sol17", lineId: "l2", stationId: "s05", location_m: 51300 },
			{ id: "sol18", lineId: "l2", stationId: "s16", location_m: 57200 },
			{ id: "sol19", lineId: "l2", stationId: "s15", location_m: 61400 },
		],
		stopPatterns: [],
	};
}
