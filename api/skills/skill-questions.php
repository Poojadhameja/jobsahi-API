<?php
// skill-questions.php - Manage skill test questions (linked to job_id)
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');
$current_user = authenticateJWT(['admin','recruiter','student']);
$user_role = $current_user['role'] ?? '';
$user_id = $current_user['user_id'] ?? null;

function respond($d){ echo json_encode($d); exit; }

function getRecruiterId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? AND admin_action = 'approved' LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? (int)$res['id'] : null;
}

// --------------------
// POST: Create Question (Admin/Recruiter)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($user_role, ['admin','recruiter'])) 
        respond(["status"=>false,"message"=>"Only recruiter or admin can add questions"]);

    $data = json_decode(file_get_contents("php://input"), true);
    $required = ['job_id','question_text','option_a','option_b','option_c','option_d','correct_option'];
    foreach ($required as $f) {
        if (!isset($data[$f]) || trim($data[$f]) === '') respond(["status"=>false,"message"=>"Missing field: $f"]);
    }

    $job_id = (int)$data['job_id'];
    $question_text = trim($data['question_text']);
    $opt_a = trim($data['option_a']);
    $opt_b = trim($data['option_b']);
    $opt_c = trim($data['option_c']);
    $opt_d = trim($data['option_d']);
    $correct = strtoupper(trim($data['correct_option'])); // 'A','B','C','D'

    if (!in_array($correct, ['A','B','C','D'])) {
        respond(["status"=>false,"message"=>"correct_option must be one of A, B, C, or D"]);
    }

    // ✅ verify job exists
    $check = $conn->prepare("SELECT id, recruiter_id FROM jobs WHERE id = ?");
    $check->bind_param("i",$job_id);
    $check->execute();
    $chk = $check->get_result();
    if ($chk->num_rows === 0) respond(["status"=>false,"message"=>"Invalid job_id"]);
    $jobRow = $chk->fetch_assoc();
    $check->close();

    if ($user_role === 'recruiter') {
        $recruiter_id = getRecruiterId($conn, $user_id);
        if (!$recruiter_id) respond(["status"=>false,"message"=>"Recruiter profile not found or not approved"]);
        if ((int)$jobRow['recruiter_id'] !== $recruiter_id) {
            respond(["status"=>false,"message"=>"You are not allowed to add questions for this job"]);
        }
    }

    // ✅ enforce max 15 questions per job
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM skill_questions WHERE job_id = ?");
    $countStmt->bind_param("i", $job_id);
    $countStmt->execute();
    $countRes = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $currentTotal = (int)($countRes['total'] ?? 0);
    if ($currentTotal >= 15) {
        respond(["status"=>false,"message"=>"Maximum 15 questions allowed for this job"]);
    }

    $stmt = $conn->prepare("
        INSERT INTO skill_questions (job_id, question_text, option_a, option_b, option_c, option_d, correct_option, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issssss",$job_id,$question_text,$opt_a,$opt_b,$opt_c,$opt_d,$correct);

    if($stmt->execute()){
        respond(["status"=>true,"message"=>"Question added successfully","insert_id"=>$stmt->insert_id]);
    } else {
        respond(["status"=>false,"message"=>"Error: ".$stmt->error]);
    }
}

// --------------------
// GET: Fetch Questions (Admin/Recruiter/Student)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM skill_questions WHERE id = ?");
        $stmt->bind_param("i",$id);
    } elseif ($job_id) {
        $stmt = $conn->prepare("SELECT * FROM skill_questions WHERE job_id = ? ORDER BY id ASC");
        $stmt->bind_param("i",$job_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM skill_questions ORDER BY id ASC");
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows=[];
    while($r=$res->fetch_assoc()){
        if($user_role==='student') unset($r['correct_option']);
        $rows[]=$r;
    }
    respond(["status"=>true,"data"=>$rows]);
}

// --------------------
// PUT: Update Question
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!in_array($user_role, ['admin','recruiter'])) 
        respond(["status"=>false,"message"=>"Only admin or recruiter can edit"]);

    $data=json_decode(file_get_contents("php://input"),true);
    if(!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
    $id=(int)$data['id'];

    $fields=[]; $types=''; $vals=[];
    $allowed=['job_id','question_text','option_a','option_b','option_c','option_d','correct_option'];
    foreach($allowed as $f){
        if(isset($data[$f])){
            $fields[]="$f=?";
            $types.=($f==='job_id')?'i':'s';
            $vals[]=$data[$f];
        }
    }
    if(!count($fields)) respond(["status"=>false,"message"=>"No fields to update"]);

    $sql="UPDATE skill_questions SET ".implode(", ",$fields)." WHERE id=?";
    $types.='i'; $vals[]=$id;
    $stmt=$conn->prepare($sql);
    $stmt->bind_param($types,...$vals);

    if($stmt->execute()){
        respond(["status"=>true,"message"=>"Question updated successfully"]);
    } else respond(["status"=>false,"message"=>$stmt->error]);
}

respond(["status"=>false,"message"=>"Only GET, POST, PUT allowed"]);
?>
