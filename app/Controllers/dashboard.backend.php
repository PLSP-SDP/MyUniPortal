<?php
// --- DASHBOARD DATA FETCHING FUNCTIONS ---

// Get the total number of students
function getStudentCount($pdo)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM students");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// Get the total number of courses
function getCourseCount($pdo)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM courses");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// Get the number of pending enrollments
function getPendingEnrollmentsCount($pdo)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM enrollments WHERE status = 'pending'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// Get the number of overdue billings
function getOverdueBillingsCount($pdo)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM billings WHERE status = 'overdue'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}



// Get the recent announcements
function getRecentAnnouncements($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 2");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get the recent enrollment requests
function getRecentEnrollmentRequests($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            p.program_name AS course_name,
            e.enrollment_date AS requested_at,
            e.status
        FROM enrollments e
        JOIN students s ON e.student_id = s.student_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE e.status = 'pending'
        ORDER BY e.enrollment_id DESC
        LIMIT 5
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// Get the recent billing overview
function getRecentBillings($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            billings.billing_id,
            CONCAT(students.first_name, ' ', students.last_name) AS student_name,
            billings.amount,
            billings.due_date,
            billings.status
        FROM 
            billings
        JOIN 
            students ON billings.student_id = students.student_id
        ORDER BY 
            billings.due_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>