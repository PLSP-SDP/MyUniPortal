<?php
/**
 * Student Courses Backend Controller
 * Handles course data fetching and enrollment operations for student courses page
 */

// Include common utilities
require_once 'utils.php';

/**
 * Get student's enrolled courses for current term
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Current enrolled courses
 */
function getStudentCurrentCourses($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.course_id,
                c.course_code,
                c.course_name,
                c.description as course_description,
                c.units,
                c.has_lab,
                cl.class_id,
                cl.section,
                CONCAT(cl.days_of_week, ' ', 
                       TIME_FORMAT(cl.start_time, '%h:%i %p'), ' - ', 
                       TIME_FORMAT(cl.end_time, '%h:%i %p')) AS schedule,
                cl.room,
                cl.max_students,
                COALESCE(enrollment_count.enrolled_count, 0) as enrolled_count,
                CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name,
                staff.email as instructor_email,
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                ed.grade,
                ed.numeric_grade,
                ed.status as enrollment_status,
                e.enrollment_date,
                p.program_name,
                p.program_code
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            LEFT JOIN programs p ON c.program_id = p.program_id
            LEFT JOIN (
                SELECT 
                    ed2.class_id,
                    COUNT(*) as enrolled_count
                FROM enrollment_details ed2
                JOIN enrollments e2 ON ed2.enrollment_id = e2.enrollment_id
                WHERE e2.status = 'approved' AND ed2.status = 'enrolled'
                GROUP BY ed2.class_id
            ) enrollment_count ON cl.class_id = enrollment_count.class_id
            WHERE e.student_id = ? 
            AND e.status = 'approved'
            AND t.is_current = 1
            ORDER BY c.course_code
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting current courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student's course history (completed courses)
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Course history
 */
function getStudentCourseHistory($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.course_id,
                c.course_code,
                c.course_name,
                c.units,
                cl.section,
                CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name,
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                ed.grade,
                ed.numeric_grade,
                ed.status as enrollment_status,
                e.enrollment_date,
                p.program_name
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            LEFT JOIN programs p ON c.program_id = p.program_id
            WHERE e.student_id = ? 
            AND e.status = 'approved'
            AND t.is_current = 0
            ORDER BY t.academic_year DESC, t.term_name DESC, c.course_code
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting course history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get available courses for enrollment
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Available courses
 */
function getAvailableCoursesForEnrollment($pdo, $student_id)
{
    try {
        // Get student's program to filter relevant courses
        $stmt = $pdo->prepare("
            SELECT program_id, year_level 
            FROM students 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$studentInfo) {
            return [];
        }        // Get current term classes that student is not enrolled in
        $stmt = $pdo->prepare("
            SELECT 
                c.course_id,
                c.course_code,
                c.course_name,
                c.description as course_description,
                c.units,
                c.has_lab,
                cl.class_id,
                cl.section,
                CONCAT(cl.days_of_week, ' ', 
                       TIME_FORMAT(cl.start_time, '%h:%i %p'), ' - ', 
                       TIME_FORMAT(cl.end_time, '%h:%i %p')) AS schedule,
                cl.room,
                cl.max_students,
                COALESCE(enrollment_count.enrolled_count, 0) as enrolled_count,
                (cl.max_students - COALESCE(enrollment_count.enrolled_count, 0)) as available_slots,
                CONCAT(staff.first_name, ' ', staff.last_name) AS instructor_name,
                t.term_name,
                t.academic_year,
                p.program_name,
                p.program_code
            FROM classes cl
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            LEFT JOIN staff ON cl.instructor_id = staff.staff_id
            LEFT JOIN programs p ON c.program_id = p.program_id
            LEFT JOIN (
                SELECT 
                    ed2.class_id,
                    COUNT(*) as enrolled_count
                FROM enrollment_details ed2
                JOIN enrollments e2 ON ed2.enrollment_id = e2.enrollment_id
                WHERE e2.status = 'approved' AND ed2.status = 'enrolled'
                GROUP BY ed2.class_id
            ) enrollment_count ON cl.class_id = enrollment_count.class_id
            WHERE t.is_current = 1
            AND c.status = 'active'
            AND COALESCE(enrollment_count.enrolled_count, 0) < cl.max_students
            AND cl.class_id NOT IN (
                SELECT ed.class_id 
                FROM enrollment_details ed
                JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
                WHERE e.student_id = ? AND e.status IN ('approved', 'pending')
            )
            AND (c.program_id = ? OR c.program_id IS NULL)
            ORDER BY c.course_code
        ");
        $stmt->execute([$student_id, $studentInfo['program_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting available courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get course prerequisites
 * @param PDO $pdo Database connection
 * @param string $course_id Course ID
 * @return array Prerequisites
 */
function getCoursePrerequisites($pdo, $course_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.course_code,
                c.course_name
            FROM course_prerequisites cp
            JOIN courses c ON cp.prerequisite_course_id = c.course_id
            WHERE cp.course_id = ?
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting prerequisites: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student's academic statistics
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Academic statistics
 */
function getStudentAcademicSummary($pdo, $student_id)
{
    try {
        // Total units enrolled (current term)
        $stmt = $pdo->prepare("
            SELECT SUM(c.units) as current_units
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            JOIN terms t ON cl.term_id = t.term_id
            WHERE e.student_id = ? AND e.status = 'approved' AND t.is_current = 1
        ");
        $stmt->execute([$student_id]);
        $currentUnits = $stmt->fetch(PDO::FETCH_ASSOC)['current_units'] ?? 0;

        // Total units completed
        $stmt = $pdo->prepare("
            SELECT SUM(c.units) as completed_units
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            WHERE e.student_id = ? AND e.status = 'approved' 
            AND ed.grade IS NOT NULL AND ed.grade != ''
        ");
        $stmt->execute([$student_id]);
        $completedUnits = $stmt->fetch(PDO::FETCH_ASSOC)['completed_units'] ?? 0;

        // Total courses enrolled (current)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as current_courses
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN terms t ON cl.term_id = t.term_id
            WHERE e.student_id = ? AND e.status = 'approved' AND t.is_current = 1
        ");
        $stmt->execute([$student_id]);
        $currentCourses = $stmt->fetch(PDO::FETCH_ASSOC)['current_courses'] ?? 0;

        // Total courses completed
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as completed_courses
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved' 
            AND ed.grade IS NOT NULL AND ed.grade != ''
        ");
        $stmt->execute([$student_id]);
        $completedCourses = $stmt->fetch(PDO::FETCH_ASSOC)['completed_courses'] ?? 0;        // Current GPA
        $stmt = $pdo->prepare("
            SELECT AVG(ed.numeric_grade) as gpa
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved' 
            AND ed.numeric_grade IS NOT NULL AND ed.numeric_grade > 0
        ");
        $stmt->execute([$student_id]);
        $gpa = $stmt->fetch(PDO::FETCH_ASSOC)['gpa'] ?? 0;

        // Get student's program total units to calculate progress
        $stmt = $pdo->prepare("
            SELECT p.total_units
            FROM students s
            JOIN programs p ON s.program_id = p.program_id
            WHERE s.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $programInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalProgramUnits = $programInfo['total_units'] ?? 120; // Default to 120 if not found
        
        // Calculate progress percentage
        $progressPercentage = $totalProgramUnits > 0 ? round(($completedUnits / $totalProgramUnits) * 100, 1) : 0;

        return [
            'current_units' => intval($currentUnits),
            'completed_units' => intval($completedUnits),
            'current_courses' => intval($currentCourses),
            'completed_courses' => intval($completedCourses),
            'gpa' => round(floatval($gpa), 2),
            'progress_percentage' => $progressPercentage,
            'total_program_units' => intval($totalProgramUnits)
        ];    } catch (PDOException $e) {
        error_log("Error getting academic summary: " . $e->getMessage());
        return [
            'current_units' => 0,
            'completed_units' => 0,
            'current_courses' => 0,
            'completed_courses' => 0,
            'gpa' => 0.00,
            'progress_percentage' => 0,
            'total_program_units' => 120
        ];
    }
}

/**
 * Get current term information
 * @param PDO $pdo Database connection
 * @return array Current term data
 */
function getCurrentTerm($pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                term_id,
                term_name,
                academic_year,
                start_date,
                end_date,
                enrollment_start,
                enrollment_end,
                status
            FROM terms 
            WHERE is_current = 1
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: [
            'term_name' => 'No Current Term',
            'academic_year' => '',
            'start_date' => '',
            'end_date' => '',
            'enrollment_start' => '',
            'enrollment_end' => '',
            'status' => 'inactive'
        ];
    } catch (PDOException $e) {
        error_log("Error getting current term: " . $e->getMessage());
        return [
            'term_name' => 'Error Loading Term',
            'academic_year' => '',
            'start_date' => '',
            'end_date' => '',
            'enrollment_start' => '',
            'enrollment_end' => '',
            'status' => 'error'
        ];
    }
}

// Process the courses page request only if this file is being accessed directly
if (basename($_SERVER['PHP_SELF']) == 'courses.php' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $student_id = getStudentIdFromUserId($pdo, $user_id);
    
    if ($student_id) {
        // Get all course-related data
        $currentCourses = getStudentCurrentCourses($pdo, $student_id);
        $courseHistory = getStudentCourseHistory($pdo, $student_id);
        $availableCourses = getAvailableCoursesForEnrollment($pdo, $student_id);
        $academicSummary = getStudentAcademicSummary($pdo, $student_id);
        $currentTerm = getCurrentTerm($pdo);
    } else {
        // Handle case where student ID is not found
        $currentCourses = [];
        $courseHistory = [];
        $availableCourses = [];
        $academicSummary = [
            'current_units' => 0,
            'completed_units' => 0,
            'current_courses' => 0,
            'completed_courses' => 0,
            'gpa' => 0.00
        ];
        $currentTerm = [
            'term_name' => 'Student Not Found',
            'academic_year' => '',
            'start_date' => '',
            'end_date' => '',
            'enrollment_start' => '',
            'enrollment_end' => '',
            'status' => 'error'
        ];
    }
} elseif (basename($_SERVER['PHP_SELF']) == 'courses.php') {
    // No session found, redirect to login only if this is the courses page    header('Location: ../../index.php');
    exit;
}
?>
