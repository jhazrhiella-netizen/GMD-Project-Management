<?php
// Supabase connection helpers using the REST API (PostgREST + Auth endpoints)
// Configuration - prefer environment variables. Do NOT keep service role keys in source.
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: '');
define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: null); // service_role key for server operations - MUST be provided via env
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: null);

function get_supabase_key($use_anon = false) {
	if ($use_anon) return SUPABASE_ANON_KEY;
	return SUPABASE_KEY;
}

function sb_request($method, $path, $body = null, $use_anon = false, $extra_headers = []) {
	$base = rtrim(SUPABASE_URL, '/');
	if (!$base) return ['error' => 'SUPABASE_URL not configured', 'ok' => false];
	$url = $base . $path;
	$ch = curl_init($url);
	// Respect caller-provided Content-Type in $extra_headers; otherwise default to JSON
	$hasContentType = false;
	foreach ($extra_headers as $h) {
		if (stripos($h, 'content-type:') === 0) { $hasContentType = true; break; }
	}
	$headers = [];
	if (! $hasContentType) $headers[] = 'Content-Type: application/json';
	$key = get_supabase_key($use_anon);
	if (!$key && !$use_anon) {
		return ['error' => 'SUPABASE_KEY (service role) not configured on server', 'ok' => false];
	}
	if ($key) {
		$headers[] = 'apikey: ' . $key;
		$headers[] = 'Authorization: Bearer ' . $key;
	}
	foreach ($extra_headers as $h) $headers[] = $h;
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
	if ($body !== null) {
		// If caller provided a raw string body, use it as-is (useful for form-encoded requests)
		if (is_string($body)) {
			$postBody = $body;
		} else {
			$postBody = json_encode($body);
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
	}
	// Request headers + body together so we can parse response headers
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$resp = curl_exec($ch);
	$info = curl_getinfo($ch);
	if ($resp === false) {
		$err = curl_error($ch);
		curl_close($ch);
		return ['error' => $err, 'ok' => false];
	}
	// separate headers and body
	$header_size = $info['header_size'] ?? 0;
	$header_text = substr($resp, 0, $header_size);
	$body_text = substr($resp, $header_size);
	// parse headers into associative array
	$header_lines = preg_split("/\r?\n/", trim($header_text));
	$parsed_headers = [];
	foreach ($header_lines as $line) {
		if (strpos($line, ':') !== false) {
			list($k, $v) = explode(':', $line, 2);
			$parsed_headers[strtolower(trim($k))] = trim($v);
		}
	}
	curl_close($ch);
	$decoded = json_decode($body_text, true);
	$status = $info['http_code'] ?? 0;
	$ok = ($status >= 200 && $status < 300);
	return ['status' => $status, 'ok' => $ok, 'body' => $decoded, 'raw' => $body_text, 'headers' => $parsed_headers];
}

// Sign in using Supabase Auth (email + password)
function sb_sign_in($email, $password) {
	// Supabase token endpoint accepts grant_type in query for JSON requests.
	$path = '/auth/v1/token?grant_type=password';
	$data = [
		'email' => $email,
		'password' => $password,
	];
	return sb_request('POST', $path, $data, true);
}

// Get profile (assumes a `profiles` table keyed by id = user id)
function sb_get_profile($user_id) {
	$path = '/rest/v1/profiles?id=eq.' . urlencode($user_id) . '&select=*';
	return sb_request('GET', $path, null, false);
}

// Generic helpers for REST table access
function sb_get_table($table, $query = '', $extra_headers = []) {
	$path = '/rest/v1/' . $table . ($query ? '?' . ltrim($query, '?') : '');
	return sb_request('GET', $path, null, false, $extra_headers);
}

function sb_insert_table($table, $data) {
	$path = '/rest/v1/' . $table;
	$headers = ['Prefer: return=representation'];
	return sb_request('POST', $path, $data, false, $headers);
}

function sb_update_table($table, $data, $filter) {
	$path = '/rest/v1/' . $table . '?' . ltrim($filter, '?');
	$headers = ['Prefer: return=representation'];
	return sb_request('PATCH', $path, $data, false, $headers);
}

function sb_delete_table($table, $filter) {
	$path = '/rest/v1/' . $table . '?' . ltrim($filter, '?');
	return sb_request('DELETE', $path, null, false);
}

// Upload a file to Supabase Storage. Returns public URL on success or ['error'=>...] on failure.
function sb_upload_file($bucket, $object_path, $local_file_path, $content_type = null) {
	// encode each path segment of the object path to preserve slashes
	$encodedObject = implode('/', array_map('rawurlencode', explode('/', ltrim($object_path, '/'))));
	$url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . rawurlencode($bucket) . '/' . $encodedObject;
	if (!file_exists($local_file_path)) return ['error' => 'local file not found'];
	// Basic server-side validation
	$maxBytes = 10 * 1024 * 1024; // 10 MB
	$size = filesize($local_file_path);
	if ($size === false) return ['error' => 'cannot determine file size', 'ok' => false];
	if ($size > $maxBytes) return ['error' => 'file too large', 'ok' => false];
	$fh = fopen($local_file_path, 'rb');
	$data = stream_get_contents($fh);
	fclose($fh);

	$allowed = [
		'image/jpeg','image/png','image/gif','image/webp',
		'video/mp4','video/webm',
		'application/pdf','text/plain'
	];
	if ($content_type && !in_array($content_type, $allowed)) {
		return ['error' => 'disallowed file type', 'ok' => false];
	}

	$ch = curl_init($url);
	$headers = [];
	// ensure Content-Type is always set for binary uploads
	if ($content_type) $headers[] = 'Content-Type: ' . $content_type;
	else $headers[] = 'Content-Type: application/octet-stream';
	$key = get_supabase_key(false);
	if (!$key) return ['error' => 'SUPABASE_KEY not configured', 'ok' => false];
	$headers[] = 'apikey: ' . $key;
	$headers[] = 'Authorization: Bearer ' . $key;
	$headers[] = 'x-upsert: true';

	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$resp = curl_exec($ch);
	$info = curl_getinfo($ch);
	if ($resp === false) {
		$err = curl_error($ch);
		curl_close($ch);
		return ['error' => $err];
	}
	curl_close($ch);
	if ($info['http_code'] >= 200 && $info['http_code'] < 300) {
		// If environment declares public storage, return public URL; otherwise return object path
		$public_storage = (getenv('SUPABASE_PUBLIC_STORAGE') === '1');
		if ($public_storage) {
			$public = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . ltrim($object_path, '/');
			return ['ok' => true, 'url' => $public, 'status' => $info['http_code'], 'raw' => $resp];
		}
		return ['ok' => true, 'object_path' => $object_path, 'bucket' => $bucket, 'status' => $info['http_code'], 'raw' => $resp];
	}
	// try to surface any JSON error body for debugging
	$decoded = json_decode($resp, true);
	if (is_array($decoded) && isset($decoded['message'])) {
		error_log('sb_upload_file error body: ' . json_encode($decoded));
		return ['status' => $info['http_code'], 'error' => $decoded['message'], 'raw' => $resp];
	}
	// If Supabase responds with 404 "Bucket not found", attempt to create the bucket (server-side) and retry once.
	if (($info['http_code'] ?? 0) === 404 && is_array($decoded) && isset($decoded['message']) && stripos($decoded['message'], 'bucket') !== false) {
		error_log('sb_upload_file detected missing bucket "' . $bucket . '". Attempting to create it.');
		// create bucket via storage API
		$createPath = '/storage/v1/bucket';
		$createBody = ['name' => $bucket, 'public' => (getenv('SUPABASE_PUBLIC_STORAGE') === '1')];
		$createRes = sb_request('POST', $createPath, $createBody, false);
		if (isset($createRes['ok']) && $createRes['ok']) {
			// retry original upload once
			$ch2 = curl_init($url);
			$headers2 = $headers;
			curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch2, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers2);
			$resp2 = curl_exec($ch2);
			$info2 = curl_getinfo($ch2);
			if ($resp2 === false) {
				$err2 = curl_error($ch2);
				curl_close($ch2);
				return ['error' => $err2];
			}
			curl_close($ch2);
			if (($info2['http_code'] ?? 0) >= 200 && ($info2['http_code'] ?? 0) < 300) {
				if (getenv('SUPABASE_PUBLIC_STORAGE') === '1') {
					$public = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . ltrim($object_path, '/');
					return ['ok' => true, 'url' => $public, 'status' => $info2['http_code'], 'raw' => $resp2];
				}
				return ['ok' => true, 'object_path' => $object_path, 'bucket' => $bucket, 'status' => $info2['http_code'], 'raw' => $resp2];
			}
			// fall through to return the original response below
		} else {
			error_log('sb_upload_file: failed to create bucket: ' . json_encode($createRes));
		}
	}
	error_log('sb_upload_file unexpected response (' . ($info['http_code'] ?? 'unknown') . '): ' . substr($resp,0,1000));
	return ['status' => $info['http_code'] ?? 0, 'raw' => $resp];
}

// Generate a signed URL for a stored object (server-only). Returns ['url'=>...] on success.
function sb_get_signed_url($bucket, $object_path, $expires = 3600) {
	$base = rtrim(SUPABASE_URL, '/');
	if (!$base) return ['error' => 'SUPABASE_URL not configured', 'ok' => false];
	$path = '/storage/v1/object/sign/' . rawurlencode($bucket) . '/' . ltrim($object_path, '/') . '?expires=' . intval($expires);
	$res = sb_request('POST', $path, null, false);
	if (!isset($res['ok']) || !$res['ok']) {
		error_log('sb_get_signed_url failed: ' . json_encode($res));
		return ['error' => 'failed to generate signed url', 'raw' => $res];
	}
	$body = $res['body'] ?? null;
	if (is_array($body)) {
		// try multiple common field names
		foreach (['signedURL','signed_url','signedUrl','signedurl','signed'] as $k) {
			if (isset($body[$k]) && is_string($body[$k]) && strlen($body[$k])>0) return ['url' => $body[$k]];
		}
		// sometimes the API may return the signed url as a top-level string inside body
		foreach ($body as $v) {
			if (is_string($v) && strpos($v, 'http') === 0) return ['url' => $v];
		}
	}
	if (is_string($res['raw'] ?? null) && strlen($res['raw'])>0) {
		$rawTrim = trim($res['raw']);
		if (strpos($rawTrim, 'http') === 0) return ['url' => $rawTrim];
	}
	error_log('sb_get_signed_url: unexpected response body: ' . json_encode($res));
	return ['error' => 'failed to generate signed url', 'raw' => $res];
}

?>