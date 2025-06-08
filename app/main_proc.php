<?php
include "database.php"; // This now provides a $pdo connection object

// Function to get the effective subpage (considering defaults)
function getEffectiveSubpage($page, $subpage) {
    // Define default subpages for each page
    $pageDefaults = [
        'Manage' => 'Announcement',
        // Add other pages with defaults here as needed
    ];
    
    // If no subpage is specified and the page has a default, use it
    if (empty($subpage) && isset($pageDefaults[$page])) {
        return $pageDefaults[$page];
    }
    
    return $subpage;
}

// Function to load controllers specifically for student users
function loadStudentControllers($page, $subpage, $action) {
    // Student-specific page handling
    if (!empty($page)) {
        switch ($page) {
            case 'profile':
                // Load student profile management controller
                include_once "Controllers/student.backend.php";
                error_log("Loaded student.backend.php for profile management");
                break;
            case 'courses':
                // Load course and enrollment controllers for course browsing and enrollment
                include_once "Controllers/course.backend.php";
                include_once "Controllers/enrollment.backend.php";
                error_log("Loaded course.backend.php and enrollment.backend.php for course management");
                break;
            case 'grades':
                // Load grade controller for viewing grades and transcripts
                include_once "Controllers/grade.backend.php";
                error_log("Loaded grade.backend.php for grade viewing");
                break;
            case 'home':
            default:
                // For home/dashboard, load minimal controllers for basic functionality
                error_log("Loading minimal controllers for student home/dashboard");
                break;
        }
    }
    
    // Load controllers based on specific student actions
    if (!empty($action)) {
        // Profile-related actions
        if (strpos($action, 'profile') !== false || strpos($action, 'password') !== false) {
            include_once "Controllers/student.backend.php";
            error_log("Loaded student.backend.php for profile actions");
        }
        
        // Course-related actions (enrollment, viewing)
        if (strpos($action, 'course') !== false || strpos($action, 'enroll') !== false || strpos($action, 'drop') !== false) {
            include_once "Controllers/course.backend.php";
            include_once "Controllers/enrollment.backend.php";
            error_log("Loaded course and enrollment controllers for course actions");
        }
        
        // Grade-related actions
        if (strpos($action, 'grade') !== false || strpos($action, 'transcript') !== false) {
            include_once "Controllers/grade.backend.php";
            error_log("Loaded grade.backend.php for grade actions");
        }
        
        // Billing-related actions (view only for students)
        if (strpos($action, 'billing') !== false || strpos($action, 'payment') !== false) {
            include_once "Controllers/billing.backend.php";
            error_log("Loaded billing.backend.php for student billing view");
        }
        
        // Announcement viewing
        if (strpos($action, 'announcement') !== false) {
            include_once "Controllers/announcement.backend.php";
            error_log("Loaded announcement.backend.php for student announcement view");
        }
    }
    
    // Log optimization
    error_log("Student-specific controller loading completed");
}

// Function to conditionally include backend controllers based on current page/action
function loadRequiredControllers($pdo) {
    // Always load dashboard controller for user details and basic functions
    include_once "Controllers/dashboard.backend.php";
    
    // Get current page context
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    $rawSubpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
    
    // Get the effective subpage (considering defaults)
    $subpage = getEffectiveSubpage($page, $rawSubpage);
    
    // Get user role for optimization
    $userRole = getUserRole();
    
    // Log what we're loading for debugging
    error_log("Loading controllers for - Page: $page, Raw Subpage: $rawSubpage, Effective Subpage: $subpage, Action: $action, Role: $userRole");
      // Role-based optimization: Only load controllers relevant to the user's role
    if ($userRole === 'student') {
        // For students, only load student-relevant controllers
        loadStudentControllers($page, $subpage, $action);
        return;
    }
    
    // For admin/staff users, load full controller set based on subpage or actions
    loadAdminControllers($page, $subpage, $action);
}

// Function to load controllers for admin/staff users
function loadAdminControllers($page, $subpage, $action) {
    if (!empty($subpage)) {
        switch ($subpage) {
            case 'Announcement':
                include_once "Controllers/announcement.backend.php";
                error_log("Loaded announcement.backend.php");
                break;
            case 'Billing':
                include_once "Controllers/billing.backend.php";
                error_log("Loaded billing.backend.php");
                break;
            case 'Class':
                include_once "Controllers/class.backend.php";
                error_log("Loaded class.backend.php");
                break;
            case 'Course':
                include_once "Controllers/course.backend.php";
                error_log("Loaded course.backend.php");
                break;
            case 'Enrollment':
                include_once "Controllers/enrollment.backend.php";
                error_log("Loaded enrollment.backend.php");
                break;
            case 'Grade':
                include_once "Controllers/grade.backend.php";
                error_log("Loaded grade.backend.php");
                break;
            case 'Prof':
                include_once "Controllers/professors.backend.php";
                error_log("Loaded professors.backend.php");
                break;            case 'Student':
                include_once "Controllers/student.backend.php";
                error_log("Loaded student.backend.php");
                break;
        }
    }
    
    // Load specific controllers based on action if subpage is not set
    if (empty($subpage) && !empty($action)) {
        // Check action patterns to determine which controller to load
        if (strpos($action, 'grade') !== false || strpos($action, 'Grade') !== false) {
            include_once "Controllers/grade.backend.php";
            error_log("Loaded grade.backend.php based on action");
        }
        if (strpos($action, 'class') !== false || strpos($action, 'Class') !== false) {
            include_once "Controllers/class.backend.php";
            error_log("Loaded class.backend.php based on action");
        }
        if (strpos($action, 'course') !== false || strpos($action, 'Course') !== false) {
            include_once "Controllers/course.backend.php";
            error_log("Loaded course.backend.php based on action");
        }
        if (strpos($action, 'announcement') !== false || strpos($action, 'Announcement') !== false) {
            include_once "Controllers/announcement.backend.php";
            error_log("Loaded announcement.backend.php based on action");
        }
        if (strpos($action, 'billing') !== false || strpos($action, 'Billing') !== false) {
            include_once "Controllers/billing.backend.php";
            error_log("Loaded billing.backend.php based on action");
        }
        if (strpos($action, 'enrollment') !== false || strpos($action, 'Enrollment') !== false) {
            include_once "Controllers/enrollment.backend.php";
            error_log("Loaded enrollment.backend.php based on action");
        }
        if (strpos($action, 'professor') !== false || strpos($action, 'staff') !== false || strpos($action, 'Prof') !== false) {
            include_once "Controllers/professors.backend.php";
            error_log("Loaded professors.backend.php based on action");
        }
        if (strpos($action, 'student') !== false || strpos($action, 'Student') !== false) {
            include_once "Controllers/student.backend.php";
            error_log("Loaded student.backend.php based on action");
        }
    }    // Special case: If no specific page is detected, we might be on the login page
    // In that case, we don't need to load any specific controllers
    if (empty($page) && empty($subpage) && empty($action)) {
        error_log("No specific controllers needed - likely on login page");
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

// Load required controllers based on current page/action
loadRequiredControllers($pdo);

// Function to validate login format (XX-00000)
function validateLoginID($loginID)
{
    return preg_match('/^[A-Z0-9]{2}-\d{5}$/', $loginID);
}

// Function to sanitize input
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input));
}

// Function to check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to get user role
function getUserRole()
{
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Function to redirect based on role
function redirectBasedOnRole($role)
{
    switch ($role) {
        case 'student':
            header("Location: app/views/student/dashboard.php");
            break;
        case 'admin':
        case 'staff':
            header("Location: app/views/admin/dashboard.php");
            break;
        default:
            header("Location: index.php");
    }
    exit();
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_action'])) {
    $loginID = sanitizeInput($_POST['login_id']);
    $password = $_POST['password'];
    $error = "";

    // Validate login ID format
    if (!validateLoginID($loginID)) {
        $error = "Invalid login ID format. Please use XX-00000 format.";
    } else {
        // Check if user exists and password is correct
        $query = "SELECT u.user_id, u.login_id, u.password, u.role, u.status 
                  FROM users u 
                  WHERE u.login_id = :login_id";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['login_id' => $loginID]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if account is active
            if ($user['status'] != 'active') {
                $error = "Your account is " . $user['status'] . ". Please contact administration.";
            }
            // Verify password
            elseif (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['login_id'] = $user['login_id'];
                $_SESSION['role'] = $user['role'];

                // Update last login timestamp
                $updateQuery = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = :user_id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute(['user_id' => $user['user_id']]);

                // Log activity
                $activityType = "login";
                $description = "User logged in successfully";
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];

                $logID = generateNextID('LG');

                $logQuery = "INSERT INTO activity_logs (log_id, user_id, activity_type, description, ip_address, user_agent) 
                            VALUES (:log_id, :user_id, :activity_type, :description, :ip_address, :user_agent)";
                $logStmt = $pdo->prepare($logQuery);
                $logStmt->execute([
                    'log_id' => $logID,
                    'user_id' => $user['user_id'],
                    'activity_type' => $activityType,
                    'description' => $description,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ]);

                // Redirect based on role
                redirectBasedOnRole($user['role']);
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "User ID not found. Please check your credentials.";
        }
    }

    // If there was an error, store it in session to display on form
    if (!empty($error)) {
        $_SESSION['login_error'] = $error;
        header("Location: index.php");
        exit();
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    // Log activity before destroying session
    if (isLoggedIn()) {
        $activityType = "logout";
        $description = "User logged out";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $userID = $_SESSION['user_id'];

        $logID = generateNextID('LG');

        $logQuery = "INSERT INTO activity_logs (log_id, user_id, activity_type, description, ip_address, user_agent) 
                    VALUES (:log_id, :user_id, :activity_type, :description, :ip_address, :user_agent)";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([
            'log_id' => $logID,
            'user_id' => $userID,
            'activity_type' => $activityType,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    // Destroy session and redirect to login page
    session_unset();
    session_destroy();
    header("Location: ../../../../index.php");
    exit();
}

// Function to generate next ID with specific prefix
function generateNextID($prefix)
{
    global $pdo;

    // Determine table based on prefix
    $table = '';
    $idColumn = '';    switch ($prefix) {
        case 'US':
            $table = 'users';
            $idColumn = 'user_id';
            break;
        case 'ST':
            $table = 'students';
            $idColumn = 'student_id';
            break;
        case 'SF':
            $table = 'staff';
            $idColumn = 'staff_id';
            break;
        case 'LG':
            $table = 'activity_logs';
            $idColumn = 'log_id';
            break;
        case 'PG':
            $table = 'programs';
            $idColumn = 'program_id';
            break;
        case 'CR':
            $table = 'courses';
            $idColumn = 'course_id';
            break;
        case 'CL':
            $table = 'classes';
            $idColumn = 'class_id';
            break;
        case 'EN':
            $table = 'enrollments';
            $idColumn = 'enrollment_id';
            break;
        case 'ED':
            $table = 'enrollment_details';
            $idColumn = 'detail_id';
            break;
        case 'BL':
            $table = 'billings';
            $idColumn = 'billing_id';
            break;
        case 'PM':
            $table = 'payments';
            $idColumn = 'payment_id';
            break;
        case 'AN':
            $table = 'announcements';
            $idColumn = 'announcement_id';
            break;
        case 'TM':
            $table = 'terms';
            $idColumn = 'term_id';
            break;
        // Add other cases as needed
    }

    if (empty($table)) {
        return $prefix . '-00001'; // Default if table not found
    }

    // Find the highest existing ID for this prefix
    $query = "SELECT $idColumn FROM $table WHERE $idColumn LIKE :prefix ORDER BY $idColumn DESC LIMIT 1";
    $stmt = $pdo->prepare($query);
    $prefixPattern = $prefix . '-%';
    $stmt->execute(['prefix' => $prefixPattern]);
    $row = $stmt->fetch();

    if ($row) {
        $lastID = $row[$idColumn];
        $numericPart = intval(substr($lastID, 3));
        $newNumericPart = $numericPart + 1;
        $newID = $prefix . '-' . str_pad($newNumericPart, 5, '0', STR_PAD_LEFT);
    } else {
        $newID = $prefix . '-00001';
    }

    return $newID;
}

// Function to get user details based on role
function getUserDetails($userID, $role)
{
    global $pdo;

    $table = ($role == 'student') ? 'students' : 'staff';
    $idColumn = ($role == 'student') ? 'student_id' : 'staff_id';

    // Get the user's first name, last name, and role from the appropriate table
    $query = "
        SELECT u.user_id, u.role, 
               IFNULL(s.first_name, '') AS first_name, IFNULL(s.last_name, '') AS last_name 
        FROM users u
        LEFT JOIN $table s ON u.user_id = s.user_id
        WHERE u.user_id = :user_id AND u.role = :role
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $userID, 'role' => $role]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ?: null;
}
function getStaffIdByUserId($userID)
{
    global $pdo;

    // Get the staff_id based on the user_id from the staff table
    $query = "
        SELECT staff_id
        FROM staff
        WHERE user_id = :user_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $userID]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ? $result['staff_id'] : null;
}

/**
 * Get all active students for dropdown
 * @param PDO $pdo The database connection
 * @return array List of students with ID and name
 */
function get_AllStudents($pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT s.student_id, CONCAT(s.first_name, ' ', s.last_name) AS student_name 
            FROM students s
            INNER JOIN users u ON s.user_id = u.user_id
            WHERE u.status = 'active'
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error
        error_log("Error fetching students: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all active terms for dropdown
 * @param PDO $pdo The database connection
 * @return array List of terms with ID and name
 */
function get_AllTerms($pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT t.term_id, 
                   CONCAT(t.term_name, ' (', DATE_FORMAT(t.start_date, '%b %Y'), ' - ', 
                   DATE_FORMAT(t.end_date, '%b %Y'), ')') AS term_name
            FROM terms t
            WHERE t.end_date >= CURRENT_DATE
            ORDER BY t.start_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error
        error_log("Error fetching terms: " . $e->getMessage());
        return [];
    }
}



// Protect pages that require login
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

// Protect pages that require specific role
function requireRole($allowedRoles)
{
    requireLogin();

    $userRole = getUserRole();
    $allowed = false;

    if (is_array($allowedRoles)) {
        $allowed = in_array($userRole, $allowedRoles);
    } else {
        $allowed = ($userRole === $allowedRoles);
    }

    if (!$allowed) {
        header("Location: unauthorized.php");
        exit();
    }
}

/**
 * Process Student Course Requests (Enrollment/Drop)
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Student_Course_Request($pdo)
{
    // Include the courses backend for processing
    include_once "Controllers/student-page/courses.backend.php";
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
        $user_id = $_SESSION['user_id'] ?? '';
        
        if (empty($user_id)) {
            $response['message'] = 'User ID not found in session';
            return $response;
        }
        
        // Get the actual student_id from the students table using user_id
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_data) {
            $response['message'] = 'Student record not found';
            return $response;
        }
        
        $student_id = $student_data['student_id'];
        
        switch ($action) {
            case 'enroll':
                $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : '';
                if (empty($class_id)) {
                    $response['message'] = 'Class ID is required for enrollment';
                    break;
                }
                
                // Check if class has available slots
                $stmt = $pdo->prepare("
                    SELECT enrolled_count, max_students, 
                           c.course_name, cl.section
                    FROM classes cl
                    JOIN courses c ON cl.course_id = c.course_id
                    WHERE cl.class_id = ?
                ");
                $stmt->execute([$class_id]);
                $classInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$classInfo) {
                    $response['message'] = 'Class not found';
                    break;
                }
                
                if ($classInfo['enrolled_count'] >= $classInfo['max_students']) {
                    $response['message'] = 'Class is already full';
                    break;
                }
                
                // Check if already enrolled
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM enrollment_details ed
                    JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
                    WHERE e.student_id = ? AND ed.class_id = ? 
                    AND e.status IN ('approved', 'pending')
                ");
                $stmt->execute([$student_id, $class_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing['count'] > 0) {
                    $response['message'] = 'Already enrolled in this class';
                    break;
                }
                
                // Create enrollment
                $pdo->beginTransaction();
                
                // Insert enrollment record
                $stmt = $pdo->prepare("
                    INSERT INTO enrollments (student_id, enrollment_date, status) 
                    VALUES (?, NOW(), 'pending')
                ");
                $stmt->execute([$student_id]);
                $enrollment_id = $pdo->lastInsertId();
                
                // Insert enrollment detail
                $stmt = $pdo->prepare("
                    INSERT INTO enrollment_details (enrollment_id, class_id, status) 
                    VALUES (?, ?, 'enrolled')
                ");
                $stmt->execute([$enrollment_id, $class_id]);
                
                $pdo->commit();
                
                $response['success'] = true;
                $response['message'] = 'Successfully enrolled in ' . $classInfo['course_name'] . ' (Section ' . $classInfo['section'] . ')';
                break;
                
            case 'drop':
                $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : '';
                if (empty($class_id)) {
                    $response['message'] = 'Class ID is required for dropping';
                    break;
                }
                
                // Get enrollment info
                $stmt = $pdo->prepare("
                    SELECT e.enrollment_id, c.course_name, cl.section
                    FROM enrollment_details ed
                    JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
                    JOIN classes cl ON ed.class_id = cl.class_id
                    JOIN courses c ON cl.course_id = c.course_id
                    WHERE e.student_id = ? AND ed.class_id = ? 
                    AND e.status = 'approved'
                ");
                $stmt->execute([$student_id, $class_id]);
                $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$enrollment) {
                    $response['message'] = 'Enrollment not found or already dropped';
                    break;
                }
                
                // Update enrollment status
                $stmt = $pdo->prepare("
                    UPDATE enrollments 
                    SET status = 'dropped' 
                    WHERE enrollment_id = ?
                ");
                $stmt->execute([$enrollment['enrollment_id']]);
                
                $response['success'] = true;
                $response['message'] = 'Successfully dropped from ' . $enrollment['course_name'] . ' (Section ' . $enrollment['section'] . ')';
                break;
                
            case 'view_details':
                $course_id = isset($_GET['course_id']) ? $_GET['course_id'] : '';
                if (empty($course_id)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Course ID is required']);
                    exit;
                }
                
                // Get course details with prerequisites
                $stmt = $pdo->prepare("
                    SELECT c.*, p.program_name,
                           GROUP_CONCAT(
                               CONCAT(prereq.course_code, ' - ', prereq.course_name) 
                               SEPARATOR '; '
                           ) as prerequisites
                    FROM courses c
                    LEFT JOIN programs p ON c.program_id = p.program_id
                    LEFT JOIN course_prerequisites cp ON c.course_id = cp.course_id
                    LEFT JOIN courses prereq ON cp.prerequisite_course_id = prereq.course_id
                    WHERE c.course_id = ?
                    GROUP BY c.course_id
                ");
                $stmt->execute([$course_id]);
                $courseDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                
                header('Content-Type: application/json');
                echo json_encode($courseDetails);
                exit;
                
            default:
                $response['message'] = 'Invalid action';
                break;
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing course request: " . $e->getMessage());
        $response['message'] = 'Database error occurred';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing course request: " . $e->getMessage());
        $response['message'] = 'An error occurred while processing your request';
    }
    
    return $response;
}

/**
 * Process Student Grade Requests (View grades, request transcript, etc.)
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Student_Grade_Request($pdo)
{
    // Include the grades backend for processing
    include_once "Controllers/student-page/grades.backend.php";
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
        $user_id = $_SESSION['user_id'] ?? '';
        
        if (empty($user_id)) {
            $response['message'] = 'User ID not found in session';
            return $response;
        }
        
        // Get the actual student_id from the students table using user_id
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_data) {
            $response['message'] = 'Student record not found';
            return $response;
        }
        
        $student_id = $student_data['student_id'];
        
        switch ($action) {
            case 'get_grade_details':
                // Return grade details as JSON for AJAX requests
                $course_id = $_GET['course_id'] ?? '';
                if (empty($course_id)) {
                    $response['message'] = 'Course ID is required';
                    break;
                }
                
                // Get detailed grade information for a specific course
                $stmt = $pdo->prepare("
                    SELECT 
                        c.course_code,
                        c.course_name,
                        c.description,
                        c.units,
                        ed.midterm_grade,
                        ed.final_grade,
                        ed.grade as final_course_grade,
                        ed.numeric_grade,
                        CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name,
                        t.term_name,
                        t.academic_year
                    FROM enrollment_details ed
                    JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
                    JOIN classes cl ON ed.class_id = cl.class_id
                    JOIN courses c ON cl.course_id = c.course_id
                    JOIN terms t ON e.term_id = t.term_id
                    JOIN staff ON cl.instructor_id = staff.staff_id
                    WHERE e.student_id = ? AND c.course_id = ?
                ");
                $stmt->execute([$student_id, $course_id]);
                $gradeData = $stmt->fetch(PDO::FETCH_ASSOC);
                  if ($gradeData) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'data' => $gradeData]);
                    exit;
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Grade data not found']);
                    exit;
                }
                
            case 'download_transcript':
                // Handle transcript download request
                $response['success'] = true;
                $response['message'] = 'Transcript request submitted successfully. You will be notified when it is ready.';
                
                // Here you could add logic to:
                // 1. Create a transcript request record
                // 2. Queue the transcript generation
                // 3. Send notification to admin
                break;
                
            case 'request_transcript':
                // Handle transcript request form submission
                $request_type = $_POST['request_type'] ?? 'official';
                $purpose = $_POST['purpose'] ?? '';
                
                if (empty($purpose)) {
                    $response['message'] = 'Purpose is required for transcript request';
                    break;
                }
                
                // Insert transcript request into database
                $stmt = $pdo->prepare("
                    INSERT INTO transcript_requests (student_id, request_type, purpose, status, requested_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                
                if ($stmt->execute([$student_id, $request_type, $purpose])) {
                    $response['success'] = true;
                    $response['message'] = 'Transcript request submitted successfully. You will be notified when it is ready.';
                } else {
                    $response['message'] = 'Failed to submit transcript request. Please try again.';
                }
                break;
                
            default:
                $response['message'] = 'Invalid action';
                break;
        }
        
    } catch (Exception $e) {
        error_log("Error processing grade request: " . $e->getMessage());
        $response['message'] = 'An error occurred while processing your request';
    }
    
    return $response;
}

/**
 * Process Student Profile Requests (Update Profile/Change Password)
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Student_Profile_Request($pdo)
{
    // Include the profile backend for processing
    include_once "Controllers/student-page/profile.backend.php";
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
        $user_id = $_SESSION['user_id'] ?? '';
        
        if (empty($user_id)) {
            $response['message'] = 'User ID not found in session';
            return $response;
        }
        
        // Get the actual student_id from the students table using user_id
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_data) {
            $response['message'] = 'Student record not found';
            return $response;
        }
        
        $student_id = $student_data['student_id'];
        
        switch ($action) {
            case 'update_profile':
                $response = updateStudentProfile($pdo, $student_id, $_POST);
                break;
                
            case 'change_password':
                $response = changeStudentPassword($pdo, $student_id, $_POST);
                break;
                
            default:
                $response['message'] = 'Invalid action';
                break;
        }
        
    } catch (Exception $e) {
        error_log("Error processing profile request: " . $e->getMessage());
        $response['message'] = 'An error occurred while processing your request';
    }
    
    return $response;
}


?>