-- Add missing "created_at" timestamp to messages table
ALTER TABLE public.messages
  ADD COLUMN IF NOT EXISTS created_at timestamptz DEFAULT now();

-- After running, refresh PostgREST / Supabase schema cache if required.
