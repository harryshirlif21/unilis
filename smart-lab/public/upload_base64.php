<?php
/**
 * Base64 Image Upload Handler for Quill Paste
 * Accepts a base64-encoded image from clipboard paste events
 */

header('Content-Type: application/json');

// Allow only specific file types
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image data provided']);
    exit;
}

$imageData = $input['image'];

// Validate it's a valid base64 data URI
if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image data format']);
    exit;
}

$imageType = strtolower($matches[1]);
$mimeMap = [
    'jpeg' => 'image/jpeg',
    'jpg'  => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

$mime = $mimeMap[$imageType] ?? null;
if (!$mime || !in_array($mime, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
    exit;
}

// Decode base64
$base64 = substr($imageData, strpos($imageData, ',') + 1);
$decoded = base64_decode($base64);

if ($decoded === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to decode image data']);
    exit;
}

// Validate file size
if (strlen($decoded) > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Image size exceeds 5MB limit']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = $imageType === 'jpeg' ? 'jpg' : $imageType;
$filename = uniqid('pasted_img_', true) . '.' . $extension;
$filepath = $uploadDir . $filename;

// Save file
if (!file_put_contents($filepath, $decoded)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save image']);
    exit;
}

// Return the URL to the uploaded file
$appUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
$imageUrl = $appUrl . '/public/uploads/' . $filename;

echo json_encode(['location' => $imageUrl]);