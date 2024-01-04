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
  work_groups
(
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

  name
    VARCHAR(255)
    NOT NULL
  ,

  PRIMARY KEY (
    work_groups_id
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

  name
    VARCHAR(255)
    NOT NULL
  ,

  affect_date
    DATETIME
    NOT NULL
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

  arrive_hh
    TINYINT(2) UNSIGNED
  ,

  arrive_mm
    TINYINT(2) UNSIGNED
  ,

  arrive_ss
    TINYINT(2) UNSIGNED
  ,

  departure_hh
    TINYINT(2) UNSIGNED
  ,

  departure_mm
    TINYINT(2) UNSIGNED
  ,

  departure_ss
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
