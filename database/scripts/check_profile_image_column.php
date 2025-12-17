<?php
require_once __DIR__ . '/../../api/db.php';

$result = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles LIKE 'profile_image'");
if(mysqli_num_rows($result) > 0) {
    echo "✅ profile_image column exists\n";
    $row = mysqli_fetch_assoc($result);
    print_r($row);
} else {
    echo "❌ profile_image column NOT found\n";
}

mysqli_close($conn);
?>

