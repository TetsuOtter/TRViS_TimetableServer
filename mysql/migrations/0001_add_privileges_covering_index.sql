-- =============================================================================
-- Migration 0001: Add covering indexes to privileges tables (M11)
-- =============================================================================
-- Purpose:
--   Adds a covering index `priv_lookup_covering_idx` to both
--   `projects_privileges` and `work_groups_privileges` for M11 remediation.
--   The index column order (uid, deleted_at, <entity>_id, privilege_type)
--   makes uid+deleted_at an equality/NULL prefix, allows <entity>_id GROUP BY
--   to run filesort-free, and lets privilege_type MAX use a loose index scan;
--   all referenced columns are covered (no table lookup required).
--
-- Scope:
--   `init_sql/0_create_db.sql` is executed only on a fresh DB (first container
--   start). This file is the hand-applied delta for ALREADY-RUNNING databases.
--
-- How to apply:
--   mysql -h <host> -u <user> -p <database> \
--     < mysql/migrations/0001_add_privileges_covering_index.sql
--
--   Files are applied in numeric-prefix order (0001 before 0002, etc.).
--
-- Idempotency caveat:
--   MySQL 8.0 does not support `ADD INDEX IF NOT EXISTS`. Re-running this
--   migration on an already-migrated database will fail with:
--     ERROR 1061 (42000): Duplicate key name 'priv_lookup_covering_idx'
--   This is a SAFE signal that the migration has already been applied.
--   Verify current state with:
--     SHOW INDEX FROM projects_privileges;
--     SHOW INDEX FROM work_groups_privileges;
-- =============================================================================

ALTER TABLE projects_privileges
  ADD KEY priv_lookup_covering_idx (uid, deleted_at, projects_id, privilege_type);

ALTER TABLE work_groups_privileges
  ADD KEY priv_lookup_covering_idx (uid, deleted_at, work_groups_id, privilege_type);
