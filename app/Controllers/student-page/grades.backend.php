<?php
/**
 * Student Grades Backend Controller
 * Handles grade data fetching and transcript operations for student grades page
 */

// Include common utilities
require_once 'utils.php';

/**
 * Get student ID from session
 * @param PDO $pdo Database connection
 * @return string|null Student ID or null if not found
 */
function getStudentIdFromSession($pdo)
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return getStudentIdFromUserId($pdo, $_SESSION['user_id']);
}

/**
 * Log student activity
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @param string $activity_type Activity type
 * @param string $description Activity description
 */
function logStudentActivity($pdo, $student_id, $activity_type, $description)
{
    try {
        // Get user_id from student_id
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $user_id = $result['user_id'];
            
            // Generate log ID
            $log_id = generateNextID('LG');
            
            // Insert activity log
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (log_id, user_id, activity_type, description, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->execute([$log_id, $user_id, $activity_type, $description, $ip_address]);
        }
    } catch (PDOException $e) {
        error_log("Error logging student activity: " . $e->getMessage());
    }
}

/**
 * Get student's current semester grades
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Current semester courses with grades
 */
function getStudentCurrentGrades($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.course_id,
                c.course_code,
                c.course_name,
                c.units,
                c.has_lab,
                cl.class_id,
                cl.section,
                CONCAT(cl.days_of_week, ' ', 
                       TIME_FORMAT(cl.start_time, '%h:%i %p'), ' - ', 
                       TIME_FORMAT(cl.end_time, '%h:%i %p')) AS schedule,
                cl.room,
                CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name,
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                t.is_current,
                ed.grade,
                ed.numeric_grade,
                ed.status as enrollment_status,
                ed.remarks,
                ed.date_modified as grade_updated,
                e.enrollment_date,
                CASE 
                    WHEN ed.grade IS NOT NULL THEN 'Completed'
                    WHEN t.is_current = 1 THEN 'In Progress'
                    ELSE 'Pending'
                END as grade_status
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            WHERE e.student_id = :student_id 
            AND t.is_current = 1
            AND ed.status IN ('enrolled', 'completed')
            ORDER BY c.course_code, cl.section
        ");
        
        $stmt->execute(['student_id' => $student_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logStudentActivity($pdo, $student_id, 'view_current_grades', 'Viewed current semester grades');
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error fetching student current grades: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student's complete grade history
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array All completed courses with grades
 */
function getStudentGradeHistory($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.course_id,
                c.course_code,
                c.course_name,
                c.units,
                c.has_lab,
                cl.class_id,
                cl.section,
                CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name,
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                ed.grade,
                ed.numeric_grade,
                ed.status as enrollment_status,
                ed.remarks,
                ed.date_modified as grade_updated,
                e.enrollment_date,
                CASE 
                    WHEN ed.numeric_grade >= 3.0 THEN 'Passed'
                    WHEN ed.numeric_grade < 3.0 AND ed.numeric_grade > 0 THEN 'Failed'
                    WHEN ed.grade = 'INC' THEN 'Incomplete'
                    WHEN ed.grade = 'W' THEN 'Withdrawn'
                    ELSE 'No Grade'
                END as grade_status
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            WHERE e.student_id = :student_id 
            AND ed.status = 'completed'
            AND ed.grade IS NOT NULL
            ORDER BY t.academic_year DESC, t.term_name DESC, c.course_code
        ");
        
        $stmt->execute(['student_id' => $student_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logStudentActivity($pdo, $student_id, 'view_grade_history', 'Viewed complete grade history');
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error fetching student grade history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student's academic summary and GPA calculation
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Academic summary with GPA calculations
 */
function getStudentAcademicSummary($pdo, $student_id)
{
    try {
        // Get overall GPA and totals
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN ed.status = 'completed' AND ed.numeric_grade IS NOT NULL THEN 1 END) as total_completed_courses,
                SUM(CASE WHEN ed.status = 'completed' AND ed.numeric_grade IS NOT NULL THEN c.units ELSE 0 END) as total_completed_units,
                SUM(CASE WHEN ed.status = 'completed' AND ed.numeric_grade IS NOT NULL THEN (ed.numeric_grade * c.units) ELSE 0 END) as total_grade_points,
                AVG(CASE WHEN ed.status = 'completed' AND ed.numeric_grade IS NOT NULL THEN ed.numeric_grade ELSE NULL END) as overall_gpa,
                COUNT(CASE WHEN ed.status = 'enrolled' THEN 1 END) as current_enrolled_courses,
                SUM(CASE WHEN ed.status = 'enrolled' THEN c.units ELSE 0 END) as current_enrolled_units,
                COUNT(CASE WHEN ed.status = 'completed' AND ed.numeric_grade >= 3.0 THEN 1 END) as passed_courses,
                COUNT(CASE WHEN ed.status = 'completed' AND ed.numeric_grade < 3.0 AND ed.numeric_grade > 0 THEN 1 END) as failed_courses,
                COUNT(CASE WHEN ed.grade = 'INC' THEN 1 END) as incomplete_courses
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            WHERE e.student_id = :student_id
        ");
        
        $stmt->execute(['student_id' => $student_id]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate weighted GPA if we have completed units
        if ($summary['total_completed_units'] > 0) {
            $summary['weighted_gpa'] = $summary['total_grade_points'] / $summary['total_completed_units'];
        } else {
            $summary['weighted_gpa'] = 0;
        }
        
        // Get semester-wise GPA
        $stmt = $pdo->prepare("
            SELECT 
                t.term_name,
                t.academic_year,
                COUNT(ed.detail_id) as courses_taken,
                SUM(c.units) as units_taken,
                SUM(ed.numeric_grade * c.units) as grade_points,
                AVG(ed.numeric_grade) as semester_gpa,
                SUM(c.units) as total_units
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            WHERE e.student_id = :student_id 
            AND ed.status = 'completed'
            AND ed.numeric_grade IS NOT NULL
            GROUP BY t.term_id, t.term_name, t.academic_year
            ORDER BY t.academic_year DESC, t.term_name DESC
        ");
        
        $stmt->execute(['student_id' => $student_id]);
        $summary['semester_gpa'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate weighted semester GPA
        foreach ($summary['semester_gpa'] as &$semester) {
            if ($semester['total_units'] > 0) {
                $semester['weighted_gpa'] = $semester['grade_points'] / $semester['total_units'];
            } else {
                $semester['weighted_gpa'] = 0;
            }
        }
        
        return $summary;
    } catch (PDOException $e) {
        error_log("Error fetching student academic summary: " . $e->getMessage());
        return [
            'total_completed_courses' => 0,
            'total_completed_units' => 0,
            'overall_gpa' => 0,
            'weighted_gpa' => 0,
            'current_enrolled_courses' => 0,
            'current_enrolled_units' => 0,
            'passed_courses' => 0,
            'failed_courses' => 0,
            'incomplete_courses' => 0,
            'semester_gpa' => []
        ];
    }
}

/**
 * Get grade details for a specific course
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @param string $detail_id Enrollment detail ID
 * @return array|null Detailed grade information or null if not found
 */
function getGradeDetails($pdo, $student_id, $detail_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ed.detail_id,
                c.course_code,
                c.course_name,
                c.description as course_description,
                c.units,
                c.has_lab,
                cl.section,
                CONCAT(cl.days_of_week, ' ', 
                       TIME_FORMAT(cl.start_time, '%h:%i %p'), ' - ', 
                       TIME_FORMAT(cl.end_time, '%h:%i %p')) AS schedule,
                cl.room,
                CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name,
                staff.email as instructor_email,
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                ed.grade,
                ed.numeric_grade,
                ed.status as enrollment_status,
                ed.remarks,
                ed.date_added as enrollment_date,
                ed.date_modified as grade_updated,
                p.program_name,
                p.program_code
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            JOIN students s ON e.student_id = s.student_id
            JOIN programs p ON s.program_id = p.program_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            WHERE e.student_id = :student_id 
            AND ed.detail_id = :detail_id
        ");
        
        $stmt->execute([
            'student_id' => $student_id,
            'detail_id' => $detail_id
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Get grade history for this course
            $historyStmt = $pdo->prepare("
                SELECT 
                    gh.previous_grade,
                    gh.previous_numeric_grade,
                    gh.new_grade,
                    gh.new_numeric_grade,
                    gh.changed_at,
                    gh.reason,
                    CONCAT(staff.first_name, ' ', staff.last_name) AS changed_by_name
                FROM grade_history gh
                LEFT JOIN staff ON gh.changed_by = staff.staff_id
                WHERE gh.detail_id = :detail_id
                ORDER BY gh.changed_at DESC
            ");
            
            $historyStmt->execute(['detail_id' => $detail_id]);
            $result['grade_history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            logStudentActivity($pdo, $student_id, 'view_grade_details', "Viewed grade details for course: {$result['course_code']}");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching grade details: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate academic transcript data
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array|null Comprehensive transcript data or null if error
 */
function generateTranscriptData($pdo, $student_id)
{
    try {
        // Get student information
        $stmt = $pdo->prepare("
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                s.date_of_birth,
                s.enrollment_date,
                s.year_level,
                p.program_name,
                p.program_code,
                p.total_units as program_total_units,
                u.login_id
            FROM students s
            JOIN programs p ON s.program_id = p.program_id
            JOIN users u ON s.user_id = u.user_id
            WHERE s.student_id = :student_id
        ");
        
        $stmt->execute(['student_id' => $student_id]);
        $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_info) {
            throw new Exception("Student not found");
        }
        
        // Get all completed courses grouped by term
        $stmt = $pdo->prepare("
            SELECT 
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                c.course_code,
                c.course_name,
                c.units,
                ed.grade,
                ed.numeric_grade,
                CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            WHERE e.student_id = :student_id 
            AND ed.status = 'completed'
            AND ed.grade IS NOT NULL
            ORDER BY t.academic_year, t.start_date, c.course_code
        ");
        
        $stmt->execute(['student_id' => $student_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group courses by term
        $transcript = [
            'student_info' => $student_info,
            'terms' => [],
            'summary' => getStudentAcademicSummary($pdo, $student_id)
        ];
        
        foreach ($courses as $course) {
            $term_key = $course['academic_year'] . ' - ' . $course['term_name'];
            if (!isset($transcript['terms'][$term_key])) {
                $transcript['terms'][$term_key] = [
                    'term_info' => [
                        'term_name' => $course['term_name'],
                        'academic_year' => $course['academic_year'],
                        'start_date' => $course['start_date'],
                        'end_date' => $course['end_date']
                    ],
                    'courses' => [],
                    'term_units' => 0,
                    'term_grade_points' => 0
                ];
            }
            
            $transcript['terms'][$term_key]['courses'][] = $course;
            $transcript['terms'][$term_key]['term_units'] += $course['units'];
            if ($course['numeric_grade'] !== null) {
                $transcript['terms'][$term_key]['term_grade_points'] += ($course['numeric_grade'] * $course['units']);
            }
        }
        
        // Calculate term GPAs
        foreach ($transcript['terms'] as &$term) {
            if ($term['term_units'] > 0) {
                $term['term_gpa'] = $term['term_grade_points'] / $term['term_units'];
            } else {
                $term['term_gpa'] = 0;
            }
        }
        
        logStudentActivity($pdo, $student_id, 'generate_transcript', 'Generated academic transcript');
        
        return $transcript;
    } catch (Exception $e) {
        error_log("Error generating transcript data: " . $e->getMessage());
        return null;
    }
}

/**
 * Handle AJAX requests for grades page
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    $student_id = getStudentIdFromSession($pdo);
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'get_grade_details':
                if (!isset($_GET['detail_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Detail ID required']);
                    exit;
                }
                
                $details = getGradeDetails($pdo, $student_id, $_GET['detail_id']);
                if ($details) {
                    echo json_encode(['success' => true, 'data' => $details]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Grade details not found']);
                }
                break;
                
            case 'download_transcript':
                $transcript = generateTranscriptData($pdo, $student_id);
                if ($transcript) {
                    echo json_encode(['success' => true, 'data' => $transcript]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error generating transcript']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Grades AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
    
    exit;
}

/**
 * Handle POST requests for grades page
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $student_id = getStudentIdFromSession($pdo);
    
    if (!$student_id) {
        return ['success' => false, 'message' => 'Student not found'];
    }
    
    try {
        switch ($action) {
            case 'request_transcript':
                // Log transcript request
                logStudentActivity($pdo, $student_id, 'request_transcript', 'Requested official transcript');
                
                // In a real system, this might create a request in the database
                // For now, we'll just return success
                return ['success' => true, 'message' => 'Transcript request submitted successfully. You will be notified when it is ready.'];
                
            default:
                return ['success' => false, 'message' => 'Invalid action'];
        }
    } catch (Exception $e) {
        error_log("Grades POST Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while processing your request'];
    }
}

?>
