-- =============================================================================
-- Migration 0002: Consolidate stations onto the project-rooted model
-- =============================================================================
-- Purpose:
--   Abolish the work-group-rooted `stations` table and promote the
--   project-rooted `project_stations` to be THE `stations` table. Re-root
--   `colors` and `station_tracks` under Project as well. After this migration
--   the schema matches `init_sql/0_create_db.sql` (the new baseline).
--
--   Concretely:
--     * `colors.work_groups_id` -> `projects_id` (remapped via work_groups,
--       lossless), FK now -> projects.
--     * work-group `stations` table is DROPPED.
--     * `project_stations` is RENAMED to `stations`; PK
--       `project_stations_id` -> `stations_id`; `location_km` + `record_type`
--       columns added (consumed by the TRViS-JSON dump; default 0 = km 0 /
--       record_type normal).
--     * `station_tracks` and `timetable_rows` re-point their `stations_id` FK
--       to the new (project-rooted) `stations`.
--     * `stations_on_line` / `stop_patterns` / `stop_pattern_rows` keep their
--       `project_stations_id` / `from_*` / `to_*` COLUMN names but their FKs
--       now reference `stations(stations_id)`.
--
-- GREENFIELD ASSUMPTION — DATA IS DISCARDED, NOT MIGRATED:
--   This is a destructive consolidation. The work-group `stations` IDs cannot
--   be remapped to project-station IDs (no mapping exists), so any
--   `timetable_rows` / `station_tracks` that referenced the abolished
--   work-group `stations` are DELETED here, not migrated. This is by design:
--   the frontend already addressed stations as project_stations, so timetable
--   rows could not have referenced valid work-group stations in practice.
--   If you have production data you must preserve, DO NOT run this file —
--   write a bespoke data-mapping migration instead.
--
-- Scope:
--   `init_sql/0_create_db.sql` runs only on a fresh DB. This file is the
--   hand-applied delta for ALREADY-RUNNING databases. Apply after 0001.
--
-- How to apply:
--   mysql -h <host> -u <user> -p <database> \
--     < mysql/migrations/0002_consolidate_stations_to_project_rooted.sql
--
-- FK constraint names:
--   The DROP FOREIGN KEY statements below use MySQL's auto-generated
--   `<table>_ibfk_<n>` names produced by the init_sql CREATE TABLE statements
--   (n = order the FK appears in the table definition). If your DB was created
--   differently and the names differ, look them up with:
--     SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
--       FROM information_schema.KEY_COLUMN_USAGE
--      WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;
--   and adjust accordingly.
--
-- Idempotency caveat:
--   Not idempotent. Re-running on an already-migrated DB fails (e.g. unknown
--   column `work_groups_id`, or unknown table `project_stations`). A failure
--   on re-run is a SAFE signal that this migration has already been applied.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. Discard data rooted in the abolished work-group `stations`.
--    (No-op on a greenfield DB; see GREENFIELD ASSUMPTION above.)
-- ---------------------------------------------------------------------------
DELETE FROM timetable_rows;
DELETE FROM station_tracks;

-- ---------------------------------------------------------------------------
-- 2. Drop the FKs that point at tables we are about to change/drop.
-- ---------------------------------------------------------------------------
ALTER TABLE colors            DROP FOREIGN KEY colors_ibfk_1;            -- -> work_groups
ALTER TABLE station_tracks    DROP FOREIGN KEY station_tracks_ibfk_1;   -- -> stations (WG)
ALTER TABLE timetable_rows    DROP FOREIGN KEY timetable_rows_ibfk_2;   -- stations_id -> stations (WG)
ALTER TABLE timetable_rows    DROP FOREIGN KEY timetable_rows_ibfk_4;   -- colors_id -> colors
ALTER TABLE stations_on_line  DROP FOREIGN KEY stations_on_line_ibfk_3; -- -> project_stations
ALTER TABLE stop_patterns     DROP FOREIGN KEY stop_patterns_ibfk_3;    -- from_project_stations_id
ALTER TABLE stop_patterns     DROP FOREIGN KEY stop_patterns_ibfk_4;    -- to_project_stations_id
ALTER TABLE stop_pattern_rows DROP FOREIGN KEY stop_pattern_rows_ibfk_3; -- -> project_stations

-- ---------------------------------------------------------------------------
-- 3. Re-root `colors` under Project (remap value via work_groups, lossless).
-- ---------------------------------------------------------------------------
ALTER TABLE colors ADD COLUMN projects_id BINARY(16) NULL COMMENT 'UUID v4' AFTER colors_id;
UPDATE colors c
  JOIN work_groups wg ON wg.work_groups_id = c.work_groups_id
  SET c.projects_id = wg.projects_id;
ALTER TABLE colors MODIFY COLUMN projects_id BINARY(16) NOT NULL COMMENT 'UUID v4';
ALTER TABLE colors DROP COLUMN work_groups_id;
ALTER TABLE colors ADD FOREIGN KEY (projects_id) REFERENCES projects (projects_id);

-- ---------------------------------------------------------------------------
-- 4. Drop the work-group `stations` table; promote `project_stations`.
-- ---------------------------------------------------------------------------
DROP TABLE stations;

ALTER TABLE project_stations
  RENAME TO stations;
ALTER TABLE stations
  CHANGE COLUMN project_stations_id stations_id BINARY(16) NOT NULL COMMENT 'UUID v4';
ALTER TABLE stations
  ADD COLUMN location_km DOUBLE PRECISION NOT NULL DEFAULT 0 AFTER name,
  ADD COLUMN record_type TINYINT NOT NULL DEFAULT 0 AFTER on_station_detect_radius_m;

-- ---------------------------------------------------------------------------
-- 5. Re-add the retargeted FKs against the new (project-rooted) `stations`.
-- ---------------------------------------------------------------------------
ALTER TABLE station_tracks    ADD FOREIGN KEY (stations_id)              REFERENCES stations (stations_id);
ALTER TABLE timetable_rows    ADD FOREIGN KEY (stations_id)              REFERENCES stations (stations_id);
ALTER TABLE timetable_rows    ADD FOREIGN KEY (colors_id)                REFERENCES colors (colors_id);
ALTER TABLE stations_on_line  ADD FOREIGN KEY (project_stations_id)      REFERENCES stations (stations_id);
ALTER TABLE stop_patterns     ADD FOREIGN KEY (from_project_stations_id) REFERENCES stations (stations_id);
ALTER TABLE stop_patterns     ADD FOREIGN KEY (to_project_stations_id)   REFERENCES stations (stations_id);
ALTER TABLE stop_pattern_rows ADD FOREIGN KEY (project_stations_id)      REFERENCES stations (stations_id);

SET FOREIGN_KEY_CHECKS = 1;
