<?php
/**
 * Functions for handling class data and operations in the SiES system
 */

/**
 * Process all class-related requests (add, edit, delete, get)
 * 
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Class_Request($pdo)
{
    $response = [
        'status' => false,
        'message' => ''
    ];    
    // Process GET actions (delete, get class details)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];
        error_log("process_Class_Request called with action: " . $action);

        switch ($action) {            case 'get_class':
                // This is handled via AJAX, so we need to return proper JSON
                header('Content-Type: application/json');
                
                if (!isset($_GET['id']) || empty($_GET['id'])) {
                    echo json_encode(['error' => 'Missing class ID']);
                    exit;
                }
                
                try {
                    $classData = get_ClassById($pdo, $_GET['id']);
                    
                    if ($classData) {
                        // Get enrollment count for this class
                        $enrolledCount = get_EnrolledCount($pdo, $_GET['id']);
                        $classData['enrolled_count'] = $enrolledCount;
                        
                        // Return class data as JSON
                        echo json_encode($classData);
                    } else {
                        echo json_encode(['error' => 'Class not found']);
                    }
                } catch (Exception $e) {
                    error_log("Error in get_class action: " . $e->getMessage());
                    echo json_encode(['error' => 'An error occurred while fetching class data: ' . $e->getMessage()]);
                }
                exit;

            case 'delete_class':
                if (isset($_GET['id'])) {
                    $response = delete_Class($pdo, $_GET['id']);
                    $_SESSION['class_response'] = $response;
                    header('Location: ?page=Manage&subpage=Class');
                    exit;
                }
                break;
        }
    }

    // Process POST actions (add, edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
        $action = $_GET['action'];

        switch ($action) {            case 'add_class':
                $response = add_Class($pdo, $_POST);
                return $response;            case 'edit_class':
                $response = edit_Class($pdo, $_POST);
                return $response;

        }
    }

    return $response;
}

/**
 * Get all classes with optional filtering
 * 
 * @param PDO $pdo Database connection
 * @param array $filters Optional. Array of filter criteria (term_id, course_id, instructor_id, status, search)
 * @return array Array of class records with related information
 */
function get_ClassData($pdo, $filters = null)
{
    try {
        // Verify database connection
        if (!$pdo) {
            error_log("Database connection failed in get_ClassData");
            return [];
        }
        
        // Use provided filters or fallback to $_GET
        $filters = $filters ?? $_GET;
        $sql = "
            SELECT 
                c.class_id, c.course_id, c.term_id, c.section, c.instructor_id, 
                c.room, c.days_of_week, c.start_time, c.end_time, c.max_students, c.status,
                co.course_code, co.course_name,
                t.term_name,
                CONCAT(s.first_name, ' ', s.last_name) AS instructor_name
            FROM 
                classes c
            JOIN 
                courses co ON c.course_id = co.course_id
            JOIN 
                terms t ON c.term_id = t.term_id
            LEFT JOIN 
                staff s ON c.instructor_id = s.staff_id
            WHERE 1=1
        ";        $params = [];

        // Apply course filter if set
        if (isset($filters['course']) && $filters['course'] !== '') {
            $sql .= " AND c.course_id = :course";
            $params['course'] = $filters['course'];
        }

        // Apply term filter if set
        if (isset($filters['term']) && $filters['term'] !== '') {
            $sql .= " AND c.term_id = :term";
            $params['term'] = $filters['term'];
        }

        // Apply instructor filter if set
        if (isset($filters['instructor']) && $filters['instructor'] !== '') {
            $sql .= " AND c.instructor_id = :instructor";
            $params['instructor'] = $filters['instructor'];
        }

        // Apply status filter if set
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND c.status = :status";
            $params['status'] = $filters['status'];
        }        // Apply search filter if set
        if (isset($filters['search']) && $filters['search'] !== '') {
            $searchTerm = trim($filters['search']);
            
            // Try multiple approaches to search for class ID
            if (!empty($searchTerm)) {
                $sql .= " AND (";
                
                // Option 1: Direct equality for class ID (exact match)
                $sql .= "c.class_id = :exact_id";
                $params['exact_id'] = $searchTerm;
                
                // Option 2: LIKE search for class ID
                $sql .= " OR c.class_id LIKE :like_id";
                $params['like_id'] = "%$searchTerm%";
                
                // Option 3: Search in other fields with unique parameter names
                $sql .= " OR c.section LIKE :section_search";
                $params['section_search'] = "%$searchTerm%";
                
                $sql .= " OR co.course_code LIKE :code_search";
                $params['code_search'] = "%$searchTerm%";
                
                $sql .= " OR co.course_name LIKE :name_search";
                $params['name_search'] = "%$searchTerm%";
                
                $sql .= ")";
            }
        }

        // Order by course_code and section
        $sql .= " ORDER BY co.course_code, c.section";        // Debug information
        error_log("SQL Query: " . $sql);
        error_log("Search Parameters: " . json_encode($params));
        error_log("GET parameters: " . json_encode($_GET));
        
        // Execute the query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Result count: " . count($result));
        return $result;    } catch (PDOException $e) {
        error_log("Error fetching class data: " . $e->getMessage());
        error_log("SQL query that failed: " . $sql);
        error_log("Parameters: " . json_encode($params));
        return [];
    }
}

/**
 * Get a specific class by its ID
 * 
 * @param PDO $pdo Database connection
 * @param string $classId The class ID to retrieve
 * @return array|bool Class record with related information or false if not found
 */
function get_ClassById($pdo, $classId) 
{
    try {
        if (empty($classId)) {
            error_log("Error: Empty class ID provided to get_ClassById");
            return false;
        }

        $sql = "
            SELECT 
                c.class_id, c.course_id, c.term_id, c.section, c.instructor_id, 
                c.room, c.days_of_week, c.start_time, c.end_time, c.max_students, c.status,
                co.course_code, co.course_name,
                t.term_name,
                CONCAT(s.first_name, ' ', s.last_name) AS instructor_name
            FROM 
                classes c
            JOIN 
                courses co ON c.course_id = co.course_id
            JOIN 
                terms t ON c.term_id = t.term_id
            LEFT JOIN 
                staff s ON c.instructor_id = s.staff_id
            WHERE 
                c.class_id = :class_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            error_log("No class found with ID: " . $classId);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching class by ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a unique class ID
 * 
 * @param PDO $pdo Database connection
 * @return string New class ID in format CL-00000
 */
function generate_Class_ID($pdo) 
{
    try {
        $stmt = $pdo->query("SELECT MAX(SUBSTRING(class_id, 4)) as max_id FROM classes WHERE class_id LIKE 'CL-%'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $next_id = 1;
        if ($result && $result['max_id']) {
            $next_id = intval($result['max_id']) + 1;
        }

        return 'CL-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        error_log("Error generating class ID: " . $e->getMessage());
        return 'CL-00001'; // Default fallback
    }
}

/**
 * Add a new class
 * 
 * @param PDO $pdo Database connection
 * @param array $data Form data for the new class
 * @return array Response with status and message
 */
function add_Class($pdo, $data) 
{
    try {
        // Input validation
        if (
            empty($data['course_id']) || empty($data['term_id']) || 
            empty($data['section']) || empty($data['room']) || 
            empty($data['days_of_week']) || empty($data['start_time']) || 
            empty($data['end_time']) || empty($data['max_students']) || 
            empty($data['status'])
        ) {
            return ['status' => false, 'message' => 'All required fields must be filled out.'];
        }

        // Check for duplicate class (same course, term, and section)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM classes 
            WHERE course_id = :course_id AND term_id = :term_id AND section = :section
        ");
        $checkStmt->execute([
            'course_id' => $data['course_id'],
            'term_id' => $data['term_id'],
            'section' => $data['section']
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                'status' => false, 
                'message' => 'A class with this course, term, and section already exists.'
            ];
        }

        // Generate a new class ID
        $class_id = generate_Class_ID($pdo);
        
        // Validate time format and duration
        $start_time = strtotime($data['start_time']);
        $end_time = strtotime($data['end_time']);
        
        if ($end_time <= $start_time) {
            return [
                'status' => false, 
                'message' => 'End time must be after start time.'
            ];
        }

        // Handle empty instructor ID
        $instructor_id = !empty($data['instructor_id']) ? $data['instructor_id'] : null;
        
        // Insert the new class
        $sql = "
            INSERT INTO classes (
                class_id, course_id, term_id, section, instructor_id, 
                room, days_of_week, start_time, end_time, max_students, status
            ) VALUES (
                :class_id, :course_id, :term_id, :section, :instructor_id,
                :room, :days_of_week, :start_time, :end_time, :max_students, :status
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'class_id' => $class_id,
            'course_id' => $data['course_id'],
            'term_id' => $data['term_id'],
            'section' => $data['section'],
            'instructor_id' => $instructor_id,
            'room' => $data['room'],
            'days_of_week' => $data['days_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'max_students' => $data['max_students'],
            'status' => $data['status']
        ]);
        
        // Log activity if the function exists
        if (function_exists('log_activity') && isset($_SESSION['user_id'])) {
            log_activity($pdo, $_SESSION['user_id'], 'add_class', "Added new class: {$data['course_id']} - Section {$data['section']}");
        }
        
        return [
            'status' => true, 
            'message' => "Class {$class_id} has been successfully created."
        ];
    } catch (PDOException $e) {
        error_log("Error adding class: " . $e->getMessage());
        return [
            'status' => false, 
            'message' => 'Database error occurred: ' . $e->getMessage()
        ];
    }
}

/**
 * Edit an existing class
 * 
 * @param PDO $pdo Database connection
 * @param array $data Form data for the class update
 * @return array Response with status and message
 */
function edit_Class($pdo, $data) 
{
    try {
        // Input validation
        if (
            empty($data['class_id']) || empty($data['course_id']) || 
            empty($data['term_id']) || empty($data['section']) || 
            empty($data['room']) || empty($data['days_of_week']) || 
            empty($data['start_time']) || empty($data['end_time']) || 
            empty($data['max_students']) || empty($data['status'])
        ) {
            return ['status' => false, 'message' => 'All required fields must be filled out.'];
        }

        // Check if the class exists
        $checkStmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_id = :class_id");
        $checkStmt->execute(['class_id' => $data['class_id']]);
        
        if ($checkStmt->fetchColumn() === false) {
            return ['status' => false, 'message' => 'Class not found.'];
        }

        // Check for duplicate class (same course, term, and section, but different class_id)
        $duplicateStmt = $pdo->prepare("
            SELECT COUNT(*) FROM classes 
            WHERE course_id = :course_id AND term_id = :term_id AND section = :section 
            AND class_id != :class_id
        ");
        $duplicateStmt->execute([
            'course_id' => $data['course_id'],
            'term_id' => $data['term_id'],
            'section' => $data['section'],
            'class_id' => $data['class_id']
        ]);
        
        if ($duplicateStmt->fetchColumn() > 0) {
            return [
                'status' => false, 
                'message' => 'Another class with this course, term, and section already exists.'
            ];
        }

        // Validate time format and duration
        $start_time = strtotime($data['start_time']);
        $end_time = strtotime($data['end_time']);
        
        if ($end_time <= $start_time) {
            return [
                'status' => false, 
                'message' => 'End time must be after start time.'
            ];
        }

        // Validate max_students if there are already students enrolled
        $enrolledCount = get_EnrolledCount($pdo, $data['class_id']);
        if ($enrolledCount > intval($data['max_students'])) {
            return [
                'status' => false, 
                'message' => "Cannot reduce max students to {$data['max_students']} when {$enrolledCount} students are already enrolled."
            ];
        }

        // Handle empty instructor ID
        $instructor_id = !empty($data['instructor_id']) ? $data['instructor_id'] : null;

        // Update the class
        $sql = "
            UPDATE classes SET
                course_id = :course_id,
                term_id = :term_id,
                section = :section,
                instructor_id = :instructor_id,
                room = :room,
                days_of_week = :days_of_week,
                start_time = :start_time,
                end_time = :end_time,
                max_students = :max_students,
                status = :status
            WHERE class_id = :class_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'course_id' => $data['course_id'],
            'term_id' => $data['term_id'],
            'section' => $data['section'],
            'instructor_id' => $instructor_id,
            'room' => $data['room'],
            'days_of_week' => $data['days_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'max_students' => $data['max_students'],
            'status' => $data['status'],
            'class_id' => $data['class_id']
        ]);
        
        // Log activity if the function exists
        if (function_exists('log_activity') && isset($_SESSION['user_id'])) {
            log_activity($pdo, $_SESSION['user_id'], 'edit_class', "Updated class: {$data['class_id']}");
        }
        
        return [
            'status' => true, 
            'message' => "Class {$data['class_id']} has been successfully updated."
        ];
    } catch (PDOException $e) {
        error_log("Error editing class: " . $e->getMessage());
        return [
            'status' => false, 
            'message' => 'Database error occurred: ' . $e->getMessage()
        ];
    }
}

/**
 * Delete a class
 * 
 * @param PDO $pdo Database connection
 * @param string $classId The class ID to delete
 * @return array Response with status and message
 */
function delete_Class($pdo, $classId) 
{
    try {
        // Check if the class exists
        $checkStmt = $pdo->prepare("SELECT course_id, section FROM classes WHERE class_id = :class_id");
        $checkStmt->execute(['class_id' => $classId]);
        $class = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$class) {
            return ['status' => false, 'message' => 'Class not found.'];
        }

        // Check if there are students enrolled in this class
        $enrolledCount = get_EnrolledCount($pdo, $classId);
        if ($enrolledCount > 0) {
            return [
                'status' => false, 
                'message' => "Cannot delete this class because {$enrolledCount} students are enrolled. Remove enrollments first."
            ];
        }

        // Delete the class
        $deleteStmt = $pdo->prepare("DELETE FROM classes WHERE class_id = :class_id");
        $deleteStmt->execute(['class_id' => $classId]);
        
        // Log activity if the function exists
        if (function_exists('log_activity') && isset($_SESSION['user_id'])) {
            log_activity($pdo, $_SESSION['user_id'], 'delete_class', "Deleted class: {$classId}");
        }
        
        return [
            'status' => true, 
            'message' => "Class {$classId} has been successfully deleted."
        ];
    } catch (PDOException $e) {
        error_log("Error deleting class: " . $e->getMessage());
        return [
            'status' => false, 
            'message' => 'Database error occurred: ' . $e->getMessage()
        ];
    }
}

/**
 * Get count of enrolled students for a class
 * 
 * @param PDO $pdo Database connection
 * @param string $classId The class ID to check
 * @return int Number of students enrolled in the class
 */
function get_EnrolledCount($pdo, $classId) 
{
    try {
        $sql = "
            SELECT COUNT(*) AS enrolled_count 
            FROM enrollment_details 
            WHERE class_id = :class_id AND status = 'enrolled'
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['class_id' => $classId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? intval($result['enrolled_count']) : 0;
    } catch (PDOException $e) {
        error_log("Error getting enrolled count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get all active courses for dropdown
 * 
 * @param PDO $pdo Database connection
 * @return array List of courses with ID, code and name
 */
function get_AllCourses($pdo) 
{
    try {
        $sql = "
            SELECT course_id, course_code, course_name 
            FROM courses 
            WHERE status = 'active' 
            ORDER BY course_code
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all instructors (staff) for dropdown
 * 
 * @param PDO $pdo Database connection
 * @return array List of staff with ID, first name and last name
 */
function get_AllInstructors($pdo) 
{
    try {
        $sql = "
            SELECT s.staff_id, s.first_name, s.last_name 
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
            WHERE u.status = 'active' 
            ORDER BY s.last_name, s.first_name
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching instructors: " . $e->getMessage());
        return [];
    }
}

/**
 * Get classes for a specific instructor
 * 
 * @param PDO $pdo Database connection
 * @param string $instructorId The instructor's staff ID
 * @param string $termId Optional term ID to filter by
 * @return array List of classes taught by the instructor
 */
function get_InstructorClasses($pdo, $instructorId, $termId = null) 
{
    try {
        $sql = "
            SELECT 
                c.class_id, c.section, c.room, c.days_of_week, c.start_time, c.end_time, c.status,
                co.course_code, co.course_name,
                t.term_name
            FROM 
                classes c
            JOIN 
                courses co ON c.course_id = co.course_id
            JOIN 
                terms t ON c.term_id = t.term_id
            WHERE 
                c.instructor_id = :instructor_id
        ";
        
        $params = ['instructor_id' => $instructorId];
        
        if ($termId) {
            $sql .= " AND c.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        
        $sql .= " ORDER BY t.start_date DESC, co.course_code, c.section";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching instructor classes: " . $e->getMessage());
        return [];
    }
}

/**
 * Get classes for a specific student
 * 
 * @param PDO $pdo Database connection
 * @param string $studentId The student's ID
 * @param string $termId Optional term ID to filter by
 * @return array List of classes the student is enrolled in
 */
function get_StudentClasses($pdo, $studentId, $termId = null) 
{
    try {
        $sql = "
            SELECT 
                c.class_id, c.section, c.room, c.days_of_week, c.start_time, c.end_time, c.status,
                co.course_code, co.course_name,
                t.term_name,
                CONCAT(s.first_name, ' ', s.last_name) AS instructor_name,
                ed.grade, ed.numeric_grade, ed.status AS enrollment_status
            FROM 
                enrollment_details ed
            JOIN 
                enrollments e ON ed.enrollment_id = e.enrollment_id
            JOIN 
                classes c ON ed.class_id = c.class_id
            JOIN 
                courses co ON c.course_id = co.course_id
            JOIN 
                terms t ON e.term_id = t.term_id
            LEFT JOIN 
                staff s ON c.instructor_id = s.staff_id
            WHERE 
                e.student_id = :student_id
        ";
        
        $params = ['student_id' => $studentId];
        
        if ($termId) {
            $sql .= " AND e.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        
        $sql .= " ORDER BY t.start_date DESC, co.course_code, c.section";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching student classes: " . $e->getMessage());
        return [];
    }
}

/**
 * Get class schedule for a specific room
 * 
 * @param PDO $pdo Database connection
 * @param string $room The room name/number
 * @param string $termId Optional term ID to filter by
 * @return array List of classes scheduled in the room
 */
function get_RoomSchedule($pdo, $room, $termId = null) 
{
    try {
        $sql = "
            SELECT 
                c.class_id, c.section, c.days_of_week, c.start_time, c.end_time, c.status,
                co.course_code, co.course_name,
                t.term_name,
                CONCAT(s.first_name, ' ', s.last_name) AS instructor_name
            FROM 
                classes c
            JOIN 
                courses co ON c.course_id = co.course_id
            JOIN 
                terms t ON c.term_id = t.term_id
            LEFT JOIN 
                staff s ON c.instructor_id = s.staff_id
            WHERE 
                c.room = :room
        ";
        
        $params = ['room' => $room];
        
        if ($termId) {
            $sql .= " AND c.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        
        $sql .= " ORDER BY c.days_of_week, c.start_time";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching room schedule: " . $e->getMessage());
        return [];
    }
}

/**
 * Check for time conflicts for a class schedule
 * 
 * @param PDO $pdo Database connection
 * @param string $room Room to check
 * @param string $daysOfWeek Days of week (e.g., "MWF")
 * @param string $startTime Start time in HH:MM format
 * @param string $endTime End time in HH:MM format
 * @param string $termId Term ID
 * @param string $classId Optional class ID to exclude from check (for edits)
 * @return array|bool Conflicting class data or false if no conflicts
 */
function check_ClassScheduleConflict($pdo, $room, $daysOfWeek, $startTime, $endTime, $termId, $classId = null) 
{
    try {
        // This query finds classes that overlap with the given time slot
        $sql = "
            SELECT 
                c.class_id, c.section, c.days_of_week, c.start_time, c.end_time,
                co.course_code, co.course_name
            FROM 
                classes c
            JOIN 
                courses co ON c.course_id = co.course_id
            WHERE 
                c.room = :room
                AND c.term_id = :term_id
                AND (
                    (c.start_time <= :end_time AND c.end_time >= :start_time)
                )
                AND (
        ";
        
        // Check for days overlap by building day-specific conditions
        $dayConditions = [];
        $days = str_split($daysOfWeek);
        
        foreach ($days as $day) {
            $dayConditions[] = "c.days_of_week LIKE :day_$day";
            $params["day_$day"] = "%$day%";
        }
        
        $sql .= implode(" OR ", $dayConditions) . ")";
        
        // Exclude the current class if editing
        if ($classId) {
            $sql .= " AND c.class_id != :class_id";
            $params['class_id'] = $classId;
        }
        
        $params['room'] = $room;
        $params['term_id'] = $termId;
        $params['start_time'] = $startTime;
        $params['end_time'] = $endTime;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return !empty($conflicts) ? $conflicts : false;
    } catch (PDOException $e) {
        error_log("Error checking class schedule conflicts: " . $e->getMessage());
        return false;
    }
}
?>