<?php

// Handle all enrollment actions
if (isset($_POST['action']) || isset($_GET['action'])) {
    $action = isset($_POST['action']) ? $_POST['action'] : $_GET['action'];

    switch ($action) {
        case 'get_programs':
            handleGetPrograms();
            break;
        case 'submit_enrollment':
            handleSubmitEnrollment();
            break;
        case 'add_enrollment':
            handleAddEnrollment();
            break;
        case 'update_enrollment':
            handleUpdateEnrollment();
            break;        case 'get_enrollment':
            handleGetEnrollment();
            break;
        case 'approve_enrollment':
            handleApproveEnrollment();
            break;
        case 'reject_enrollment':
            handleRejectEnrollment();
            break;
        case 'delete_enrollment':
            handleDeleteEnrollment();
            break;
        case 'get_enrollments':
            // This would be called from the main page to get filtered enrollments
            handleGetEnrollments();
            break;
    }
}

/**
 * Generate a unique enrollment ID
 * @return string New enrollment ID in format EN-xxxxx
 */
function generateEnrollmentID()
{
    global $pdo;

    // Get the current highest enrollment ID
    $stmt = $pdo->prepare("SELECT MAX(SUBSTRING(enrollment_id, 4)) as max_id FROM enrollments WHERE enrollment_id LIKE 'EN-%'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextID = 1;
    if ($result && $result['max_id']) {
        $nextID = intval($result['max_id']) + 1;
    }

    // Format with leading zeros (EN-00001, EN-00002, etc.)
    return 'EN-' . str_pad($nextID, 5, '0', STR_PAD_LEFT);
}

/**
 * Generate a unique enrollment detail ID
 * @return string New enrollment detail ID in format ED-xxxxx
 */
function generateEnrollmentDetailID()
{
    global $pdo;

    // Get the current highest enrollment detail ID
    $stmt = $pdo->prepare("SELECT MAX(SUBSTRING(detail_id, 4)) as max_id FROM enrollment_details WHERE detail_id LIKE 'ED-%'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextID = 1;
    if ($result && $result['max_id']) {
        $nextID = intval($result['max_id']) + 1;
    }

    // Format with leading zeros (ED-00001, ED-00002, etc.)
    return 'ED-' . str_pad($nextID, 5, '0', STR_PAD_LEFT);
}

/**
 * Add a new enrollment record
 */
function handleAddEnrollment()
{
    global $pdo;

    // Create enrollment response array for notifications
    $enrollment_response = [
        'status' => false,
        'message' => ''
    ];

    // Start transaction
    $pdo->beginTransaction();

    try {        
        // Validate required fields
        if (empty($_POST['student_id']) || empty($_POST['term_id']) || empty($_POST['enrollment_date']) || empty($_POST['status'])) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "All required fields must be filled";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }        // Validate that at least one class is selected (only required for approved enrollments)
        if ($_POST['status'] === 'approved' && (empty($_POST['selected_classes']) || !is_array($_POST['selected_classes']))) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Please select at least one class for approved enrollments";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Check if student already enrolled in this term
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND term_id = ?");
        $stmt->execute([$_POST['student_id'], $_POST['term_id']]);
        if ($stmt->fetchColumn() > 0) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Student is already enrolled in this term";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Generate new enrollment ID
        $enrollmentID = generateEnrollmentID();

        // Set up approved fields if status is approved
        $approved_by = null;
        $approved_date = null;

        if ($_POST['status'] === 'approved') {
            if (empty($_POST['approved_by']) || empty($_POST['approved_date'])) {
                $enrollment_response['status'] = false;
                $enrollment_response['message'] = "Approved by and approved date are required for approved status";
                $_SESSION['enrollment_response'] = $enrollment_response;
                header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
                exit;
            }
            $approved_by = $_POST['approved_by'];
            $approved_date = $_POST['approved_date'];
        }

        // Prepare and execute INSERT statement for enrollment
        $stmt = $pdo->prepare("
            INSERT INTO enrollments 
            (enrollment_id, student_id, term_id, enrollment_date, status, approved_by, approved_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $success = $stmt->execute([
            $enrollmentID,
            $_POST['student_id'],
            $_POST['term_id'],
            $_POST['enrollment_date'],
            $_POST['status'],
            $approved_by,
            $approved_date,
            $_POST['notes'] ?? null
        ]);
        
        if (!$success) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Failed to add enrollment record";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }        // Add enrollment details for each selected class (only for approved enrollments)
        $insertedDetails = 0;
        if ($_POST['status'] === 'approved' && !empty($_POST['selected_classes']) && is_array($_POST['selected_classes'])) {
            foreach ($_POST['selected_classes'] as $classId) {
                // Validate that the class exists and belongs to the selected term
                $classStmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_id = ? AND term_id = ?");
                $classStmt->execute([$classId, $_POST['term_id']]);
                
                if ($classStmt->fetch()) {
                    // Generate enrollment detail ID
                    $detailID = generateEnrollmentDetailID();
                    
                    // Insert enrollment detail
                    $detailStmt = $pdo->prepare("
                        INSERT INTO enrollment_details 
                        (detail_id, enrollment_id, class_id, status, date_added)
                        VALUES (?, ?, ?, 'enrolled', CURRENT_TIMESTAMP)
                    ");
                    
                    if ($detailStmt->execute([$detailID, $enrollmentID, $classId])) {
                        $insertedDetails++;
                    }
                }
            }

            if ($insertedDetails === 0) {
                throw new Exception("Failed to add enrollment details for any of the selected classes");
            }
        }

        // Commit the transaction
        $pdo->commit();        // Set success response
        if ($_POST['status'] === 'approved' && $insertedDetails > 0) {
            $enrollment_response = [
                'status' => true,
                'message' => "Enrollment record added successfully with ID: $enrollmentID. Added $insertedDetails class(es)."
            ];
        } else {
            $enrollment_response = [
                'status' => true,
                'message' => "Enrollment record added successfully with ID: $enrollmentID."
            ];
        }

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;

    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();

        // Set error response
        $enrollment_response = [
            'status' => false,
            'message' => $e->getMessage()
        ];        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;
    }
}

/**
 * Update an existing enrollment record
 */
function handleUpdateEnrollment()
{
    global $pdo;

    // Create enrollment response array for notifications
    $enrollment_response = [
        'status' => false,
        'message' => ''
    ];

    // Start transaction
    $pdo->beginTransaction();

    try {        // Validate required fields
        if (
            empty($_POST['enrollment_id']) || empty($_POST['student_id']) || empty($_POST['term_id']) ||
            empty($_POST['enrollment_date']) || empty($_POST['status'])
        ) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "All required fields must be filled";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Check if changing to a combination that already exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM enrollments 
            WHERE student_id = ? AND term_id = ? AND enrollment_id != ?
        ");
        $stmt->execute([$_POST['student_id'], $_POST['term_id'], $_POST['enrollment_id']]);
        if ($stmt->fetchColumn() > 0) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Student is already enrolled in this term with a different enrollment ID";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }        // Set up approved fields based on status
        $approved_by = null;
        $approved_date = null;        if ($_POST['status'] === 'approved') {
            if (empty($_POST['approved_by']) || empty($_POST['approved_date'])) {
                $enrollment_response['status'] = false;
                $enrollment_response['message'] = "Approved by and approved date are required for approved status";
                $_SESSION['enrollment_response'] = $enrollment_response;
                header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
                exit;
            }
            $approved_by = $_POST['approved_by'];
            $approved_date = $_POST['approved_date'];
            
            // Validate that at least one class is selected for approved enrollments
            if (empty($_POST['selected_classes']) || !is_array($_POST['selected_classes'])) {
                $enrollment_response['status'] = false;
                $enrollment_response['message'] = "Please select at least one class for approved enrollments";
                $_SESSION['enrollment_response'] = $enrollment_response;
                header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
                exit;
            }
        }

        // Prepare and execute UPDATE statement
        $stmt = $pdo->prepare("
            UPDATE enrollments SET
                student_id = ?,
                term_id = ?,
                enrollment_date = ?,
                status = ?,
                approved_by = ?,
                approved_date = ?,
                notes = ?
            WHERE enrollment_id = ?
        ");

        $success = $stmt->execute([
            $_POST['student_id'],
            $_POST['term_id'],
            $_POST['enrollment_date'],
            $_POST['status'],
            $approved_by,
            $approved_date,
            $_POST['notes'] ?? null,
            $_POST['enrollment_id']
        ]);        if (!$success) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Failed to update enrollment record";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Handle enrollment details (classes)
        $updatedDetails = 0;
        
        // First, delete all existing enrollment details for this enrollment
        $deleteStmt = $pdo->prepare("DELETE FROM enrollment_details WHERE enrollment_id = ?");
        $deleteStmt->execute([$_POST['enrollment_id']]);
        
        // Then, add new enrollment details if status is approved and classes are selected
        if ($_POST['status'] === 'approved' && !empty($_POST['selected_classes']) && is_array($_POST['selected_classes'])) {
            foreach ($_POST['selected_classes'] as $classId) {
                // Validate that the class exists and belongs to the selected term
                $classStmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_id = ? AND term_id = ?");
                $classStmt->execute([$classId, $_POST['term_id']]);
                
                if ($classStmt->fetch()) {
                    // Generate enrollment detail ID
                    $detailID = generateEnrollmentDetailID();
                    
                    // Insert enrollment detail
                    $detailStmt = $pdo->prepare("
                        INSERT INTO enrollment_details 
                        (detail_id, enrollment_id, class_id, status, date_added)
                        VALUES (?, ?, ?, 'enrolled', CURRENT_TIMESTAMP)
                    ");
                    
                    if ($detailStmt->execute([$detailID, $_POST['enrollment_id'], $classId])) {
                        $updatedDetails++;
                    }
                }
            }
        }        // Commit the transaction
        $pdo->commit();

        // Set success response
        if ($_POST['status'] === 'approved' && $updatedDetails > 0) {
            $enrollment_response = [
                'status' => true,
                'message' => "Enrollment record {$_POST['enrollment_id']} updated successfully with $updatedDetails class(es)."
            ];
        } else {
            $enrollment_response = [
                'status' => true,
                'message' => "Enrollment record {$_POST['enrollment_id']} updated successfully."
            ];
        }

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;

    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();

        // Set error response
        $enrollment_response = [
            'status' => false,
            'message' => $e->getMessage()
        ];

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;
    }
}

/**
 * Get enrollment details for a specific enrollment ID
 */
function handleGetEnrollment()
{
    global $pdo;

    // Return JSON response
    header('Content-Type: application/json');

    try {
        if (empty($_GET['id'])) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Enrollment ID is required";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        $enrollmentID = $_GET['id'];

        // Get enrollment details with related information
        $stmt = $pdo->prepare("
            SELECT 
                e.*,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                t.term_name,
                CONCAT(t.start_date, ' - ', t.end_date) AS term_period,
                CONCAT(st.first_name, ' ', st.last_name) AS staff_name
            FROM enrollments e
            LEFT JOIN students s ON e.student_id = s.student_id
            LEFT JOIN terms t ON e.term_id = t.term_id
            LEFT JOIN staff st ON e.approved_by = st.staff_id
            WHERE e.enrollment_id = ?
        ");

        $stmt->execute([$enrollmentID]);        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$enrollment) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Enrollment not found";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Get enrollment details (classes) for this enrollment
        $detailsStmt = $pdo->prepare("
            SELECT 
                ed.detail_id,
                ed.class_id,
                ed.status as detail_status,
                c.course_id,
                c.section,
                co.course_code,
                co.course_name
            FROM enrollment_details ed
            LEFT JOIN classes c ON ed.class_id = c.class_id
            LEFT JOIN courses co ON c.course_id = co.course_id
            WHERE ed.enrollment_id = ?
        ");
        
        $detailsStmt->execute([$enrollmentID]);
        $enrollmentDetails = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $enrollment['enrollment_details'] = $enrollmentDetails;

        echo json_encode(['success' => true, 'enrollment' => $enrollment]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

/**
 * Approve an enrollment record
 */
function handleApproveEnrollment()
{
    global $pdo;

    // Create enrollment response array for notifications
    $enrollment_response = [
        'status' => false,
        'message' => ''
    ];

    // Start transaction
    $pdo->beginTransaction();

    try {        // Validate required fields
        if (empty($_POST['enrollment_id']) || empty($_POST['approved_by']) || empty($_POST['approved_date'])) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "All required fields must be filled";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Update enrollment to approved status
        $stmt = $pdo->prepare("
            UPDATE enrollments SET
                status = 'approved',
                approved_by = ?,
                approved_date = ?,
                notes = CASE WHEN ? IS NOT NULL AND ? != '' THEN ? ELSE notes END
            WHERE enrollment_id = ? AND status = 'pending'
        ");

        $success = $stmt->execute([
            $_POST['approved_by'],
            $_POST['approved_date'],
            $_POST['notes'],
            $_POST['notes'],
            $_POST['notes'],
            $_POST['enrollment_id']
        ]);

        if ($stmt->rowCount() === 0) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Enrollment not found or already processed";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Commit the transaction
        $pdo->commit();

        // Set success response
        $enrollment_response = [
            'status' => true,
            'message' => "Enrollment {$_POST['enrollment_id']} has been approved successfully"
        ];

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;

    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();

        // Set error response
        $enrollment_response = [
            'status' => false,
            'message' => $e->getMessage()
        ];

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;
    }
}

/**
 * Reject an enrollment record
 */
function handleRejectEnrollment()
{
    global $pdo;

    // Create enrollment response array for notifications
    $enrollment_response = [
        'status' => false,
        'message' => ''
    ];

    // Start transaction
    $pdo->beginTransaction();

    try {        // Validate required fields
        if (empty($_POST['enrollment_id']) || empty($_POST['approved_date']) || empty($_POST['notes'])) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "All required fields must be filled";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Update enrollment to rejected status
        $stmt = $pdo->prepare("
            UPDATE enrollments SET
                status = 'rejected',
                notes = ?
            WHERE enrollment_id = ? AND status = 'pending'
        ");

        $success = $stmt->execute([
            $_POST['notes'],
            $_POST['enrollment_id']
        ]);

        if ($stmt->rowCount() === 0) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Enrollment not found or already processed";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Commit the transaction
        $pdo->commit();

        // Set success response
        $enrollment_response = [
            'status' => true,
            'message' => "Enrollment {$_POST['enrollment_id']} has been rejected"
        ];

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;

    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();

        // Set error response
        $enrollment_response = [
            'status' => false,
            'message' => $e->getMessage()
        ];

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;    }
}

/**
 * Delete an enrollment record and all associated enrollment details
 */
function handleDeleteEnrollment()
{
    global $pdo;

    // Create enrollment response array for notifications
    $enrollment_response = [
        'status' => false,
        'message' => ''
    ];

    // Start transaction
    $pdo->beginTransaction();

    try {        
        // Validate required fields
        if (empty($_POST['enrollment_id']) || empty($_POST['deletion_reason'])) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "All required fields must be filled";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        $enrollmentId = $_POST['enrollment_id'];
        $deletionReason = $_POST['deletion_reason'];

        // Check if enrollment exists
        $checkStmt = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE enrollment_id = ?");
        $checkStmt->execute([$enrollmentId]);
        
        if (!$checkStmt->fetch()) {
            $enrollment_response['status'] = false;
            $enrollment_response['message'] = "Enrollment not found";
            $_SESSION['enrollment_response'] = $enrollment_response;
            header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
            exit;
        }

        // Get count of enrollment details that will be deleted
        $detailCountStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_details WHERE enrollment_id = ?");
        $detailCountStmt->execute([$enrollmentId]);
        $detailCount = $detailCountStmt->fetchColumn();

        // Delete all enrollment details first (due to foreign key constraints)
        $deleteDetailsStmt = $pdo->prepare("DELETE FROM enrollment_details WHERE enrollment_id = ?");
        $deleteDetailsSuccess = $deleteDetailsStmt->execute([$enrollmentId]);
        
        if (!$deleteDetailsSuccess) {
            throw new Exception("Failed to delete enrollment details");
        }

        // Delete the enrollment record
        $deleteEnrollmentStmt = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
        $deleteEnrollmentSuccess = $deleteEnrollmentStmt->execute([$enrollmentId]);
        
        if (!$deleteEnrollmentSuccess || $deleteEnrollmentStmt->rowCount() === 0) {
            throw new Exception("Failed to delete enrollment record");
        }

        // Log the deletion activity (if you have an activity log)
        try {
            $logID = generateNextID('AL'); // Assuming you have this function
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (log_id, user_id, activity_type, description, ip_address) 
                VALUES (?, ?, 'enrollment_deletion', ?, ?)
            ");
            $logStmt->execute([
                $logID,
                $_SESSION['user_id'] ?? null,
                "Deleted enrollment $enrollmentId with $detailCount enrollment details. Reason: $deletionReason",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $logError) {
            // Log error but don't fail the deletion
            error_log("Failed to log enrollment deletion: " . $logError->getMessage());
        }

        // Commit the transaction
        $pdo->commit();

        // Set success response
        $enrollment_response = [
            'status' => true,
            'message' => "Enrollment $enrollmentId and $detailCount related class enrollments have been permanently deleted"
        ];

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;

    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();

        // Set error response
        $enrollment_response = [
            'status' => false,
            'message' => $e->getMessage()
        ];

        // Store the response in session
        $_SESSION['enrollment_response'] = $enrollment_response;

        // Redirect
        header("Location: views/admin/dashboard.php?page=Manage&subpage=Enrollment");
        exit;
    }
}

/**
 * Get enrollments list with filtering options
 * This function should be called from index.php to get filtered enrollments
 */
function handleGetEnrollments()
{
    global $pdo;

    try {
        // Base query with joins for related information
        $baseQuery = "
            SELECT 
                e.*,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                t.term_name,
                CONCAT(st.first_name, ' ', st.last_name) AS staff_name
            FROM enrollments e
            LEFT JOIN students s ON e.student_id = s.student_id
            LEFT JOIN terms t ON e.term_id = t.term_id
            LEFT JOIN staff st ON e.approved_by = st.staff_id
        ";

        $whereConditions = [];
        $params = [];

        // Apply filters if provided
        if (!empty($_GET['term'])) {
            $whereConditions[] = "e.term_id = ?";
            $params[] = $_GET['term'];
        }

        if (!empty($_GET['status'])) {
            $whereConditions[] = "e.status = ?";
            $params[] = $_GET['status'];
        }

        if (!empty($_GET['search'])) {
            $search = "%" . $_GET['search'] . "%";
            $whereConditions[] = "(e.enrollment_id LIKE ? OR e.student_id LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        // Add WHERE clause if there are conditions
        if (!empty($whereConditions)) {
            $baseQuery .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // Add ordering
        $baseQuery .= " ORDER BY e.enrollment_date DESC";

        // Pagination
        $page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
        $limit = 10; // Items per page
        $offset = ($page - 1) * $limit;

        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) FROM (" . $baseQuery . ") as filtered";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalEnrollments = $stmtCount->fetchColumn();

        // Add limit and offset for pagination
        $baseQuery .= " LIMIT $limit OFFSET $offset";

        // Execute the final query
        $stmt = $pdo->prepare($baseQuery);
        $stmt->execute($params);
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If this is an AJAX request, return JSON
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'enrollments' => $enrollments,
                'totalEnrollments' => $totalEnrollments,
                'currentPage' => $page,
                'totalPages' => ceil($totalEnrollments / $limit)
            ]);
            exit;
        }

        // Otherwise, this is for the main page load
        // Return the variables to be used in the view
        return [
            'enrollments' => $enrollments,
            'totalEnrollments' => $totalEnrollments,
            'pagination' => generatePagination($page, ceil($totalEnrollments / $limit))
        ];

    } catch (Exception $e) {
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }

        // For page load errors
        return [
            'enrollments' => [],
            'totalEnrollments' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @return string HTML for the pagination controls
 */
function generatePagination($currentPage, $totalPages)
{
    if ($totalPages <= 1) {
        return ''; // No pagination needed
    }

    $html = '<ul class="pagination pagination-sm mb-0">';

    // Previous button
    $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
    $html .= '<li class="page-item ' . $prevDisabled . '">
        <a class="page-link" href="?page=enrollment&page_num=' . ($currentPage - 1) . '" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
        </a>
    </li>';

    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $startPage + 4);

    if ($endPage - $startPage < 4) {
        $startPage = max(1, $endPage - 4);
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">
            <a class="page-link" href="?page=enrollment&page_num=' . $i . '">' . $i . '</a>
        </li>';
    }

    // Next button
    $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $html .= '<li class="page-item ' . $nextDisabled . '">
        <a class="page-link" href="?page=enrollment&page_num=' . ($currentPage + 1) . '" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
        </a>
    </li>';

    $html .= '</ul>';    return $html;
}

/**
 * Handle getting available programs for enrollment
 */
function handleGetPrograms() {
    global $pdo;
    
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->prepare("
            SELECT program_id, program_code, program_name, description 
            FROM programs 
            WHERE status = 'active' 
            ORDER BY program_name
        ");
        $stmt->execute();
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'programs' => $programs
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching programs: ' . $e->getMessage()
        ]);
    }
    exit;
}

/**
 * Handle new student enrollment submission
 */
function handleSubmitEnrollment() {
    global $pdo;
    
    header('Content-Type: application/json');
    
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'date_of_birth', 'gender', 'program_id', 'year_level', 'password', 'confirm_password'];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'All required fields must be filled.'
                ]);
                exit;
            }
        }
        
        // Validate password confirmation
        if ($_POST['password'] !== $_POST['confirm_password']) {
            echo json_encode([
                'success' => false,
                'message' => 'Passwords do not match.'
            ]);
            exit;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Email address is already registered.'
            ]);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Generate IDs
        $user_id = generateNextID('US');
        $student_id = generateNextID('ST');
        $login_id = generateNextID('ST'); // Use student format for login
        
        // Hash password
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Insert into users table
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, login_id, password, role, status)
            VALUES (?, ?, ?, 'student', 'active')
        ");
        $stmt->execute([$user_id, $login_id, $hashed_password]);
        
        // Insert into students table
        $stmt = $pdo->prepare("
            INSERT INTO students (
                student_id, user_id, first_name, last_name, date_of_birth, gender,
                address, city, state, postal_code, phone_number, email,
                emergency_contact_name, emergency_contact_phone, program_id, year_level,
                enrollment_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        
        $stmt->execute([
            $student_id,
            $user_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['address'] ?? null,
            $_POST['city'] ?? null,
            $_POST['state'] ?? null,
            $_POST['postal_code'] ?? null,
            $_POST['phone_number'] ?? null,
            $_POST['email'],
            $_POST['emergency_contact_name'] ?? null,
            $_POST['emergency_contact_phone'] ?? null,
            $_POST['program_id'],
            $_POST['year_level']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Registration successful! Your Student ID is: $student_id. You can now login with ID: $login_id"
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage()
        ]);
    }
    exit;
}
?>