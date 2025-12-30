<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

try {
    $drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : null;
    $company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : null;
    $day_id = isset($_GET['day_id']) ? intval($_GET['day_id']) : null;
    $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    // Build query
    $sql = "SELECT 
                ca.*,
                u.user_name as student_name,
                u.email as student_email,
                u.phone_number as student_phone,
                sp.resume as resume_url,
                cdd.date as assigned_date,
                cdd.day_number,
                -- Preference 1
                cdc1.company_id as pref1_company_id,
                rp1.company_name as pref1_company_name,
                -- Preference 2
                cdc2.company_id as pref2_company_id,
                rp2.company_name as pref2_company_name,
                -- Preference 3
                cdc3.company_id as pref3_company_id,
                rp3.company_name as pref3_company_name,
                -- Preference 4
                cdc4.company_id as pref4_company_id,
                rp4.company_name as pref4_company_name,
                -- Preference 5
                cdc5.company_id as pref5_company_id,
                rp5.company_name as pref5_company_name,
                -- Preference 6
                cdc6.company_id as pref6_company_id,
                rp6.company_name as pref6_company_name
            FROM campus_applications ca
            LEFT JOIN student_profiles sp ON ca.student_id = sp.id
            LEFT JOIN users u ON sp.user_id = u.id
            LEFT JOIN campus_drive_days cdd ON ca.assigned_day_id = cdd.id
            LEFT JOIN campus_drive_companies cdc1 ON ca.pref1_company_id = cdc1.id
            LEFT JOIN recruiter_profiles rp1 ON cdc1.company_id = rp1.id
            LEFT JOIN campus_drive_companies cdc2 ON ca.pref2_company_id = cdc2.id
            LEFT JOIN recruiter_profiles rp2 ON cdc2.company_id = rp2.id
            LEFT JOIN campus_drive_companies cdc3 ON ca.pref3_company_id = cdc3.id
            LEFT JOIN recruiter_profiles rp3 ON cdc3.company_id = rp3.id
            LEFT JOIN campus_drive_companies cdc4 ON ca.pref4_company_id = cdc4.id
            LEFT JOIN recruiter_profiles rp4 ON cdc4.company_id = rp4.id
            LEFT JOIN campus_drive_companies cdc5 ON ca.pref5_company_id = cdc5.id
            LEFT JOIN recruiter_profiles rp5 ON cdc5.company_id = rp5.id
            LEFT JOIN campus_drive_companies cdc6 ON ca.pref6_company_id = cdc6.id
            LEFT JOIN recruiter_profiles rp6 ON cdc6.company_id = rp6.id
            WHERE 1=1";
    
    $conditions = [];
    if ($drive_id) {
        $conditions[] = "ca.drive_id = $drive_id";
    }
    
    if ($company_id) {
        $conditions[] = "(ca.pref1_company_id IN (SELECT id FROM campus_drive_companies WHERE company_id = $company_id)
                         OR ca.pref2_company_id IN (SELECT id FROM campus_drive_companies WHERE company_id = $company_id)
                         OR ca.pref3_company_id IN (SELECT id FROM campus_drive_companies WHERE company_id = $company_id)
                         OR ca.pref4_company_id IN (SELECT id FROM campus_drive_companies WHERE company_id = $company_id)
                         OR ca.pref5_company_id IN (SELECT id FROM campus_drive_companies WHERE company_id = $company_id)
                         OR ca.pref6_company_id IN (SELECT id FROM campus_drive_companies WHERE company_id = $company_id))";
    }
    
    if ($day_id) {
        $conditions[] = "ca.assigned_day_id = $day_id";
    }
    
    if ($status && in_array($status, ['pending', 'shortlisted', 'rejected', 'selected'])) {
        $conditions[] = "ca.status = '$status'";
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM campus_applications ca WHERE 1=1";
    if (!empty($conditions)) {
        $count_sql .= " AND " . implode(" AND ", $conditions);
    }
    $count_result = $conn->query($count_sql);
    $total = $count_result->fetch_assoc()['total'];
    
    // Add ordering and pagination
    $sql .= " ORDER BY ca.applied_at DESC LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($sql);
    $applications = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format preferences
        $row['preferences'] = [
            [
                'company_id' => $row['pref1_company_id'],
                'company_name' => $row['pref1_company_name']
            ],
            [
                'company_id' => $row['pref2_company_id'],
                'company_name' => $row['pref2_company_name']
            ],
            [
                'company_id' => $row['pref3_company_id'],
                'company_name' => $row['pref3_company_name']
            ],
            [
                'company_id' => $row['pref4_company_id'],
                'company_name' => $row['pref4_company_name']
            ],
            [
                'company_id' => $row['pref5_company_id'],
                'company_name' => $row['pref5_company_name']
            ],
            [
                'company_id' => $row['pref6_company_id'],
                'company_name' => $row['pref6_company_name']
            ]
        ];
        
        // Remove individual pref fields from response
        unset($row['pref1_company_id'], $row['pref1_company_name']);
        unset($row['pref2_company_id'], $row['pref2_company_name']);
        unset($row['pref3_company_id'], $row['pref3_company_name']);
        unset($row['pref4_company_id'], $row['pref4_company_name']);
        unset($row['pref5_company_id'], $row['pref5_company_name']);
        unset($row['pref6_company_id'], $row['pref6_company_name']);
        
        $applications[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Applications fetched successfully",
        "data" => $applications,
        "pagination" => [
            "total" => intval($total),
            "page" => $page,
            "limit" => $limit,
            "total_pages" => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching applications",
        "error" => $e->getMessage()
    ]);
}
?>

