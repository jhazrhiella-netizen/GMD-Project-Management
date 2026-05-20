-- Add missing "type" and "status" columns to messages table
-- Run this against your PostgreSQL / Supabase database (psql or Supabase SQL editor)
ALTER TABLE public.messages
  ADD COLUMN IF NOT EXISTS "type" text DEFAULT 'text';

ALTER TABLE public.messages
  ADD COLUMN IF NOT EXISTS status text DEFAULT 'sent';

-- If "created_at" is missing, you can add it (uncomment):
-- ALTER TABLE public.messages
--   ADD COLUMN IF NOT EXISTS created_at timestamptz DEFAULT now();

-- After running migrations, refresh PostgREST / Supabase schema cache if required (restart PostgREST or redeploy supabase).
