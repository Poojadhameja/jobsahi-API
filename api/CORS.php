<?php 
header('Content-Type: application/json');

// Allow specific prod origins
$strictAllowed = [
  'https://beige-jaguar-560051.hostingersite.com',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allow = false;

// Allow any localhost / 127.0.0.1 (any port) for dev
if (preg_match('#^http://localhost(:\d+)?$#', $origin)) {
  $allow = true;
} elseif (preg_match('#^http://127\.0\.0\.1(:\d+)?$#', $origin)) {
  $allow = true;
} elseif (in_array($origin, $strictAllowed, true)) {
  $allow = true;
}

if ($allow) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
} else {
  // Uncomment to hard-block unknown origins in prod
  // http_response_code(403);
  // echo json_encode(["status"=>false,"message"=>"Origin not allowed"]);
  // exit;
}

// Preflight + headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 86400"); // cache preflight 24h
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    "status"  => false,
    "message" => "Only POST requests allowed",
    "code"    => "METHOD_NOT_ALLOWED"
  ]);
  exit;
}


?>