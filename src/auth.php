<?php
// Authentication helpers that use supabase-connection.php
require_once __DIR__ . '/../supabase-connection.php';

function auth_sign_in($email, $password) {
    $res = sb_sign_in($email, $password);
    if (isset($res['error'])) return ['error' => $res['error']];
    if (!empty($res['body']['access_token'])) {
        $user = $res['body']['user'] ?? null;
        $access_token = $res['body']['access_token'];
        if ($user && isset($user['id'])) {
            $profile = sb_get_profile($user['id']);
            // profile.body is an array of profiles
            $profileData = null;
            if (isset($profile['body']) && is_array($profile['body']) && count($profile['body'])>0) {
                $profileData = $profile['body'][0];
            }
            // construct session-safe user object (do not store access_token in session)
            $sessionUser = [
                'id' => $user['id'],
                'email' => $user['email'] ?? $email,
                'role' => $profileData['role'] ?? 'admin',
                'profile' => $profileData
            ];
            return ['user' => $sessionUser];
        }
    }
    return ['error' => 'Invalid credentials'];
}

?>
