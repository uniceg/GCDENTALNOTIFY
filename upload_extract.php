<?php
// UPLOAD IMAGE TEMPORARILY
if (!isset($_FILES['idImage'])) {
    echo json_encode(['error' => 'No file uploaded.']);
    exit;
}

$imageTmp = $_FILES['idImage']['tmp_name'];
$imageData = base64_encode(file_get_contents($imageTmp));

// CLOUD VISION API REQUEST
$apiKey = 'AIzaSyAe8-zOfW4ZLKDw7O3rvffej1i4i7MWu3Y';
$url = "https://vision.googleapis.com/v1/images:annotate?key=$apiKey";

$requestBody = json_encode([
    "requests" => [[
        "image" => ["content" => $imageData],
        "features" => [["type" => "TEXT_DETECTION"]]
    ]]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// PARSE OCR RESULT
$text = $result['responses'][0]['fullTextAnnotation']['text'] ?? '';
// Use regex or string functions to extract specific values
$firstName = '';
$lastName = '';
$middleInitial = '';
$address = '';

if ($text) {
    // You can tailor this to your ID's format
    preg_match('/Name:\s*(\w+)\s+(\w+)\s+(\w)/', $text, $matches);
    if ($matches) {
        $lastName = $matches[1];
        $firstName = $matches[2];
        $middleInitial = $matches[3];
    }

    preg_match('/Address:\s*(.+)/', $text, $addressMatch);
    if ($addressMatch) {
        $address = trim($addressMatch[1]);
    }
}

echo json_encode([
    "firstName" => $firstName,
    "lastName" => $lastName,
    "middleInitial" => $middleInitial,
    "address" => $address
]);
