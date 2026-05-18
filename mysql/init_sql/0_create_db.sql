CREATE TABLE
  api_keys
(
  api_keys_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  api_key
    VARCHAR(255)
    NOT NULL
  ,

  expires_at
    DATETIME
    NOT NULL
  ,

  PRIMARY KEY (
    api_keys_id
  ),

  UNIQUE KEY (
    api_key
  )
);

CREATE TABLE
  projects
(
  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  PRIMARY KEY (
    projects_id
  )
);

CREATE TABLE
  work_groups
(
  work_groups_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4 (所属するProject。未リリースのため常にNOT NULL)'
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  PRIMARY KEY (
    work_groups_id
  ),

  FOREIGN KEY (
    projects_id
  ) REFERENCES
  projects (
    projects_id
  )
);

CREATE TABLE
  colors
(
  colors_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  work_groups_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  red_8bit
    TINYINT UNSIGNED
    NOT NULL
  ,

  green_8bit
    TINYINT UNSIGNED
    NOT NULL
  ,

  blue_8bit
    TINYINT UNSIGNED
    NOT NULL
  ,

  red_real
    DECIMAL(16, 15)
    NOT NULL
  ,

  green_real
    DECIMAL(16, 15)
    NOT NULL
  ,

  blue_real
    DECIMAL(16, 15)
    NOT NULL
  ,

  PRIMARY KEY (
    colors_id
  ),

  FOREIGN KEY (
    work_groups_id
  ) REFERENCES
  work_groups (
    work_groups_id
  )
);

CREATE TABLE
  works
(
  works_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  work_groups_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  affect_date
    DATETIME
  ,

  affix_content_type
    INTEGER
  ,

  affix_file_name
    VARCHAR(255)
  ,

  remarks
    VARCHAR(255)
  ,

  has_e_train_timetable
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  e_train_timetable_content_type
    INTEGER
  ,

  e_train_timetable_file_name
    VARCHAR(255)
  ,

  PRIMARY KEY (
    works_id
  ),

  FOREIGN KEY (
    work_groups_id
  ) REFERENCES
  work_groups (
    work_groups_id
  )
);

CREATE TABLE
  trains
(
  trains_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  works_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  train_number
    VARCHAR(255)
    NOT NULL
  ,

  max_speed
    VARCHAR(255)
  ,

  speed_type
    VARCHAR(1023)
  ,

  nominal_tractive_capacity
    VARCHAR(1023)
  ,

  car_count
    SMALLINT
  ,

  destination
    VARCHAR(255)
  ,

  begin_remarks
    VARCHAR(1023)
  ,

  after_remarks
    VARCHAR(1023)
  ,

  remarks
    TEXT(65535)
  ,

  before_departure
    VARCHAR(1023)
  ,

  after_arrive
    VARCHAR(1023)
  ,

  train_info
    VARCHAR(1023)
  ,

  direction
    TINYINT
    NOT NULL
  ,

  day_count
    TINYINT
    NOT NULL
  ,

  is_ride_on_moving
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  PRIMARY KEY (
    trains_id
  ),

  FOREIGN KEY (
    works_id
  ) REFERENCES
  works (
    works_id
  )
);

CREATE TABLE
  stations
(
  stations_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  work_groups_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  location_km
    DOUBLE PRECISION
    NOT NULL
  ,

  location_lonlat
    POINT
  ,

  on_station_detect_radius_m
    DOUBLE PRECISION
    NOT NULL
  ,

  record_type
    TINYINT
    NOT NULL
  ,

  PRIMARY KEY (
    stations_id
  ),

  FOREIGN KEY (
    work_groups_id
  ) REFERENCES
  work_groups (
    work_groups_id
  )
);

CREATE TABLE
  station_tracks
(
  station_tracks_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  stations_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  run_in_limit
    SMALLINT
  ,

  run_out_limit
    SMALLINT
  ,

  PRIMARY KEY (
    station_tracks_id
  ),

  FOREIGN KEY (
    stations_id
  ) REFERENCES
  stations (
    stations_id
  )
);

CREATE TABLE
  timetable_rows
(
  timetable_rows_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  trains_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  stations_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  station_tracks_id
    BINARY(16)
    COMMENT 'UUID v4'
  ,

  colors_id
    BINARY(16)
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  drive_time_mm
    TINYINT(3) UNSIGNED
  ,

  drive_time_ss
    TINYINT(2) UNSIGNED
  ,

  is_operation_only_stop
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  is_pass
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  has_bracket
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  is_last_stop
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  arrive_time_hh
    TINYINT(2) UNSIGNED
  ,

  arrive_time_mm
    TINYINT(2) UNSIGNED
  ,

  arrive_time_ss
    TINYINT(2) UNSIGNED
  ,

  departure_time_hh
    TINYINT(2) UNSIGNED
  ,

  departure_time_mm
    TINYINT(2) UNSIGNED
  ,

  departure_time_ss
    TINYINT(2) UNSIGNED
  ,

  run_in_limit
    SMALLINT
  ,

  run_out_limit
    SMALLINT
  ,

  remarks
    TEXT(65535)
  ,

  arrive_str
    VARCHAR(255)
  ,

  departure_str
    VARCHAR(255)
  ,

  marker_text
    VARCHAR(16)
  ,

  work_type
    TINYINT UNSIGNED
  ,

  PRIMARY KEY (
    timetable_rows_id
  ),

  FOREIGN KEY (
    trains_id
  ) REFERENCES
  trains (
    trains_id
  ),

  FOREIGN KEY (
    stations_id
  ) REFERENCES
  stations (
    stations_id
  ),

  FOREIGN KEY (
    station_tracks_id
  ) REFERENCES
  station_tracks (
    station_tracks_id
  ),

  FOREIGN KEY (
    colors_id
  ) REFERENCES
  colors (
    colors_id
  )
);

CREATE TABLE
  invite_keys
(
  invite_keys_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  work_groups_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  valid_from
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  expires_at
    DATETIME
  ,

  use_limit
    INTEGER
  ,

  disabled_at
    DATETIME
  ,

  privilege_type
    TINYINT
    NOT NULL
  ,

  PRIMARY KEY (
    invite_keys_id
  ),

  FOREIGN KEY (
    work_groups_id
  ) REFERENCES
  work_groups (
    work_groups_id
  )
);

CREATE TABLE
  work_groups_privileges
(
  uid
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  work_groups_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  invite_keys_id
    BINARY(16)
    COMMENT 'UUID v4'
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  privilege_type
    TINYINT
    NOT NULL
  ,

  PRIMARY KEY (
    uid,
    work_groups_id
  ),

  FOREIGN KEY (
    work_groups_id
  ) REFERENCES
  work_groups (
    work_groups_id
  ),

  FOREIGN KEY (
    invite_keys_id
  ) REFERENCES
  invite_keys (
    invite_keys_id
  )
);

CREATE TABLE
  projects_privileges
(
  uid
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  invite_keys_id
    BINARY(16)
    COMMENT 'UUID v4'
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  privilege_type
    TINYINT
    NOT NULL
  ,

  PRIMARY KEY (
    uid,
    projects_id
  ),

  FOREIGN KEY (
    projects_id
  ) REFERENCES
  projects (
    projects_id
  ),

  FOREIGN KEY (
    invite_keys_id
  ) REFERENCES
  invite_keys (
    invite_keys_id
  )
);

-- NOTE: `lines` は MySQL の予約語 (LOAD DATA ... LINES) のため、テーブル名は
-- `project_lines` / PK `project_lines_id` とする (バッククォート不使用の既存規約を維持)。
-- API/OpenAPI 仕様上は `Line` / `lines_id` のまま。Repo層で別名マッピングする
-- (WorksRepo の `works.affect_date AS AffectDate` と同様のパターン)。
CREATE TABLE
  project_lines
(
  project_lines_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  PRIMARY KEY (
    project_lines_id
  ),

  FOREIGN KEY (
    projects_id
  ) REFERENCES
  projects (
    projects_id
  )
);

CREATE TABLE
  project_stations
(
  project_stations_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
    DEFAULT ''
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  full_name
    VARCHAR(255)
  ,

  location_lonlat
    POINT
  ,

  on_station_detect_radius_m
    DOUBLE PRECISION
  ,

  always_show_hh
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  PRIMARY KEY (
    project_stations_id
  ),

  FOREIGN KEY (
    projects_id
  ) REFERENCES
  projects (
    projects_id
  )
);

CREATE TABLE
  stations_on_line
(
  stations_on_line_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  project_lines_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  project_stations_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
    DEFAULT ''
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  location_m
    DOUBLE PRECISION
    NOT NULL
  ,

  location_lonlat
    POINT
  ,

  track_hidden_by_default
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  PRIMARY KEY (
    stations_on_line_id
  ),

  FOREIGN KEY (
    projects_id
  ) REFERENCES
  projects (
    projects_id
  ),

  FOREIGN KEY (
    project_lines_id
  ) REFERENCES
  project_lines (
    project_lines_id
  ),

  FOREIGN KEY (
    project_stations_id
  ) REFERENCES
  project_stations (
    project_stations_id
  )
);

CREATE TABLE
  stop_patterns
(
  stop_patterns_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  project_lines_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
    DEFAULT ''
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  name
    VARCHAR(255)
    NOT NULL
  ,

  from_project_stations_id
    BINARY(16)
    COMMENT 'UUID v4'
  ,

  to_project_stations_id
    BINARY(16)
    COMMENT 'UUID v4'
  ,

  direction
    TINYINT
    NOT NULL
    DEFAULT 1
  ,

  PRIMARY KEY (
    stop_patterns_id
  ),

  FOREIGN KEY (
    projects_id
  ) REFERENCES
  projects (
    projects_id
  ),

  FOREIGN KEY (
    project_lines_id
  ) REFERENCES
  project_lines (
    project_lines_id
  ),

  FOREIGN KEY (
    from_project_stations_id
  ) REFERENCES
  project_stations (
    project_stations_id
  ),

  FOREIGN KEY (
    to_project_stations_id
  ) REFERENCES
  project_stations (
    project_stations_id
  )
);

CREATE TABLE
  stop_pattern_rows
(
  stop_pattern_rows_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  stop_patterns_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  projects_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  project_stations_id
    BINARY(16)
    NOT NULL
    COMMENT 'UUID v4'
  ,

  description
    VARCHAR(255)
    NOT NULL
    DEFAULT ''
  ,

  owner
    VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
  ,

  created_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
  ,

  updated_at
    DATETIME
    NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
  ,

  deleted_at
    DATETIME
  ,

  sort_key
    INTEGER
    NOT NULL
    DEFAULT 0
  ,

  track_name
    VARCHAR(255)
  ,

  track_hidden
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  is_operation_only_stop
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  is_pass
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  drive_time_mm
    TINYINT(3) UNSIGNED
  ,

  drive_time_ss
    TINYINT(2) UNSIGNED
  ,

  dwell_time_mm
    TINYINT(3) UNSIGNED
  ,

  dwell_time_ss
    TINYINT(2) UNSIGNED
  ,

  show_arrive
    BOOLEAN
    NOT NULL
    DEFAULT TRUE
  ,

  show_departure
    BOOLEAN
    NOT NULL
    DEFAULT TRUE
  ,

  arrive_str
    VARCHAR(255)
  ,

  departure_str
    VARCHAR(255)
  ,

  run_in_limit
    SMALLINT
  ,

  run_out_limit
    SMALLINT
  ,

  remarks
    TEXT(65535)
  ,

  always_show_hh
    BOOLEAN
    NOT NULL
    DEFAULT FALSE
  ,

  PRIMARY KEY (
    stop_pattern_rows_id
  ),

  FOREIGN KEY (
    stop_patterns_id
  ) REFERENCES
  stop_patterns (
    stop_patterns_id
  ),

  FOREIGN KEY (
    projects_id
  ) REFERENCES
  projects (
    projects_id
  ),

  FOREIGN KEY (
    project_stations_id
  ) REFERENCES
  project_stations (
    project_stations_id
  )
);
