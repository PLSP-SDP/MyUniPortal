<?php
/**
 * Student Home Page Backend Controller
 * Handles dashboard data fetching for student home page
 */

// Include common utilities
require_once 'utils.php';

/**
 * Get student's enrolled courses count
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return int Number of enrolled courses
 */
function getStudentEnrolledCoursesCount($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ed.class_id) AS total 
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved' AND ed.status = 'enrolled'
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? intval($result['total']) : 0;
    } catch (PDOException $e) {
        error_log("Error getting enrolled courses count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get student's completed courses count (courses with grades)
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return int Number of completed courses
 */
function getStudentCompletedCoursesCount($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ed.class_id) AS total 
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved' 
            AND ed.grade IS NOT NULL AND ed.grade != ''
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? intval($result['total']) : 0;
    } catch (PDOException $e) {
        error_log("Error getting completed courses count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get student's pending bills count
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return int Number of pending bills
 */
function getStudentPendingBillsCount($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total 
            FROM billings 
            WHERE student_id = ? AND status IN ('pending', 'partial')
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? intval($result['total']) : 0;
    } catch (PDOException $e) {
        error_log("Error getting pending bills count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get student's overdue bills count
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return int Number of overdue bills
 */
function getStudentOverdueBillsCount($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total 
            FROM billings 
            WHERE student_id = ? AND status = 'overdue'
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? intval($result['total']) : 0;
    } catch (PDOException $e) {
        error_log("Error getting overdue bills count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recent announcements for students
 * @param PDO $pdo Database connection
 * @param int $limit Number of announcements to fetch
 * @return array Recent announcements
 */
function getStudentRecentAnnouncements($pdo, $limit = 3)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                announcement_id,
                title,
                content,
                created_at,
                created_by,
                CONCAT(s.first_name, ' ', s.last_name) as author_name
            FROM announcements a
            LEFT JOIN staff s ON a.created_by = s.staff_id
            WHERE a.is_public = 1 
            AND (a.target_role IN ('all', 'students') OR a.target_role IS NULL)
            ORDER BY a.created_at DESC 
            LIMIT ?
        ");        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recent announcements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student's current enrollments with details
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @param int $limit Number of enrollments to fetch
 * @return array Current enrollments
 */
function getStudentCurrentEnrollments($pdo, $student_id, $limit = 5)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.enrollment_id,
                e.enrollment_date,
                e.status,
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                CONCAT(staff.first_name, ' ', staff.last_name) as approved_by_name,
                e.approved_date
            FROM enrollments e
            LEFT JOIN terms t ON e.term_id = t.term_id
            LEFT JOIN staff ON e.approved_by = staff.staff_id
            WHERE e.student_id = ?
            ORDER BY e.enrollment_date DESC
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting current enrollments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student's billing overview
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @param int $limit Number of bills to fetch
 * @return array Billing overview
 */
function getStudentBillingOverview($pdo, $student_id, $limit = 5)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                b.billing_id,
                b.term_id,
                b.amount,
                b.due_date,
                b.description,
                b.status,
                t.term_name,
                t.academic_year,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (b.amount - COALESCE(SUM(p.amount), 0)) as balance
            FROM billings b
            LEFT JOIN terms t ON b.term_id = t.term_id
            LEFT JOIN payments p ON b.billing_id = p.billing_id
            WHERE b.student_id = ?
            GROUP BY b.billing_id
            ORDER BY b.due_date DESC
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting billing overview: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student's recent grades
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @param int $limit Number of grades to fetch
 * @return array Recent grades
 */
function getStudentRecentGrades($pdo, $student_id, $limit = 5)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ed.detail_id,
                ed.grade,
                ed.numeric_grade,
                ed.remarks,
                cr.course_id,
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
            WHERE e.student_id = ? 
            AND ed.grade IS NOT NULL 
            AND ed.grade != ''
            ORDER BY t.academic_year DESC, t.term_name DESC, cr.course_code
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recent grades: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student profile information
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Student profile data
 */
function getStudentProfile($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                CONCAT(s.first_name, ' ', s.last_name) AS full_name,
                s.email,
                s.phone_number,
                s.year_level,
                s.enrollment_date,
                p.program_name,
                p.program_code,
                CONCAT(adv.first_name, ' ', adv.last_name) AS advisor_name
            FROM 
                students s
            LEFT JOIN 
                programs p ON s.program_id = p.program_id
            LEFT JOIN 
                staff adv ON s.academic_advisor_id = adv.staff_id
            WHERE s.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        } else {
            return [
                'student_id' => $student_id,
                'full_name' => 'Unknown Student',
                'first_name' => 'Unknown',
                'last_name' => 'Student',
                'email' => '',
                'phone_number' => '',
                'year_level' => '',
                'enrollment_date' => '',
                'program_name' => '',
                'program_code' => '',
                'advisor_name' => ''
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting student profile: " . $e->getMessage());
        return [
            'student_id' => $student_id,
            'full_name' => 'Unknown Student',
            'first_name' => 'Unknown',
            'last_name' => 'Student',
            'email' => '',
            'phone_number' => '',
            'year_level' => '',
            'enrollment_date' => '',
            'program_name' => '',
            'program_code' => '',
            'advisor_name' => ''
        ];
    }
}

/**
 * Aggregated function to get all student dashboard data
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array All dashboard data combined
 */
function getStudentDashboardData($pdo, $student_id)
{
    return [
        'student_profile' => getStudentProfile($pdo, $student_id),
        'enrolled_courses_count' => getStudentEnrolledCoursesCount($pdo, $student_id),
        'completed_courses_count' => getStudentCompletedCoursesCount($pdo, $student_id),
        'pending_bills_count' => getStudentPendingBillsCount($pdo, $student_id),
        'overdue_bills_count' => getStudentOverdueBillsCount($pdo, $student_id),
        'recent_announcements' => getStudentRecentAnnouncements($pdo),
        'current_enrollments' => getStudentCurrentEnrollments($pdo, $student_id),
        'billing_overview' => getStudentBillingOverview($pdo, $student_id),
        'recent_grades' => getStudentRecentGrades($pdo, $student_id)    ];
}
?>