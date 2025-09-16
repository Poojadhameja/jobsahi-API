<?php
include '../CORS.php';
// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT (any valid user can access transactions)
$decoded = authenticateJWT(); // returns array with user data
$userRole = isset($decoded['role']) ? strtolower($decoded['role']) : null;

// Get transaction ID from URL parameter
$transactionId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$transactionId) {
    echo json_encode([
        "status" => false,
        "message" => "Transaction ID is required"
    ]);
    exit();
}

try {
    // Check table structure
    $checkTransactions = $conn->query("DESCRIBE transactions");
    if (!$checkTransactions) {
        throw new Exception("Cannot access transactions table structure");
    }

    // Get column names for transactions table
    $transactionsColumns = [];
    while ($row = $checkTransactions->fetch_assoc()) {
        $transactionsColumns[] = $row['Field'];
    }

    // Detect column names
    $idColumn = in_array('transaction_id', $transactionsColumns) ? 'transaction_id' : 'id';
    $userIdColumn = in_array('user_id', $transactionsColumns) ? 'user_id' : 'NULL';
    $planIdColumn = in_array('plan_id', $transactionsColumns) ? 'plan_id' : 'NULL';
    $amountColumn = in_array('amount', $transactionsColumns) ? 'amount' : (in_array('price', $transactionsColumns) ? 'price' : 'NULL');
    $statusColumn = in_array('status', $transactionsColumns) ? 'status' : (in_array('payment_status', $transactionsColumns) ? 'payment_status' : 'NULL');
    $paymentMethodColumn = in_array('payment_method', $transactionsColumns) ? 'payment_method' : 'NULL';
    $transactionIdColumn = in_array('transaction_ref', $transactionsColumns) ? 'transaction_ref' : (in_array('reference_id', $transactionsColumns) ? 'reference_id' : 'NULL');
    $createdAtColumn = in_array('created_at', $transactionsColumns) ? 'created_at' : 'NULL';
    $updatedAtColumn = in_array('updated_at', $transactionsColumns) ? 'updated_at' : 'NULL';
    $adminActionColumn = in_array('admin_action', $transactionsColumns) ? 'admin_action' : 'NULL';

    // ✅ Build role-based WHERE condition
    $whereCondition = "WHERE {$idColumn} = ?";
    if ($adminActionColumn !== 'NULL') {
        if ($userRole === 'admin') {
            // Admin sees all (pending + approved)
            $whereCondition .= "";
        } else {
            // Non-admin sees only approved
            $whereCondition .= " AND {$adminActionColumn} = 'approved'";
        }
    }

    // ✅ Build final query
    $sql = "
        SELECT 
            {$idColumn} as transaction_id,
            {$userIdColumn} as user_id,
            {$planIdColumn} as plan_id,
            {$amountColumn} as amount,
            {$statusColumn} as status,
            {$paymentMethodColumn} as payment_method,
            {$transactionIdColumn} as transaction_ref,
            {$adminActionColumn} as admin_action,
            {$createdAtColumn} as created_at,
            {$updatedAtColumn} as updated_at
        FROM transactions
        {$whereCondition}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $transactionId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();

        if ($transaction) {
            echo json_encode([
                "status" => true,
                "message" => "Transaction retrieved successfully",
                "data" => $transaction,
                "role" => $userRole
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Transaction not found or not authorized to view"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve transaction",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>