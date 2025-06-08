<?php
/**
 * Student Profile Backend Controller
 * Handles profile data fetching and updates for student profile page
 */

// Include common utilities
require_once 'utils.php';

/**
 * Get comprehensive student profile information
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Comprehensive student profile data
 */
function getStudentFullProfile($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.student_id,
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
                s.year_level,
                s.enrollment_date,
                p.program_name,
                p.program_code,
                p.description as program_description,
                p.total_units as program_total_units,
                p.duration_years,
                CONCAT(adv.first_name, ' ', adv.last_name) AS advisor_name,
                adv.email as advisor_email,
                adv.phone_number as advisor_phone,
                u.login_id,
                u.status as account_status,
                u.created_at as account_created,
                u.last_login
            FROM 
                students s
            LEFT JOIN 
                programs p ON s.program_id = p.program_id
            LEFT JOIN 
                staff adv ON s.academic_advisor_id = adv.staff_id
            LEFT JOIN
                users u ON s.user_id = u.user_id
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
                'date_of_birth' => '',
                'gender' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'Philippines',
                'phone_number' => '',
                'email' => '',
                'emergency_contact_name' => '',
                'emergency_contact_phone' => '',
                'year_level' => '',
                'enrollment_date' => '',
                'program_name' => '',
                'program_code' => '',
                'program_description' => '',
                'program_total_units' => '',
                'duration_years' => '',
                'advisor_name' => '',
                'advisor_email' => '',
                'advisor_phone' => '',
                'login_id' => '',
                'account_status' => '',
                'account_created' => '',
                'last_login' => ''
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting student profile: " . $e->getMessage());
        return [
            'student_id' => $student_id,
            'full_name' => 'Error Loading Profile',
            'first_name' => 'Error',
            'last_name' => 'Loading',
            'date_of_birth' => '',
            'gender' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'Philippines',
            'phone_number' => '',
            'email' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'year_level' => '',
            'enrollment_date' => '',
            'program_name' => '',
            'program_code' => '',
            'program_description' => '',
            'program_total_units' => '',
            'duration_years' => '',
            'advisor_name' => '',
            'advisor_email' => '',
            'advisor_phone' => '',
            'login_id' => '',
            'account_status' => '',
            'account_created' => '',
            'last_login' => ''
        ];
    }
}

/**
 * Get student's academic statistics
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Academic statistics
 */
function getStudentAcademicStats($pdo, $student_id)
{
    try {
        // Get total enrolled courses
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ed.class_id) AS total_enrolled
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved'
        ");
        $stmt->execute([$student_id]);
        $totalEnrolled = $stmt->fetch(PDO::FETCH_ASSOC)['total_enrolled'] ?? 0;

        // Get completed courses (with grades)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ed.class_id) AS total_completed
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved' 
            AND ed.grade IS NOT NULL AND ed.grade != ''
        ");
        $stmt->execute([$student_id]);
        $totalCompleted = $stmt->fetch(PDO::FETCH_ASSOC)['total_completed'] ?? 0;

        // Get total units earned
        $stmt = $pdo->prepare("
            SELECT SUM(c.units) AS total_units
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN classes cl ON ed.class_id = cl.class_id
            JOIN courses c ON cl.course_id = c.course_id
            WHERE e.student_id = ? AND e.status = 'approved' 
            AND ed.grade IS NOT NULL AND ed.grade != ''
        ");
        $stmt->execute([$student_id]);
        $totalUnits = $stmt->fetch(PDO::FETCH_ASSOC)['total_units'] ?? 0;

        // Get GPA calculation
        $stmt = $pdo->prepare("
            SELECT AVG(ed.numeric_grade) AS gpa
            FROM enrollment_details ed
            JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
            WHERE e.student_id = ? AND e.status = 'approved' 
            AND ed.numeric_grade IS NOT NULL AND ed.numeric_grade > 0
        ");
        $stmt->execute([$student_id]);
        $gpa = $stmt->fetch(PDO::FETCH_ASSOC)['gpa'] ?? 0;

        return [
            'total_enrolled' => intval($totalEnrolled),
            'total_completed' => intval($totalCompleted),
            'total_units' => intval($totalUnits),
            'gpa' => round(floatval($gpa), 2)
        ];
    } catch (PDOException $e) {
        error_log("Error getting academic stats: " . $e->getMessage());
        return [
            'total_enrolled' => 0,
            'total_completed' => 0,
            'total_units' => 0,
            'gpa' => 0.00
        ];
    }
}

/**
 * Get student's enrollment history
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @return array Enrollment history
 */
function getStudentEnrollmentHistory($pdo, $student_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.enrollment_id,
                e.enrollment_date,
                e.status as enrollment_status,
                t.term_name,
                t.academic_year,
                t.start_date,
                t.end_date,
                COUNT(ed.class_id) as total_courses
            FROM enrollments e
            JOIN terms t ON e.term_id = t.term_id
            LEFT JOIN enrollment_details ed ON e.enrollment_id = ed.enrollment_id
            WHERE e.student_id = ?
            GROUP BY e.enrollment_id, e.enrollment_date, e.status, t.term_name, t.academic_year, t.start_date, t.end_date
            ORDER BY e.enrollment_date DESC
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting enrollment history: " . $e->getMessage());
        return [];
    }
}

// Process the profile page request only if this file is being accessed directly
if (basename($_SERVER['PHP_SELF']) == 'profile.php' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $student_id = getStudentIdFromUserId($pdo, $user_id);
    
    if ($student_id) {
        // Get comprehensive profile data
        $studentProfile = getStudentFullProfile($pdo, $student_id);
        $academicStats = getStudentAcademicStats($pdo, $student_id);
        $enrollmentHistory = getStudentEnrollmentHistory($pdo, $student_id);
    } else {
        // Handle case where student ID is not found
        $studentProfile = [
            'full_name' => 'Student Not Found',
            'first_name' => 'Student',
            'last_name' => 'Not Found'
        ];
        $academicStats = [
            'total_enrolled' => 0,
            'total_completed' => 0,
            'total_units' => 0,
            'gpa' => 0.00
        ];
        $enrollmentHistory = [];
    }
} elseif (basename($_SERVER['PHP_SELF']) == 'profile.php') {
    // No session found, redirect to login only if this is the profile page
    header('Location: ../../index.php');
    exit;
}

/**
 * Update student profile information
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @param array $data Form data
 * @return array Response with status and message
 */
function updateStudentProfile($pdo, $student_id, $data)
{
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'phone_number'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $response['message'] = 'Please fill in all required fields.';
                return $response;
            }
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Please enter a valid email address.';
            return $response;
        }
        
        // Check if email is already used by another student
        $stmt = $pdo->prepare("
            SELECT student_id FROM students 
            WHERE email = ? AND student_id != ?
        ");
        $stmt->execute([$data['email'], $student_id]);
        if ($stmt->fetch()) {
            $response['message'] = 'Email address is already in use by another student.';
            return $response;
        }
        
        // Prepare update query
        $stmt = $pdo->prepare("
            UPDATE students SET
                first_name = ?,
                last_name = ?,
                email = ?,
                phone_number = ?,
                address = ?,
                city = ?,
                state = ?,
                postal_code = ?,
                country = ?,
                emergency_contact_name = ?,
                emergency_contact_phone = ?
            WHERE student_id = ?
        ");
        
        $result = $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone_number'],
            $data['address'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['postal_code'] ?? '',
            $data['country'] ?? '',
            $data['emergency_contact_name'] ?? '',
            $data['emergency_contact_phone'] ?? '',
            $student_id
        ]);
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully.';
        } else {
            $response['message'] = 'Failed to update profile. Please try again.';
        }
        
    } catch (PDOException $e) {
        error_log("Error updating student profile: " . $e->getMessage());
        $response['message'] = 'Database error occurred while updating profile.';
    }
    
    return $response;
}

/**
 * Change student password
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID
 * @param array $data Form data
 * @return array Response with status and message
 */
function changeStudentPassword($pdo, $student_id, $data)
{
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        if (empty($data['current_password']) || empty($data['new_password']) || empty($data['confirm_password'])) {
            $response['message'] = 'Please fill in all password fields.';
            return $response;
        }
        
        // Check if new passwords match
        if ($data['new_password'] !== $data['confirm_password']) {
            $response['message'] = 'New passwords do not match.';
            return $response;
        }
        
        // Validate password strength
        if (strlen($data['new_password']) < 8) {
            $response['message'] = 'Password must be at least 8 characters long.';
            return $response;
        }
        
        // Get current password hash
        $stmt = $pdo->prepare("
            SELECT u.password 
            FROM users u
            JOIN students s ON u.user_id = s.student_id
            WHERE s.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $response['message'] = 'User account not found.';
            return $response;
        }
        
        // Verify current password
        if (!password_verify($data['current_password'], $user['password'])) {
            $response['message'] = 'Current password is incorrect.';
            return $response;
        }
        
        // Hash new password
        $new_password_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ? 
            WHERE user_id = ?
        ");
        
        $result = $stmt->execute([$new_password_hash, $student_id]);
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Password changed successfully.';
        } else {
            $response['message'] = 'Failed to change password. Please try again.';
        }
        
    } catch (PDOException $e) {
        error_log("Error changing student password: " . $e->getMessage());
        $response['message'] = 'Database error occurred while changing password.';
    }
      return $response;
}
?>
