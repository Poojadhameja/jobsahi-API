<?php
include '../CORS.php';
// ✅ Authenticate JWT (any valid user can access transactions)
$decoded = authenticateJWT(); // returns array

try {
    // First, let's check what columns exist in transactions table
    $checkTransactions = $conn->query("DESCRIBE transactions");
    
    if (!$checkTransactions) {
        throw new Exception("Cannot access transactions table structure");
    }
    
    // Get column names for transactions table
    $transactionsColumns = [];
    while ($row = $checkTransactions->fetch_assoc()) {
        $transactionsColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('transaction_id', $transactionsColumns)) {
        $idColumn = 'transaction_id';
    } elseif (in_array('id', $transactionsColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist in transactions table
    $userIdColumn = in_array('user_id', $transactionsColumns) ? 'user_id' : 'NULL';
    $planIdColumn = in_array('plan_id', $transactionsColumns) ? 'plan_id' : 'NULL';
    $amountColumn = in_array('amount', $transactionsColumns) ? 'amount' : (in_array('price', $transactionsColumns) ? 'price' : 'NULL');
    $statusColumn = in_array('status', $transactionsColumns) ? 'status' : (in_array('payment_status', $transactionsColumns) ? 'payment_status' : 'NULL');
    $paymentMethodColumn = in_array('payment_method', $transactionsColumns) ? 'payment_method' : 'NULL';
    $transactionIdColumn = in_array('transaction_ref', $transactionsColumns) ? 'transaction_ref' : (in_array('reference_id', $transactionsColumns) ? 'reference_id' : 'NULL');
    $createdAtColumn = in_array('created_at', $transactionsColumns) ? 'created_at' : 'NULL';
    $updatedAtColumn = in_array('updated_at', $transactionsColumns) ? 'updated_at' : 'NULL';
    
    // Build the query with correct column names
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as transaction_id,
            {$userIdColumn} as user_id,
            {$planIdColumn} as plan_id,
            {$amountColumn} as amount,
            {$statusColumn} as status,
            {$paymentMethodColumn} as payment_method,
            {$transactionIdColumn} as transaction_ref,
            {$createdAtColumn} as created_at,
            {$updatedAtColumn} as updated_at
        FROM transactions
        ORDER BY {$createdAtColumn} DESC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $transactions = [];
        
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Transactions retrieved successfully",
            "data" => $transactions,
            "count" => count($transactions)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve transactions",
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