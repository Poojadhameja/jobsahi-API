<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

try {
    $drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : null;
    $company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : null; // recruiter_profiles.id
    $campus_drive_company_id = isset($_GET['campus_drive_company_id']) ? intval($_GET['campus_drive_company_id']) : null; // campus_drive_companies.id
    $preference = isset($_GET['preference']) ? intval($_GET['preference']) : null; // 1-6
    $day_id = isset($_GET['day_id']) ? intval($_GET['day_id']) : null;
    $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    // Check if company_selections column exists
    $column_check = $conn->query("SHOW COLUMNS FROM campus_applications LIKE 'company_selections'");
    $has_company_selections = $column_check && $column_check->num_rows > 0;

    // Build query
    $company_selections_field = $has_company_selections ? "COALESCE(ca.company_selections, '{}') as company_selections," : "";
    $sql = "SELECT 
                ca.*,
                $company_selections_field
                u.user_name as student_name,
                u.email as student_email,
                u.phone_number as student_phone,
                sp.resume as resume_url,
                cdd.date as assigned_date,
                cdd.day_number,
                -- Preference 1 (ca.pref1_company_id is campus_drive_companies.id)
                ca.pref1_company_id as pref1_company_id,
                rp1.company_name as pref1_company_name,
                -- Preference 2
                ca.pref2_company_id as pref2_company_id,
                rp2.company_name as pref2_company_name,
                -- Preference 3
                ca.pref3_company_id as pref3_company_id,
                rp3.company_name as pref3_company_name,
                -- Preference 4
                ca.pref4_company_id as pref4_company_id,
                rp4.company_name as pref4_company_name,
                -- Preference 5
                ca.pref5_company_id as pref5_company_id,
                rp5.company_name as pref5_company_name,
                -- Preference 6
                ca.pref6_company_id as pref6_company_id,
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
    
    // Filter by campus_drive_companies.id (specific company entry in drive)
    if ($campus_drive_company_id) {
        if ($preference && $preference >= 1 && $preference <= 6) {
            // Filter by specific preference number
            $pref_field = "pref{$preference}_company_id";
            $conditions[] = "ca.$pref_field = $campus_drive_company_id";
        } else {
            // Show all preferences for this company
            $conditions[] = "(ca.pref1_company_id = $campus_drive_company_id
                             OR ca.pref2_company_id = $campus_drive_company_id
                             OR ca.pref3_company_id = $campus_drive_company_id
                             OR ca.pref4_company_id = $campus_drive_company_id
                             OR ca.pref5_company_id = $campus_drive_company_id
                             OR ca.pref6_company_id = $campus_drive_company_id)";
        }
    } elseif ($company_id) {
        // Filter by recruiter_profiles.id (all entries of this company across drives)
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
        // Format preferences and find which preference number this company is
        $preferences = [];
        $selected_preference = null;
        
        if ($campus_drive_company_id) {
            // Find which preference number this company is for this application
            for ($i = 1; $i <= 6; $i++) {
                $pref_id_field = "pref{$i}_company_id";
                $pref_name_field = "pref{$i}_company_name";
                
                $pref_id_value = isset($row[$pref_id_field]) ? intval($row[$pref_id_field]) : null;
                
                $pref_data = [
                    'preference_number' => $i,
                    'company_id' => $pref_id_value,
                    'company_name' => $row[$pref_name_field] ?? null
                ];
                
                // Compare using strict integer comparison
                if ($pref_id_value !== null && intval($pref_id_value) === intval($campus_drive_company_id)) {
                    $selected_preference = $i;
                    $pref_data['is_selected'] = true;
                }
                
                $preferences[] = $pref_data;
            }
        } else {
            // Format preferences normally
            $preferences = [
                [
                    'preference_number' => 1,
                    'company_id' => $row['pref1_company_id'],
                    'company_name' => $row['pref1_company_name']
                ],
                [
                    'preference_number' => 2,
                    'company_id' => $row['pref2_company_id'],
                    'company_name' => $row['pref2_company_name']
                ],
                [
                    'preference_number' => 3,
                    'company_id' => $row['pref3_company_id'],
                    'company_name' => $row['pref3_company_name']
                ],
                [
                    'preference_number' => 4,
                    'company_id' => $row['pref4_company_id'],
                    'company_name' => $row['pref4_company_name']
                ],
                [
                    'preference_number' => 5,
                    'company_id' => $row['pref5_company_id'],
                    'company_name' => $row['pref5_company_name']
                ],
                [
                    'preference_number' => 6,
                    'company_id' => $row['pref6_company_id'],
                    'company_name' => $row['pref6_company_name']
                ]
            ];
        }
        
        $row['preferences'] = $preferences;
        $row['selected_preference'] = $selected_preference;
        
        // Check if this company has selected this candidate (company-specific selection)
        if ($campus_drive_company_id) {
            // Check if company_selections field exists in result
            $company_selections_json = isset($row['company_selections']) ? $row['company_selections'] : '{}';
            $company_selections = json_decode($company_selections_json, true);
            if (!is_array($company_selections)) {
                $company_selections = [];
            }
            $row['is_selected_by_company'] = isset($company_selections[$campus_drive_company_id]) && 
                                             $company_selections[$campus_drive_company_id] === 'selected';
        } else {
            $row['is_selected_by_company'] = false;
        }
        
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

