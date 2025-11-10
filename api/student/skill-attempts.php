<?php
// skill-attempts.php - Manage test question attempts
require_once '../cors.php';
require_once '../db.php';
header('Content-Type: application/json');

function respond($d) { echo json_encode($d); exit; }

// ✅ JWT roles
$current_user = authenticateJWT(['student', 'recruiter', 'admin']);
$user_role = $current_user['role'] ?? '';
$user_id   = $current_user['user_id'] ?? null;

// ✅ Helper functions
function getStudentIdFromProfile($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

function getOrCreateStudentProfile($conn, $user_id) {
    $sid = getStudentIdFromProfile($conn, $user_id);
    if ($sid) return $sid;

    $stmt = $conn->prepare("INSERT INTO student_profiles (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();
    return $new_id;
}

function verifyStudentProfile($conn, $sid) {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

/* =========================================================
   POST: Record attempt (Only one attempt per question)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!in_array($user_role, ['student', 'recruiter'])) {
        respond(["status"=>false, "message"=>"Only students and recruiters can submit attempts"]);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) respond(["status"=>false, "message"=>"Invalid JSON input"]);

    $required = ['test_id','question_id','selected_option','is_correct','attempt_number','time_taken_seconds'];
    foreach ($required as $f) if (!isset($data[$f])) respond(["status"=>false,"message"=>"Missing $f"]);

    // ✅ Determine student_id
    if ($user_role === 'student') {
        $student_id = getOrCreateStudentProfile($conn, $user_id);
    } else { // recruiter
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : getOrCreateStudentProfile($conn, $user_id);
        if (!verifyStudentProfile($conn, $student_id))
            respond(["status"=>false,"message"=>"Invalid student_id"]);
    }

    $test_id  = (int)$data['test_id'];
    $question_id = (int)$data['question_id'];
    $selected = strtoupper(trim($data['selected_option']));
    $is_correct = (int)$data['is_correct'];
    $attempt_no = (int)$data['attempt_number'];
    $time_taken = (int)$data['time_taken_seconds'];

    if (!in_array($selected, ['A','B','C','D'])) respond(["status"=>false,"message"=>"Invalid selected_option"]);

    // ✅ Check if this student already attempted this question in this test
    $check = $conn->prepare("SELECT id FROM skill_attempts WHERE student_id=? AND test_id=? AND question_id=? LIMIT 1");
    $check->bind_param("iii", $student_id, $test_id, $question_id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        // 🔄 Update existing attempt instead of inserting new one
        $update = $conn->prepare("
            UPDATE skill_attempts 
            SET selected_option=?, is_correct=?, attempt_number=?, time_taken_seconds=?, answered_at=NOW()
            WHERE id=?");
        $update->bind_param("siiii", $selected, $is_correct, $attempt_no, $time_taken, $exists['id']);
        if ($update->execute()) {
            $update->close();
            respond(["status"=>true,"message"=>"Attempt updated (single attempt rule enforced)","attempt_id"=>$exists['id']]);
        } else {
            $update->close();
            respond(["status"=>false,"message"=>"Failed to update attempt"]);
        }
    } else {
        // 🆕 Create new attempt
        $stmt = $conn->prepare("
            INSERT INTO skill_attempts 
            (student_id, test_id, question_id, selected_option, is_correct, attempt_number, time_taken_seconds, answered_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiisiii", $student_id, $test_id, $question_id, $selected, $is_correct, $attempt_no, $time_taken);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            respond(["status"=>true,"message"=>"Attempt recorded","insert_id"=>$new_id]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            respond(["status"=>false,"message"=>$err]);
        }
    }
}

/* =========================================================
   GET: Retrieve Attempts (Student/Recruiter/Admin)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $student_q = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    $test_q = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;

    if ($user_role === 'student') {
        $student_q = getOrCreateStudentProfile($conn, $user_id);
    } elseif ($user_role === 'recruiter' && !$student_q) {
        $student_q = getOrCreateStudentProfile($conn, $user_id);
    }

    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM skill_attempts WHERE id=?");
        $stmt->bind_param("i",$id);
    } else {
        $where=[]; $types=''; $vals=[];
        if ($student_q) { $where[]="student_id=?"; $types.='i'; $vals[]=$student_q; }
        if ($test_q)    { $where[]="test_id=?";   $types.='i'; $vals[]=$test_q; }
        $sql="SELECT * FROM skill_attempts".(count($where)?" WHERE ".implode(" AND ",$where):"")." ORDER BY id ASC";
        $stmt=$conn->prepare($sql);
        if(count($vals)) $stmt->bind_param($types,...$vals);
    }

    $stmt->execute();
    $res=$stmt->get_result();
    $rows=[];
    while($r=$res->fetch_assoc()) $rows[]=$r;
    $stmt->close();
    respond(["status"=>true,"data"=>$rows]);
}

/* =========================================================
   PUT / DELETE unchanged (Admin & Recruiter)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!in_array($user_role, ['admin','recruiter'])) respond(["status"=>false,"message"=>"Only admin or recruiter can edit"]);
    $data=json_decode(file_get_contents("php://input"),true);
    if(!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
    $id=(int)$data['id'];
    $fields=[];$types='';$vals=[];
    $allowed=['selected_option','is_correct','attempt_number','time_taken_seconds'];
    foreach($allowed as $f){ if(isset($data[$f])){ $fields[]="$f=?"; $types.=in_array($f,['is_correct','attempt_number','time_taken_seconds'])?'i':'s'; $vals[]=$data[$f]; }}
    if(!count($fields)) respond(["status"=>false,"message"=>"No fields to update"]);
    $sql="UPDATE skill_attempts SET ".implode(", ",$fields).", answered_at=NOW() WHERE id=?";
    $types.='i';$vals[]=$id;
    $stmt=$conn->prepare($sql); $stmt->bind_param($types,...$vals);
    if($stmt->execute()){ $stmt->close(); respond(["status"=>true,"message"=>"Updated"]); }
    else { $e=$stmt->error; $stmt->close(); respond(["status"=>false,"message"=>$e]); }
}

// if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
//     if (!in_array($user_role,['admin','recruiter'])) respond(["status"=>false,"message"=>"Only admin or recruiter can delete"]);
//     $data=json_decode(file_get_contents("php://input"),true);
//     if(!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
//     $id=(int)$data['id'];
//     $stmt=$conn->prepare("DELETE FROM skill_attempts WHERE id=?");
//     $stmt->bind_param("i",$id);
//     if($stmt->execute()){ $stmt->close(); respond(["status"=>true,"message"=>"Deleted"]); }
//     else { $e=$stmt->error; $stmt->close(); respond(["status"=>false,"message"=>$e]); }
// }

respond(["status"=>false,"message"=>"Only GET, POST, PUT, DELETE allowed"]);
?>
