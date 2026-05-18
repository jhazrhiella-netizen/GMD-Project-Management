InfinityFree deployment notes

Short checklist
- PHP version: use PHP 7.3 or newer (array options in `session_set_cookie_params` require 7.3+).
- cURL: required and must be enabled (used by `supabase-connection.php`).
- Outbound HTTPS: required to reach Supabase REST endpoints.
- Keep `SUPABASE_KEY` (service role) secret — prefer storing outside public webroot or using server-side secrets.

Quick deploy steps
1. In Supabase:
   - Create a project and run the SQL from `src/migrations/current_database.sql` and `src/migrations/20260514_add_project_address_codes.sql` using the Supabase SQL editor.
   - Configure Auth settings (allowed redirect URLs) to include your InfinityFree domain if using client-side redirects.
2. On InfinityFree control panel:
   - Create an account and a site; set PHP version to 7.3+.
   - Upload repository files into the site `htdocs` (via FTP or file manager). Root of this repo should map to `htdocs` so that `index.php` is reachable.
3. Environment/configuration:
   - Copy `.env.example` to `.env` at project root and fill values: `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_KEY`, and other flags.
   - Protect `.env` (see `.htaccess.example`). InfinityFree doesn't provide persistent environment variables on the free plan, so `.env` or a PHP config file is the only practical option.
4. Verify runtime:
   - Visit `/check_env.php?token=...` (if you set `CHECK_ENV_TOKEN`) locally to validate keys.
   - Test login via `/src/login.php`.

Security notes and caveats
- Shared hosting risk: storing `SUPABASE_KEY` on a free shared host is a security risk because other site admins on the same server may be able to access files if misconfigured. Prefer hosting on a provider that supports environment variables or server-side secrets.
- If you must use InfinityFree, keep the service role key restricted (rotate regularly) and limit operations on the server that require it. Consider removing direct usage of `SUPABASE_KEY` for user-facing endpoints and rely on Supabase policies and anon key where possible.

If you want, I can:
- Add a secure `config.local.php` loader and `.htaccess` rules to deny access to `.env` (example changes), or
- Patch `supabase-connection.php` to avoid using `SUPABASE_KEY` for operations that can work with anon or user's token.
