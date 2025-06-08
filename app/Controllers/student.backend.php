<?php
/**
 * Student Backend Controller
 * Handles all student operations (add, update, delete, view)
 * 
 * @param PDO $pdo Database connection
 * @param string $action Action to perform (add, update, delete, view)
 * @param array $data Form data for add/update operations
 * @param string $id Student ID for specific operations
 * @return array Response with status and message
 */

function data_Student($pdo)
{
    try {
        // Build SQL query for fetching students
        $sql = "
            SELECT 
                s.student_id,
                s.user_id,
                s.first_name,
                s.last_name,
                CONCAT(s.first_name, ' ', s.last_name) AS full_name,
                s.date_of_birth,
                s.gender,
                s.address,
                s.city,
                s.state,
                s.postal_code,
                s.country,
                s.phone_number,
                s.email,
                s.emergency_contact_name,
                s.emergency_contact_phone,
                s.program_id,
                s.year_level,
                s.enrollment_date,
                s.academic_advisor_id,
                p.program_name,
                p.program_code,
                u.login_id,
                u.status,
                u.last_login,
                CONCAT(adv.first_name, ' ', adv.last_name) AS advisor_name
            FROM 
                students s
            LEFT JOIN 
                users u ON s.user_id = u.user_id
            LEFT JOIN 
                programs p ON s.program_id = p.program_id
            LEFT JOIN 
                staff adv ON s.academic_advisor_id = adv.staff_id
            WHERE 1=1
        ";

        $params = [];

        // Apply search filter if set
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $sql .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search OR u.login_id LIKE :search OR s.email LIKE :search)";
            $params['search'] = $search;
        }

        // Apply program filter if set
        if (isset($_GET['program']) && $_GET['program'] !== '') {
            $sql .= " AND s.program_id = :program";
            $params['program'] = $_GET['program'];
        }

        // Apply year level filter if set
        if (isset($_GET['year_level']) && $_GET['year_level'] !== '') {
            $sql .= " AND s.year_level = :year_level";
            $params['year_level'] = $_GET['year_level'];
        }

        // Apply status filter if set
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $sql .= " AND u.status = :status";
            $params['status'] = $_GET['status'];
        }

        // Apply gender filter if set
        if (isset($_GET['gender']) && $_GET['gender'] !== '') {
            $sql .= " AND s.gender = :gender";
            $params['gender'] = $_GET['gender'];
        }

        // Add ordering
        $sql .= " ORDER BY s.first_name, s.last_name";

        // Log the query for debugging
        error_log("Student SQL query: " . $sql);
        error_log("Parameters: " . json_encode($params));

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'status' => true,
            'data' => $students
        ];

    } catch (Exception $e) {
        error_log("Error in data_Student: " . $e->getMessage());
        return [
            'status' => false,
            'message' => 'Error fetching student data: ' . $e->getMessage()
        ];
    }
}

function action_Student($pdo, $action, $data = [], $id = null)
{
    $response = ['status' => false, 'message' => ''];

    try {
        switch ($action) {
            case 'add':
                // Validate required fields
                if (empty($data['first_name']) || empty($data['last_name']) || 
                    empty($data['email']) || empty($data['program_id'])) {
                    $response['message'] = "First name, last name, email, and program are required.";
                    return $response;
                }

                // Check if email already exists
                $checkEmailStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = :email");
                $checkEmailStmt->execute(['email' => $data['email']]);
                if ($checkEmailStmt->fetchColumn() > 0) {
                    $response['message'] = "Email already exists.";
                    return $response;
                }

                // Generate IDs
                $user_id = generateNextID('US');
                $student_id = generateNextID('ST');
                $login_id = generateNextID('ST'); // Login ID follows student ID format

                // Default password (can be changed later)
                $default_password = password_hash('student123', PASSWORD_DEFAULT);

                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Insert into users table first
                    $userSql = "INSERT INTO users (user_id, login_id, password, role, status) 
                               VALUES (:user_id, :login_id, :password, 'student', :status)";
                    $userStmt = $pdo->prepare($userSql);
                    $userStmt->execute([
                        'user_id' => $user_id,
                        'login_id' => $login_id,
                        'password' => $default_password,
                        'status' => isset($data['status']) ? $data['status'] : 'active'
                    ]);

                    // Insert into students table
                    $studentSql = "INSERT INTO students (
                        student_id, user_id, first_name, last_name, date_of_birth,
                        gender, address, city, state, postal_code, country,
                        phone_number, email, emergency_contact_name, emergency_contact_phone,
                        program_id, year_level, enrollment_date, academic_advisor_id
                    ) VALUES (
                        :student_id, :user_id, :first_name, :last_name, :date_of_birth,
                        :gender, :address, :city, :state, :postal_code, :country,
                        :phone_number, :email, :emergency_contact_name, :emergency_contact_phone,
                        :program_id, :year_level, :enrollment_date, :academic_advisor_id
                    )";

                    $studentStmt = $pdo->prepare($studentSql);
                    $studentStmt->execute([
                        'student_id' => $student_id,
                        'user_id' => $user_id,
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                        'gender' => !empty($data['gender']) ? $data['gender'] : null,
                        'address' => !empty($data['address']) ? $data['address'] : null,
                        'city' => !empty($data['city']) ? $data['city'] : null,
                        'state' => !empty($data['state']) ? $data['state'] : null,
                        'postal_code' => !empty($data['postal_code']) ? $data['postal_code'] : null,
                        'country' => !empty($data['country']) ? $data['country'] : 'Philippines',
                        'phone_number' => !empty($data['phone_number']) ? $data['phone_number'] : null,
                        'email' => $data['email'],
                        'emergency_contact_name' => !empty($data['emergency_contact_name']) ? $data['emergency_contact_name'] : null,
                        'emergency_contact_phone' => !empty($data['emergency_contact_phone']) ? $data['emergency_contact_phone'] : null,
                        'program_id' => $data['program_id'],
                        'year_level' => !empty($data['year_level']) ? $data['year_level'] : 1,
                        'enrollment_date' => !empty($data['enrollment_date']) ? $data['enrollment_date'] : date('Y-m-d'),
                        'academic_advisor_id' => !empty($data['academic_advisor_id']) ? $data['academic_advisor_id'] : null
                    ]);

                    // Log the action
                    if (isset($_SESSION['user_id'])) {
                        $logID = generateNextID('LG');
                        $logSql = "INSERT INTO activity_logs (log_id, user_id, activity_type, description) 
                                  VALUES (:log_id, :user_id, :activity_type, :description)";
                        $logStmt = $pdo->prepare($logSql);
                        $logStmt->execute([
                            'log_id' => $logID,
                            'user_id' => $_SESSION['user_id'],
                            'activity_type' => 'student_add',
                            'description' => "Added new student: {$data['first_name']} {$data['last_name']} (ID: $student_id)"
                        ]);
                    }

                    $pdo->commit();
                    $response['status'] = true;
                    $response['message'] = "Student added successfully. Login ID: " . $login_id;
                    $response['student_id'] = $student_id;
                    $response['login_id'] = $login_id;

                } catch (Exception $e) {
                    $pdo->rollback();
                    throw $e;
                }
                break;

            case 'update':
                // Validate required fields
                if (empty($data['student_id']) || empty($data['first_name']) || 
                    empty($data['last_name']) || empty($data['email'])) {
                    $response['message'] = "Student ID, first name, last name, and email are required.";
                    return $response;
                }

                // Check if email already exists for other students
                $checkEmailStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = :email AND student_id != :student_id");
                $checkEmailStmt->execute(['email' => $data['email'], 'student_id' => $data['student_id']]);
                if ($checkEmailStmt->fetchColumn() > 0) {
                    $response['message'] = "Email already exists for another student.";
                    return $response;
                }

                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Update students table
                    $studentSql = "UPDATE students SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        date_of_birth = :date_of_birth,
                        gender = :gender,
                        address = :address,
                        city = :city,
                        state = :state,
                        postal_code = :postal_code,
                        country = :country,
                        phone_number = :phone_number,
                        email = :email,
                        emergency_contact_name = :emergency_contact_name,
                        emergency_contact_phone = :emergency_contact_phone,
                        program_id = :program_id,
                        year_level = :year_level,
                        academic_advisor_id = :academic_advisor_id
                    WHERE student_id = :student_id";

                    $studentStmt = $pdo->prepare($studentSql);
                    $studentStmt->execute([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                        'gender' => !empty($data['gender']) ? $data['gender'] : null,
                        'address' => !empty($data['address']) ? $data['address'] : null,
                        'city' => !empty($data['city']) ? $data['city'] : null,
                        'state' => !empty($data['state']) ? $data['state'] : null,
                        'postal_code' => !empty($data['postal_code']) ? $data['postal_code'] : null,
                        'country' => !empty($data['country']) ? $data['country'] : 'Philippines',
                        'phone_number' => !empty($data['phone_number']) ? $data['phone_number'] : null,
                        'email' => $data['email'],
                        'emergency_contact_name' => !empty($data['emergency_contact_name']) ? $data['emergency_contact_name'] : null,
                        'emergency_contact_phone' => !empty($data['emergency_contact_phone']) ? $data['emergency_contact_phone'] : null,
                        'program_id' => $data['program_id'],
                        'year_level' => !empty($data['year_level']) ? $data['year_level'] : 1,
                        'academic_advisor_id' => !empty($data['academic_advisor_id']) ? $data['academic_advisor_id'] : null,
                        'student_id' => $data['student_id']
                    ]);

                    // Update user status if provided
                    if (isset($data['status'])) {
                        $userSql = "UPDATE users u 
                                   JOIN students s ON u.user_id = s.user_id 
                                   SET u.status = :status 
                                   WHERE s.student_id = :student_id";
                        $userStmt = $pdo->prepare($userSql);
                        $userStmt->execute([
                            'status' => $data['status'],
                            'student_id' => $data['student_id']
                        ]);
                    }

                    // Log the action
                    if (isset($_SESSION['user_id'])) {
                        $logID = generateNextID('LG');
                        $logSql = "INSERT INTO activity_logs (log_id, user_id, activity_type, description) 
                                  VALUES (:log_id, :user_id, :activity_type, :description)";
                        $logStmt = $pdo->prepare($logSql);
                        $logStmt->execute([
                            'log_id' => $logID,
                            'user_id' => $_SESSION['user_id'],
                            'activity_type' => 'student_update',
                            'description' => "Updated student: {$data['first_name']} {$data['last_name']} (ID: {$data['student_id']})"
                        ]);
                    }

                    $pdo->commit();
                    $response['status'] = true;
                    $response['message'] = "Student updated successfully.";

                } catch (Exception $e) {
                    $pdo->rollback();
                    throw $e;
                }
                break;

            case 'delete':
                if (empty($id)) {
                    $response['message'] = "Student ID is required for deletion.";
                    return $response;
                }

                // Check if student exists
                $checkStmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM students WHERE student_id = :student_id");
                $checkStmt->execute(['student_id' => $id]);
                $student = $checkStmt->fetch();

                if (!$student) {
                    $response['message'] = "Student not found.";
                    return $response;
                }

                // Check if student has enrollments
                $enrollmentCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :student_id");
                $enrollmentCheckStmt->execute(['student_id' => $id]);
                $enrollmentCount = $enrollmentCheckStmt->fetchColumn();

                if ($enrollmentCount > 0) {
                    $response['message'] = "Cannot delete student. Student has enrollment records.";
                    return $response;
                }                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Log the action BEFORE deleting (while user still exists)
                    if (isset($_SESSION['user_id'])) {
                        $logID = generateNextID('LG');
                        $logSql = "INSERT INTO activity_logs (log_id, user_id, activity_type, description) 
                                  VALUES (:log_id, :user_id, :activity_type, :description)";
                        $logStmt = $pdo->prepare($logSql);
                        $logStmt->execute([
                            'log_id' => $logID,
                            'user_id' => $_SESSION['user_id'],
                            'activity_type' => 'student_delete',
                            'description' => "Deleted student: {$student['first_name']} {$student['last_name']} (ID: $id)"
                        ]);
                    }

                    // Delete related records to avoid foreign key constraint violations
                    
                    // Delete notifications for this user
                    $deleteNotificationsStmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = :user_id");
                    $deleteNotificationsStmt->execute(['user_id' => $student['user_id']]);
                    
                    // Delete activity logs for this user
                    $deleteLogsStmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = :user_id");
                    $deleteLogsStmt->execute(['user_id' => $student['user_id']]);

                    // Delete from students table first
                    $deleteStudentStmt = $pdo->prepare("DELETE FROM students WHERE student_id = :student_id");
                    $deleteStudentStmt->execute(['student_id' => $id]);

                    // Delete from users table
                    $deleteUserStmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                    $deleteUserStmt->execute(['user_id' => $student['user_id']]);

                    $pdo->commit();
                    $response['status'] = true;
                    $response['message'] = "Student deleted successfully.";

                } catch (Exception $e) {
                    $pdo->rollback();
                    throw $e;
                }
                break;

            case 'view':
                if (empty($id)) {
                    $response['message'] = "Student ID is required.";
                    return $response;
                }

                $sql = "
                    SELECT 
                        s.*,
                        u.login_id,
                        u.status,
                        u.last_login,
                        u.created_at as user_created_at,
                        p.program_name,
                        p.program_code,
                        p.department as program_department,
                        CONCAT(adv.first_name, ' ', adv.last_name) AS advisor_name,
                        adv.email as advisor_email,
                        adv.phone_number as advisor_phone
                    FROM students s
                    LEFT JOIN users u ON s.user_id = u.user_id
                    LEFT JOIN programs p ON s.program_id = p.program_id
                    LEFT JOIN staff adv ON s.academic_advisor_id = adv.staff_id
                    WHERE s.student_id = :student_id
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute(['student_id' => $id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($student) {
                    $response['status'] = true;
                    $response['data'] = $student;
                } else {
                    $response['message'] = "Student not found.";
                }
                break;

            case 'reset_password':
                if (empty($id)) {
                    $response['message'] = "Student ID is required.";
                    return $response;
                }

                // Get user_id and student name from student_id
                $getUserStmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM students WHERE student_id = :student_id");
                $getUserStmt->execute(['student_id' => $id]);
                $student = $getUserStmt->fetch();

                if (!$student) {
                    $response['message'] = "Student not found.";
                    return $response;
                }

                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Reset password to default
                    $new_password = password_hash('student123', PASSWORD_DEFAULT);
                    $resetStmt = $pdo->prepare("UPDATE users SET password = :password WHERE user_id = :user_id");
                    $resetStmt->execute([
                        'password' => $new_password,
                        'user_id' => $student['user_id']
                    ]);

                    // Log the action
                    if (isset($_SESSION['user_id'])) {
                        $logID = generateNextID('LG');
                        $logSql = "INSERT INTO activity_logs (log_id, user_id, activity_type, description) 
                                  VALUES (:log_id, :user_id, :activity_type, :description)";
                        $logStmt = $pdo->prepare($logSql);
                        $logStmt->execute([
                            'log_id' => $logID,
                            'user_id' => $_SESSION['user_id'],
                            'activity_type' => 'student_password_reset',
                            'description' => "Reset password for student: {$student['first_name']} {$student['last_name']} (ID: $id)"
                        ]);
                    }

                    $pdo->commit();
                    $response['status'] = true;
                    $response['message'] = "Password reset successfully. New password: student123";

                } catch (Exception $e) {
                    $pdo->rollback();
                    throw $e;
                }
                break;

            default:
                $response['message'] = "Invalid action specified.";
        }

    } catch (Exception $e) {
        error_log("Error in action_Student: " . $e->getMessage());
        $response['message'] = "An error occurred: " . $e->getMessage();
    }

    return $response;
}

/**
 * Process student requests (add, update, delete)
 * 
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Student_Request($pdo)
{
    $response = [
        'status' => false,
        'message' => ''
    ];

    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['subpage']) && $_GET['subpage'] === 'Student') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        if ($action === 'add' || $action === 'update') {
            $response = action_Student($pdo, $action, $_POST);
            
            // If AJAX request, return JSON and exit
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
    }

    // Process GET actions (delete, reset_password, view)
    if (
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        isset($_GET['subpage']) && $_GET['subpage'] === 'Student' &&
        isset($_GET['action']) && isset($_GET['id'])
    ) {
        $action = $_GET['action'];
        $id = $_GET['id'];

        if ($action === 'delete' || $action === 'reset_password') {
            $response = action_Student($pdo, $action, [], $id);
            
            // If AJAX request, return JSON and exit
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        } elseif ($action === 'view') {
            $response = action_Student($pdo, $action, [], $id);
            
            // For view action, we don't want to show it as a message in regular requests
            if ($response['status'] && !$isAjax) {
                $response['message'] = '';
            }
            
            // If AJAX request, return JSON and exit
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
    }

    return $response;
}

function getStudentEnrollments($pdo, $student_id)
{
    try {
        $sql = "
            SELECT 
                e.enrollment_id,
                e.student_id,
                e.term_id,
                e.enrollment_date,
                e.status as enrollment_status,
                e.approved_by,
                e.approved_date,
                e.notes,
                t.term_name,
                t.academic_year,
                t.start_date as term_start,
                t.end_date as term_end,
                CONCAT(staff.first_name, ' ', staff.last_name) as approved_by_name
            FROM enrollments e
            LEFT JOIN terms t ON e.term_id = t.term_id
            LEFT JOIN staff ON e.approved_by = staff.staff_id
            WHERE e.student_id = :student_id
            ORDER BY e.enrollment_date DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['student_id' => $student_id]);
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status' => true,
            'data' => $enrollments
        ];

    } catch (Exception $e) {
        error_log("Error in getStudentEnrollments: " . $e->getMessage());
        return [
            'status' => false,
            'message' => 'Error fetching enrollment data: ' . $e->getMessage()
        ];
    }
}

function getStudentGrades($pdo, $student_id, $term_id = null)
{
    try {
        $sql = "
            SELECT 
                ed.detail_id,
                ed.enrollment_id,
                ed.class_id,
                ed.status,
                ed.grade,
                ed.numeric_grade,
                ed.remarks,
                c.course_id,
                cr.course_code,
                cr.course_name,
                cr.units,
                cl.section,
                t.term_name,
                t.academic_year,
                CONCAT(staff.first_name, ' ', staff.last_name) as instructor_name
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses cr ON cl.course_id = cr.course_id
            JOIN terms t ON cl.term_id = t.term_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            WHERE e.student_id = :student_id
        ";

        $params = ['student_id' => $student_id];

        if ($term_id) {
            $sql .= " AND cl.term_id = :term_id";
            $params['term_id'] = $term_id;
        }

        $sql .= " ORDER BY t.academic_year DESC, t.term_name, cr.course_code";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status' => true,
            'data' => $grades
        ];

    } catch (Exception $e) {
        error_log("Error in getStudentGrades: " . $e->getMessage());
        return [
            'status' => false,
            'message' => 'Error fetching grade data: ' . $e->getMessage()
        ];
    }
}

function getStudentBilling($pdo, $student_id)
{
    try {
        $sql = "
            SELECT 
                b.billing_id,
                b.student_id,
                b.term_id,
                b.amount,
                b.due_date,
                b.description,
                b.created_at,
                b.status,
                t.term_name,
                t.academic_year,
                CONCAT(staff.first_name, ' ', staff.last_name) as created_by_name,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (b.amount - COALESCE(SUM(p.amount), 0)) as balance
            FROM billings b
            LEFT JOIN terms t ON b.term_id = t.term_id
            LEFT JOIN staff ON b.created_by = staff.staff_id
            LEFT JOIN payments p ON b.billing_id = p.billing_id
            WHERE b.student_id = :student_id
            GROUP BY b.billing_id
            ORDER BY b.due_date DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['student_id' => $student_id]);
        $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status' => true,
            'data' => $billings
        ];

    } catch (Exception $e) {
        error_log("Error in getStudentBilling: " . $e->getMessage());
        return [
            'status' => false,
            'message' => 'Error fetching billing data: ' . $e->getMessage()
        ];
    }
}

// Helper function to get available programs for dropdowns
function getPrograms($pdo)
{
    try {
        $sql = "SELECT program_id, program_code, program_name, department 
                FROM programs 
                WHERE status = 'active' 
                ORDER BY program_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status' => true,
            'data' => $programs
        ];

    } catch (Exception $e) {
        error_log("Error in getPrograms: " . $e->getMessage());
        return [
            'status' => false,
            'message' => 'Error fetching programs: ' . $e->getMessage()
        ];
    }
}

// Helper function to get available advisors for dropdowns
function getAdvisors($pdo)
{
    try {
        $sql = "SELECT staff_id, CONCAT(first_name, ' ', last_name) as name, 
                       department, position, email
                FROM staff 
                WHERE position LIKE '%advisor%' OR position LIKE '%professor%'
                ORDER BY first_name, last_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status' => true,
            'data' => $advisors
        ];

    } catch (Exception $e) {
        error_log("Error in getAdvisors: " . $e->getMessage());
        return [
            'status' => false,
            'message' => 'Error fetching advisors: ' . $e->getMessage()
        ];
    }
}

?>