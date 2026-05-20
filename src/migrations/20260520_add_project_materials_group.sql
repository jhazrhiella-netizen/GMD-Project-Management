-- Migration: add group_id to project_materials
-- Adds an integer group identifier so materials can be batched per project
BEGIN;

ALTER TABLE IF EXISTS project_materials
  ADD COLUMN IF NOT EXISTS group_id integer NOT NULL DEFAULT 1;

CREATE INDEX IF NOT EXISTS idx_project_materials_project_group
  ON project_materials (project_id, group_id);

-- Ensure existing rows have a non-null group_id (default already set to 1)
UPDATE project_materials SET group_id = 1 WHERE group_id IS NULL;

COMMIT;

-- Note: If you prefer UUID batch ids, replace integer type with uuid and set defaults accordingly.
