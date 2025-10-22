<?php
require_once '../cors.php';

$decoded = authenticateJWT(['institute']); // verify token

$institute_id = $decoded['id'] ?? null;

$response = [
  "status" => "error",
  "message" => "Something went wrong"
];

if ($institute_id) {
  // 🟢 Course Completion Rate (example calculation)
  $courseQuery = "SELECT 
                    COUNT(*) as total_courses, 
                    SUM(CASE WHEN completion_status='completed' THEN 1 ELSE 0 END) as completed_courses 
                  FROM student_courses 
                  WHERE institute_id = ?";
  $stmt = $conn->prepare($courseQuery);
  $stmt->bind_param("i", $institute_id);
  $stmt->execute();
  $courseResult = $stmt->get_result()->fetch_assoc();
  $completionRate = $courseResult['total_courses'] > 0 
    ? round(($courseResult['completed_courses'] / $courseResult['total_courses']) * 100) 
    : 0;

  // 🔵 Student Satisfaction (average rating)
  $ratingQuery = "SELECT AVG(rating) as avg_rating FROM feedback WHERE institute_id = ?";
  $stmt = $conn->prepare($ratingQuery);
  $stmt->bind_param("i", $institute_id);
  $stmt->execute();
  $ratingResult = $stmt->get_result()->fetch_assoc();
  $satisfaction = round(($ratingResult['avg_rating'] / 5) * 100);

  // 🟣 Placement Success Rate
  $placementQuery = "SELECT 
                        COUNT(*) as total_students, 
                        SUM(CASE WHEN status='Placed' THEN 1 ELSE 0 END) as placed_students 
                     FROM placements 
                     WHERE institute_id = ?";
  $stmt = $conn->prepare($placementQuery);
  $stmt->bind_param("i", $institute_id);
  $stmt->execute();
  $placementResult = $stmt->get_result()->fetch_assoc();
  $placementSuccess = $placementResult['total_students'] > 0 
    ? round(($placementResult['placed_students'] / $placementResult['total_students']) * 100) 
    : 0;

  $response = [
    "status" => "success",
    "data" => [
      "course_completion_rate" => $completionRate,
      "student_satisfaction" => $satisfaction,
      "placement_success" => $placementSuccess
    ]
  ];
}

echo json_encode($response);
?>
