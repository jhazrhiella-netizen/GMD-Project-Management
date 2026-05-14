-- Migration: add address code and name columns to projects
-- Run this SQL against your Supabase/Postgres database.

ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS client_region_code text,
  ADD COLUMN IF NOT EXISTS client_province_code text,
  ADD COLUMN IF NOT EXISTS client_city_code text,
  ADD COLUMN IF NOT EXISTS client_barangay_code text,
  ADD COLUMN IF NOT EXISTS client_region_name text,
  ADD COLUMN IF NOT EXISTS client_province_name text,
  ADD COLUMN IF NOT EXISTS client_city_name text,
  ADD COLUMN IF NOT EXISTS client_barangay_name text;

-- Optional: create indexes for faster queries by code
CREATE INDEX IF NOT EXISTS idx_projects_client_province_code ON projects (client_province_code);
CREATE INDEX IF NOT EXISTS idx_projects_client_city_code ON projects (client_city_code);
CREATE INDEX IF NOT EXISTS idx_projects_client_barangay_code ON projects (client_barangay_code);
