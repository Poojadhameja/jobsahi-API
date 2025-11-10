<?php
// certificates.php – Generate styled certificate (no external library)
require_once '../cors.php';
require_once '../db.php';

$upload_dir = __DIR__ . '/../uploads/institute_certificate/';
$relative_path = '/uploads/institute_certificate/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ Authenticate Admin / Institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role']);
    $user_id = intval($decoded['user_id']);

    // ✅ Read JSON input
    $input = json_decode(file_get_contents("php://input"), true);

    $student_id   = intval($input['student_id'] ?? 0);
    $course_id    = intval($input['course_id'] ?? 0);
    $issue_date   = $input['issue_date'] ?? date('Y-m-d');
    $admin_action = 'approved';
    $created_at   = date('Y-m-d H:i:s');

    // ✅ Validation
    if ($student_id <= 0 || $course_id <= 0) {
        echo json_encode(["status" => false, "message" => "Student ID and Course ID required"]);
        exit;
    }

    // ✅ Prevent duplicate
    $chk = $conn->prepare("SELECT id FROM certificates WHERE student_id=? AND course_id=?");
    $chk->bind_param("ii", $student_id, $course_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(["status" => false, "message" => "Certificate already generated"]);
        exit;
    }

    // ✅ Fetch student + course info
    $info = $conn->prepare("
        SELECT u.user_name AS student_name, co.title AS course_title 
        FROM student_profiles s
        JOIN users u ON u.id=s.user_id
        JOIN courses co ON co.id=?
        WHERE s.id=?");
    $info->bind_param("ii", $course_id, $student_id);
    $info->execute();
    $details = $info->get_result()->fetch_assoc();
    if (!$details) {
        echo json_encode(["status" => false, "message" => "Invalid student or course"]);
        exit;
    }

    // ✅ Prepare paths
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $file_name = 'certificate_' . time() . '_' . $student_id . '.pdf';
    $file_path = $upload_dir . $file_name;
    $file_url  = $relative_path . $file_name;

    // ✅ Data for PDF - Escape special characters
    $student_name = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $details['student_name']);
    $course_title = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $details['course_title']);
    $display_date = date("j/n/Y", strtotime($issue_date));

    // Page dimensions: A4 landscape = 842 x 595 points
    $pageWidth = 842;
    $pageHeight = 595;
    $margin = 60; // Equal margin on all sides

    // ✅ Calculate centered X positions
    $title = "Certificate of Completion";
    $title_width = strlen($title) * 8.5; // Approximate width for font size 24
    $title_x = ($pageWidth - $title_width) / 2;

    $name_width = strlen($student_name) * 9; // Approximate width for font size 18
    $name_x = ($pageWidth - $name_width) / 2;

    $course_width = strlen($course_title) * 7; // Approximate width for font size 14
    $course_x = ($pageWidth - $course_width) / 2;

    // Calculate description text X position (left-aligned with margin)
    $desc_x = $margin + 40; // Additional padding from box edge

    // ✅ Generate PDF content stream with proper Y coordinates and equal margins
    $content = "q
0.94 0.97 1 rg
$margin 60 " . ($pageWidth - 2 * $margin) . " " . ($pageHeight - 2 * $margin) . " re f
Q

BT
/F1 24 Tf 
0.1 0.3 0.5 rg 
$title_x 450 Td 
($title) Tj
ET

BT
/F1 10 Tf 
0 0 0 rg 
$desc_x 390 Td 
(Upon successful completion of the course, participants will receive a Certificate of Completion,) Tj
0 -14 Td
(recognizing their achievement and confirming that they have acquired the essential skills and) Tj
0 -14 Td
(knowledge outlined in the curriculum.) Tj
ET

BT
/F1 18 Tf 
0.1 0.3 0.5 rg 
$name_x 310 Td 
($student_name) Tj
ET

BT
/F1 14 Tf 
0 0 0 rg 
$course_x 280 Td 
($course_title) Tj
ET

BT
/F1 10 Tf 
0 0 0 rg 
$margin 100 Td 
(Date: $display_date) Tj
ET
";

    $contentLength = strlen($content);

    // ✅ Build PDF structure with proper xref table
    $pdf = "%PDF-1.4\n";
    
    // Catalog
    $obj1_offset = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    
    // Pages
    $obj2_offset = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    
    // Page
    $obj3_offset = strlen($pdf);
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    
    // Content stream
    $obj4_offset = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Length $contentLength >>\nstream\n$content\nendstream\nendobj\n";
    
    // Font
    $obj5_offset = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    
    // xref table
    $xref_offset = strlen($pdf);
    $pdf .= "xref\n";
    $pdf .= "0 6\n";
    $pdf .= "0000000000 65535 f \n";
    $pdf .= sprintf("%010d 00000 n \n", $obj1_offset);
    $pdf .= sprintf("%010d 00000 n \n", $obj2_offset);
    $pdf .= sprintf("%010d 00000 n \n", $obj3_offset);
    $pdf .= sprintf("%010d 00000 n \n", $obj4_offset);
    $pdf .= sprintf("%010d 00000 n \n", $obj5_offset);
    
    // Trailer
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xref_offset\n%%EOF";

    // ✅ Write PDF file
    file_put_contents($file_path, $pdf);

    // ✅ Save record
    $stmt = $conn->prepare("INSERT INTO certificates 
        (student_id, course_id, file_url, issue_date, admin_action, created_at, modified_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $student_id, $course_id, $file_url, $issue_date, $admin_action, $created_at, $created_at);
    $stmt->execute();
    $certificate_id = $stmt->insert_id;

    // ✅ Response
    echo json_encode([
        "status" => true,
        "message" => "✅ Certificate generated successfully",
        "data" => [
            "certificate_id" => "CERT-" . date('Y') . "-" . str_pad($certificate_id, 3, '0', STR_PAD_LEFT),
            "student_name" => $details['student_name'],
            "course_title" => $details['course_title'],
            "issue_date" => $issue_date,
            "file_url" => $file_url,
            "admin_action" => $admin_action
        ],
        "meta" => [
            "role" => $role,
            "timestamp" => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// ❌ Invalid method
echo json_encode(["status" => false, "message" => "Method not allowed"]);
?>