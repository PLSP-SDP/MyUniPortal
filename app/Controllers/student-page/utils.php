<?php
/**
 * Common Utility Functions for Student Backend Controllers
 * Contains shared helper functions to avoid duplication
 */

/**
 * Get student ID from user ID (helper function)
 * @param PDO $pdo Database connection
 * @param string $user_id User ID from session
 * @return string|null Student ID or null if not found
 */
function getStudentIdFromUserId($pdo, $user_id)
{
    try {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['student_id'] : null;
    } catch (PDOException $e) {
        error_log("Error getting student ID from user ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Helper function to format grade badge class
 */
function getGradeBadgeClass($grade, $numeric_grade = null)
{
    if (empty($grade)) {
        return 'bg-secondary';
    }
    
    $grade = strtoupper($grade);
    switch ($grade) {
        case 'A':
            return 'bg-success';
        case 'B':
            return 'bg-primary';
        case 'C':
            return 'bg-info';
        case 'D':
            return 'bg-warning text-dark';
        case 'F':
        case 'FAIL':
            return 'bg-danger';
        case 'INC':
        case 'INCOMPLETE':
            return 'bg-warning text-dark';
        case 'W':
        case 'WITHDRAW':
            return 'bg-secondary';
        default:
            if ($numeric_grade !== null) {
                if ($numeric_grade >= 90) return 'bg-success';
                if ($numeric_grade >= 80) return 'bg-primary';
                if ($numeric_grade >= 70) return 'bg-info';
                if ($numeric_grade >= 60) return 'bg-warning text-dark';
                return 'bg-danger';
            }
            return 'bg-secondary';
    }
}

/**
 * Helper function to format enrollment status badge class
 */
function getEnrollmentStatusBadgeClass($status)
{
    switch (strtolower($status)) {
        case 'enrolled':
        case 'approved':
            return 'bg-success';
        case 'pending':
            return 'bg-warning text-dark';
        case 'dropped':
        case 'withdrawn':
            return 'bg-danger';
        case 'completed':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}

/**
 * Helper function to format billing status badge class
 */
function getBillingStatusBadgeClass($status)
{
    switch (strtolower($status)) {
        case 'paid':
            return 'bg-success';
        case 'pending':
            return 'bg-warning text-dark';
        case 'overdue':
            return 'bg-danger';
        case 'partial':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}

/**
 * Helper function to format account status badge class
 */
function getAccountStatusBadgeClass($status)
{
    switch (strtolower($status)) {
        case 'active':
            return 'bg-success';
        case 'inactive':
            return 'bg-warning text-dark';
        case 'suspended':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

/**
 * Helper function to check if enrollment is open
 */
function isEnrollmentOpen($currentTerm)
{
    if (empty($currentTerm['enrollment_start']) || empty($currentTerm['enrollment_end'])) {
        return false;
    }
    
    $now = new DateTime();
    $start = new DateTime($currentTerm['enrollment_start']);
    $end = new DateTime($currentTerm['enrollment_end']);
    
    return $now >= $start && $now <= $end;
}

/**
 * Helper function to get GPA status text color class
 * @param float $gpa The GPA value
 * @return string Bootstrap text color class
 */
function getGpaStatusClass($gpa) {
    if ($gpa >= 3.5) return 'text-success';
    if ($gpa >= 3.0) return 'text-info';
    if ($gpa >= 2.5) return 'text-warning';
    return 'text-danger';
}
?>
