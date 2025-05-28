<?php
// Load your service account file
$serviceAccount = file_get_contents("firebase-service-account.json");
$jwt = json_decode($serviceAccount, true);

// Your server FCM endpoint
$url = 'https://fcm.googleapis.com/v1/projects/' . $jwt['project_id'] . '/messages:send';

// The access token (we'll get this below)
$accessToken = getAccessToken($serviceAccount);

// FCM message body
$notification = [
    'message' => [
        'token' => 'USER_FCM_TOKEN_HERE', // Replace with the device FCM token
        'notification' => [
            'title' => 'Hello from PHP',
            'body' => 'This is a test message sent from localhost!',
        ]
    ]
];

// Send POST request
$headers = [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));

$response = curl_exec($ch);
curl_close($ch);

echo $response;

// Get Access Token Function
function getAccessToken($serviceAccount) {
    $jwt = new \Firebase\JWT\JWT;
    $now = time();
    $token = [
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $serviceAccount['token_uri'],
        'iat' => $now,
        'exp' => $now + 3600,
    ];

    $privateKey = $serviceAccount['private_key'];
    $jwtEncoded = \Firebase\JWT\JWT::encode($token, $privateKey, 'RS256');

    // Exchange JWT for access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serviceAccount['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwtEncoded,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return $data['access_token'];
}
?>
