<?php
// Function for billing

/**
 * Process billing requests (add, edit, delete, mark as paid)
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
/**
 * Process billing requests (add, edit, delete, mark as paid)
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Billing_Request($pdo)
{
    $response = ['status' => false, 'message' => '', 'redirect' => false];

    // Check if there's an action parameter
    if (isset($_GET['action'])) {
        $action = $_GET['action'];

        switch ($action) {
            case 'add':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $response = add_Billing($pdo, $_POST);
                    // Store response in session and set redirect flag
                    $_SESSION['billing_response'] = $response;
                    $response['redirect'] = true;
                }
                break;

            case 'edit':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $response = edit_Billing($pdo, $_POST);
                    // Store response in session and set redirect flag
                    $_SESSION['billing_response'] = $response;
                    $response['redirect'] = true;
                }
                break;

            case 'delete':
                if (isset($_GET['id'])) {
                    $response = delete_Billing($pdo, $_GET['id']);
                    // Store response in session and set redirect flag
                    $_SESSION['billing_response'] = $response;
                    $response['redirect'] = true;
                }
                break;

            case 'markPaid':
                if (isset($_GET['id'])) {
                    $response = mark_Billing_Paid($pdo, $_GET['id']);
                    // Store response in session and set redirect flag
                    $_SESSION['billing_response'] = $response;
                    $response['redirect'] = true;
                }
                break;
        }
    }

    return $response;
}

/**
 * Execute a search query for billing data with JOIN to get related student and term information
 * @param PDO $pdo Database connection
 * @param string $sql Initial SQL query
 * @param array $where_conditions Array of WHERE conditions
 * @param array $params Array of parameters for prepared statement
 * @return array Billing records with student and term names
 */
function executeSearchQuery($pdo, $sql, $where_conditions, $params) {
    // Apply WHERE conditions if any
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }

    // Add sorting - default to created_at DESC
    $sortField = isset($_POST['sortBy']) && !empty($_POST['sortBy']) ? $_POST['sortBy'] : 'created_at';
    $sortDirection = (isset($_POST['sortDirection']) && $_POST['sortDirection'] === 'ASC') ? 'ASC' : 'DESC';
    
    // Validate sort field to prevent SQL injection
    $allowedSortFields = ['due_date', 'amount', 'created_at', 'status'];
    if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'created_at'; // Default if invalid field is provided
    }
    
    $sql .= " ORDER BY b.$sortField $sortDirection";
    
    // Execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch student and term names for each billing record
    foreach ($results as &$row) {
        // Get student name
        $studentStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS student_name FROM students WHERE student_id = ?");
        $studentStmt->execute([$row['student_id']]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        $row['student_name'] = $student ? $student['student_name'] : 'Unknown Student';
        
        // Get term name
        $termStmt = $pdo->prepare("SELECT term_name FROM terms WHERE term_id = ?");
        $termStmt->execute([$row['term_id']]);
        $term = $termStmt->fetch(PDO::FETCH_ASSOC);
        $row['term_name'] = $term ? $term['term_name'] : 'Unknown Term';
        
        // Get staff name who created the billing
        $staffStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS staff_name FROM staff WHERE staff_id = ?");
        $staffStmt->execute([$row['created_by']]);
        $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
        $row['staff_name'] = $staff ? $staff['staff_name'] : 'Unknown Staff';
    }
    
    return $results;
}

/**
 * Get all billing data with optional filters
 * @param PDO $pdo Database connection
 * @return array Billing records
 */
function get_BillingData($pdo)
{
    $where_conditions = [];
    $params = [];
    $sql = "SELECT b.* FROM billings b";
    
    // Join with students table if searching by student name
    if (isset($_POST['searchStudent']) && !empty($_POST['searchStudent'])) {
        $search = $_POST['searchStudent'];
        $sql = "SELECT b.* FROM billings b 
                INNER JOIN students s ON b.student_id = s.student_id";
        $where_conditions[] = "(b.student_id LIKE ? OR 
                              CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Apply status filter if provided
    if (isset($_POST['statusFilter']) && !empty($_POST['statusFilter'])) {
        $status = $_POST['statusFilter'];

        // For any status including 'overdue'
        if (strpos($sql, "INNER JOIN") === false) {
            // No join yet, referring to billing's status directly
            $where_conditions[] = "b.status = ?";
        } else {
            // Already have a join, qualify the status column
            $where_conditions[] = "b.status = ?";
        }
        $params[] = $status;
    }

    // Use our common query execution function that adds JOINs and fetches related data
    return executeSearchQuery($pdo, $sql, $where_conditions, $params);
}

/**
 * Generate a unique billing ID
 * @param PDO $pdo Database connection
 * @return string New billing ID in format BL-00000
 */
function generate_Billing_ID($pdo)
{
    $stmt = $pdo->query("SELECT MAX(SUBSTRING(billing_id, 4)) as max_id FROM billings");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $next_id = 1;
    if ($result && $result['max_id']) {
        $next_id = intval($result['max_id']) + 1;
    }

    return 'BL-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Add a new billing record
 * @param PDO $pdo Database connection
 * @param array $data Form data
 * @return array Response with status and message
 */
function add_Billing($pdo, $data)
{
    try {
        // Input validation
        if (
            empty($data['student_id']) || empty($data['term_id']) ||
            empty($data['amount']) || empty($data['due_date'])
        ) {
            return ['status' => false, 'message' => 'All required fields must be filled out.'];
        }

        // Verify student exists
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ?");
        $stmt->execute([$data['student_id']]);
        if (!$stmt->fetch()) {
            return ['status' => false, 'message' => 'Student ID does not exist.'];
        }

        // Verify term exists
        $stmt = $pdo->prepare("SELECT term_id FROM terms WHERE term_id = ?");
        $stmt->execute([$data['term_id']]);
        if (!$stmt->fetch()) {
            return ['status' => false, 'message' => 'Term ID does not exist.'];
        }

        // Generate billing ID
        $billing_id = generate_Billing_ID($pdo);

        // Default status is pending
        $status = 'pending';

        // Check if due date is in the past
        if (strtotime($data['due_date']) < strtotime(date('Y-m-d'))) {
            $status = 'overdue';
        }

        // Insert the new billing record
        $sql = "INSERT INTO billings (billing_id, student_id, term_id, amount, due_date, 
                description, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $billing_id,
            $data['student_id'],
            $data['term_id'],
            $data['amount'],
            $data['due_date'],
            $data['description'] ?? '',
            $data['created_by'],
            $status
        ]);

        return ['status' => true, 'message' => 'Billing successfully posted.'];    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['status' => false, 'message' => 'Database error occurred. Please try again.'];
    }
}

/**
 * Edit an existing billing record
 * @param PDO $pdo Database connection
 * @param array $data Form data
 * @return array Response with status and message
 */
function edit_Billing($pdo, $data)
{
    try {
        // Input validation
        if (
            empty($data['billing_id']) || empty($data['amount']) ||
            empty($data['due_date']) || empty($data['status'])
        ) {
            return ['status' => false, 'message' => 'All required fields must be filled out.'];
        }

        // Verify billing exists
        $stmt = $pdo->prepare("SELECT billing_id FROM billings WHERE billing_id = ?");
        $stmt->execute([$data['billing_id']]);
        if (!$stmt->fetch()) {
            return ['status' => false, 'message' => 'Billing record not found.'];
        }

        // Update the billing record
        $sql = "UPDATE billings SET amount = ?, due_date = ?, description = ?, status = ? 
                WHERE billing_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['amount'],
            $data['due_date'],
            $data['description'] ?? '',
            $data['status'],
            $data['billing_id']
        ]);

        return ['status' => true, 'message' => 'Billing record successfully updated.'];
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['status' => false, 'message' => 'Database error occurred. Please try again.'];
    }
}

/**
 * Delete a billing record
 * @param PDO $pdo Database connection
 * @param string $billing_id Billing ID to delete
 * @return array Response with status and message
 */
function delete_Billing($pdo, $billing_id)
{
    try {
        // Verify billing exists
        $stmt = $pdo->prepare("SELECT billing_id FROM billings WHERE billing_id = ?");
        $stmt->execute([$billing_id]);
        if (!$stmt->fetch()) {
            return ['status' => false, 'message' => 'Billing record not found.'];
        }

        // Delete the billing record
        $sql = "DELETE FROM billings WHERE billing_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$billing_id]);

        return ['status' => true, 'message' => 'Billing record successfully deleted.'];
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['status' => false, 'message' => 'Database error occurred. Please try again.'];
    }
}

/**
 * Mark a billing record as paid
 * @param PDO $pdo Database connection
 * @param string $billing_id Billing ID to mark as paid
 * @return array Response with status and message
 */
function mark_Billing_Paid($pdo, $billing_id)
{
    try {
        // Verify billing exists
        $stmt = $pdo->prepare("SELECT billing_id, status FROM billings WHERE billing_id = ?");
        $stmt->execute([$billing_id]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$billing) {
            return ['status' => false, 'message' => 'Billing record not found.'];
        }

        if ($billing['status'] === 'paid') {
            return ['status' => false, 'message' => 'Billing is already marked as paid.'];
        }

        // Update the status to paid
        $sql = "UPDATE billings SET status = 'paid' WHERE billing_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$billing_id]);

        return ['status' => true, 'message' => 'Billing successfully marked as paid.'];
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['status' => false, 'message' => 'Database error occurred. Please try again.'];
    }
}

/**
 * Get summary statistics for billing dashboard
 * @param PDO $pdo Database connection
 * @return array Billing statistics
 */
function get_Billing_Stats($pdo)
{
    // Total outstanding amount
    $stmt = $pdo->query("SELECT SUM(amount) as total_outstanding FROM billings WHERE status != 'paid'");
    $outstanding = $stmt->fetch(PDO::FETCH_ASSOC)['total_outstanding'] ?? 0;

    // Count by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM billings GROUP BY status");
    $status_counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_counts[$row['status']] = $row['count'];
    }

    // Count overdue
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as overdue_count FROM billings WHERE status != 'paid' AND due_date < ?");
    $stmt->execute([$today]);
    $overdue_count = $stmt->fetch(PDO::FETCH_ASSOC)['overdue_count'] ?? 0;

    return [
        'total_outstanding' => $outstanding,
        'status_counts' => $status_counts,
        'overdue_count' => $overdue_count
    ];
}

/**
 * Update overdue statuses for all billing records
 * This should be run daily via cron job
 * @param PDO $pdo Database connection
 */
function update_overdue_status($pdo)
{
    $today = date('Y-m-d');

    $sql = "UPDATE billings SET status = 'overdue' 
            WHERE status IN ('pending', 'partial') AND due_date < ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);

    return $stmt->rowCount(); // Returns number of updated records
}
?>