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
    NOT NULL
  ,

  description
    VARCHAR(255)
    NOT NULL
  ,

  api_key
    VARCHAR(255)
    UNIQUE
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
    UNIQUE
    NOT NULL
  ,

  PRIMARY KEY (
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
