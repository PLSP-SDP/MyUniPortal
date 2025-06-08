<?php
/**
 * Functions for handling course data and operations in the SiES system
 */

/**
 * Process all course-related requests (add, edit, delete, get)
 * 
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Course_Request($pdo)
{
    $response = [
        'status' => false,
        'message' => '',
    ];
    
    // Process GET actions (delete, get course details)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];
        switch ($action) {            case 'get':
                if (isset($_GET['id'])) {
                    error_log("Processing 'get' action for course ID: " . $_GET['id']);
                    
                    $course = get_CourseById($pdo, $_GET['id']);
                    if ($course) {
                        // Output as JSON and exit
                        error_log("Sending JSON response for course: " . $course['course_code']);
                        header('Content-Type: application/json');
                        echo json_encode($course);
                        exit;
                    } else {
                        // Return error as JSON
                        error_log("Course not found with ID: " . $_GET['id']);
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Course not found']);
                        exit;
                    }
                } else {
                    // Return error as JSON
                    error_log("No course ID provided in 'get' action");
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Course ID not provided']);
                    exit;
                }
                case 'delete':
                // Debug all GET parameters
                error_log('DELETE action - All GET params: ' . print_r($_GET, true));
                
                if (isset($_GET['course_id']) && !empty($_GET['course_id'])) {
                    error_log('Deleting course with ID: ' . $_GET['course_id']);
                    
                    try {
                        $result = delete_Course($pdo, $_GET['course_id']);
                        error_log('Delete result: ' . print_r($result, true));
                        return $result;
                    } catch (Exception $e) {
                        error_log('Exception during delete_Course: ' . $e->getMessage());
                        return [
                            'status' => false,
                            'message' => 'Error during course deletion: ' . $e->getMessage()
                        ];
                    }
                } 
                
                error_log('Course ID not provided for delete action');
                return [
                    'status' => false, 
                    'message' => 'Course ID not provided. Please make sure the course ID is specified.'
                ];
                // No break needed as we return in all cases
        }
    }

    // Process POST actions (add, update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
        $action = $_GET['action'];
        
        switch ($action) {
            case 'add':
                $result = add_Course($pdo, $_POST);
                return $result;
                
            case 'update':
                $result = edit_Course($pdo, $_POST);
                return $result;
        }
    }

    return $response;
}

/**
 * Get all courses with optional filtering
 * 
 * @param PDO $pdo Database connection
 * @param array $filters Optional. Array of filter criteria (program, status, has_lab, search)
 * @return array Array of course records with related information
 */
function get_CourseData($pdo, $filters = null)
{
    try {
        // Verify database connection
        if (!$pdo) {
            error_log("Database connection not provided to get_CourseData");
            return [];
        }
        
        // Use provided filters or fallback to $_GET
        $filters = $filters ?? $_GET;
        
        // Build the SQL query
        $sql = "
            SELECT 
                c.course_id, c.course_code, c.course_name, c.description, c.units, c.has_lab, c.status,
                p.program_id, p.program_name,
                (SELECT GROUP_CONCAT(pre.course_code SEPARATOR ', ') 
                FROM course_prerequisites cp 
                JOIN courses pre ON cp.prerequisite_course_id = pre.course_id 
                WHERE cp.course_id = c.course_id) AS prerequisites
            FROM 
                courses c
            LEFT JOIN 
                programs p ON c.program_id = p.program_id
            WHERE 1=1
        ";
        
        $params = [];

        // Apply program filter if set
        if (isset($filters['program']) && $filters['program'] !== '') {
            $sql .= " AND c.program_id = :program_id";
            $params['program_id'] = $filters['program'];
        }

        // Apply status filter if set
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND c.status = :status";
            $params['status'] = $filters['status'];
        }

        // Apply lab filter if set
        if (isset($filters['has_lab']) && $filters['has_lab'] !== '') {
            $sql .= " AND c.has_lab = :has_lab";
            $params['has_lab'] = $filters['has_lab'];
        }        // Apply search filter if set
        if (isset($filters['search']) && $filters['search'] !== '') {
            error_log("Search filter applied with value: '" . $filters['search'] . "'");
            $sql .= " AND (c.course_code LIKE :search_code OR c.course_name LIKE :search_name)";
            $params['search_code'] = '%' . $filters['search'] . '%';
            $params['search_name'] = '%' . $filters['search'] . '%';
            error_log("Search term with wildcards: '" . $params['search_code'] . "'");
        }

        // Order by course_code
        $sql .= " ORDER BY c.course_code";
          // Debug information
        error_log("SQL Query: " . $sql);
        error_log("Search Parameters: " . json_encode($params));
          try {
            // Execute the query
            $stmt = $pdo->prepare($sql);
            
            // Execute with all parameters at once
            $stmt->execute($params);
            error_log("Executing SQL query with parameters: " . json_encode($params));
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Result count: " . count($result));            if (count($result) === 0) {
                error_log("No results found with parameters: " . json_encode($params));
                
                // Try a simple query to test if there's any data at all
                $testStmt = $pdo->query("SELECT COUNT(*) FROM courses");
                $totalCount = $testStmt->fetchColumn();
                error_log("Total courses in database: " . $totalCount);
                
                // If search parameter is set, try a direct search without filters to see if it exists
                if (isset($filters['search']) && !empty($filters['search'])) {
                    $searchOnly = "SELECT course_id, course_code, course_name FROM courses WHERE course_code LIKE ? OR course_name LIKE ?";
                    $searchStmt = $pdo->prepare($searchOnly);
                    $searchPattern = '%' . $filters['search'] . '%';
                    $searchStmt->execute([$searchPattern, $searchPattern]);
                    $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    error_log("Direct search found " . count($searchResults) . " courses");
                    if (count($searchResults) > 0) {
                        error_log("Search term exists but combined filters may be excluding it - search results: " . json_encode($searchResults));
                        if (isset($filters['program']) && !empty($filters['program'])) {
                            // Check if the course exists in the specified program
                            $programCheck = "SELECT COUNT(*) FROM courses WHERE course_id = ? AND program_id = ?";
                            foreach ($searchResults as $course) {
                                $checkStmt = $pdo->prepare($programCheck);
                                $checkStmt->execute([$course['course_id'], $filters['program']]);
                                $exists = $checkStmt->fetchColumn();
                                error_log("Course {$course['course_code']} exists in program {$filters['program']}: " . ($exists ? 'Yes' : 'No'));
                            }
                        }
                    }
                }
                if (isset($filters['search']) && $filters['search'] !== '') {
                    $searchOnly = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code LIKE :search OR course_name LIKE :search");
                    $searchOnly->execute(['search' => '%' . $filters['search'] . '%']);
                    $searchCount = $searchOnly->fetchColumn();
                    error_log("Found " . $searchCount . " courses matching search term without other filters");
                    
                    if ($searchCount > 0 && count($params) > 1) {
                        error_log("Search term exists but combined filters returned no results - possible filter conflict");
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Error executing search: " . $e->getMessage());
            return [];
        }
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching course data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a specific course by its ID
 * 
 * @param PDO $pdo Database connection
 * @param string $courseId The course ID to retrieve
 * @return array|bool Course record with related information or false if not found
 */
function get_CourseById($pdo, $courseId) 
{
    try {
        if (empty($courseId)) {
            return false;
        }

        // First get the course details        error_log("Fetching course ID: " . $courseId);

        $sql = "
            SELECT 
                c.course_id, c.course_code, c.course_name, c.description, c.units, c.has_lab, c.status,
                p.program_id, p.program_name,
                (SELECT GROUP_CONCAT(pre.course_code SEPARATOR ', ') 
                FROM course_prerequisites cp 
                JOIN courses pre ON cp.prerequisite_course_id = pre.course_id 
                WHERE cp.course_id = c.course_id) AS prerequisites
            FROM 
                courses c
            LEFT JOIN 
                programs p ON c.program_id = p.program_id
            WHERE 
                c.course_id = :course_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':course_id', $courseId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }

        // Debug log
        error_log("Retrieved course data: " . json_encode($result));
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching course by ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a unique course ID
 * 
 * @param PDO $pdo Database connection
 * @return string New course ID in format CR-00000
 */
function generate_Course_ID($pdo) 
{
    try {
        $stmt = $pdo->query("SELECT MAX(SUBSTRING(course_id, 4)) as max_id FROM courses WHERE course_id LIKE 'CR-%'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $next_id = 1;
        if ($result && $result['max_id']) {
            $next_id = intval($result['max_id']) + 1;
        }

        return 'CR-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        error_log("Error generating course ID: " . $e->getMessage());
        return 'CR-00001'; // Default fallback
    }
}

/**
 * Add a new course
 * 
 * @param PDO $pdo Database connection
 * @param array $data Form data for the new course
 * @return array Response with status and message
 */
function add_Course($pdo, $data) 
{
    try {
        // Input validation
        if (
            empty($data['course_code']) || empty($data['course_name']) || 
            empty($data['units']) || empty($data['program_id']) || 
            empty($data['status'])
        ) {
            return [
                'status' => false, 
                'message' => 'All required fields must be filled out.'
            ];
        }

        // Check for duplicate course code
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = :course_code");
        $checkStmt->execute(['course_code' => $data['course_code']]);
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                'status' => false, 
                'message' => 'A course with this course code already exists.'
            ];
        }

        // Generate a new course ID
        $course_id = generate_Course_ID($pdo);
        
        // Set has_lab to 0 or 1
        $has_lab = isset($data['has_lab']) ? 1 : 0;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert the new course
        $sql = "
            INSERT INTO courses (
                course_id, course_code, course_name, description, units, 
                program_id, has_lab, status
            ) VALUES (
                :course_id, :course_code, :course_name, :description, :units, 
                :program_id, :has_lab, :status
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'course_id' => $course_id,
            'course_code' => $data['course_code'],
            'course_name' => $data['course_name'],
            'description' => $data['description'] ?? null,
            'units' => $data['units'],
            'program_id' => $data['program_id'],
            'has_lab' => $has_lab,
            'status' => $data['status']
        ]);
        
        // Handle prerequisites if provided
        if (!empty($data['prerequisites'])) {
            $prereq_courses = explode(',', $data['prerequisites']);
            
            foreach ($prereq_courses as $prereq_code) {
                $prereq_code = trim($prereq_code);
                if (empty($prereq_code)) continue;
                
                // Get the prerequisite course ID
                $prereqStmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = :course_code");
                $prereqStmt->execute(['course_code' => $prereq_code]);
                $prereq_id = $prereqStmt->fetchColumn();
                
                if ($prereq_id) {
                    // Generate a prerequisite ID
                    $stmt = $pdo->query("SELECT MAX(SUBSTRING(prerequisite_id, 4)) as max_id FROM course_prerequisites WHERE prerequisite_id LIKE 'PR-%'");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    $next_id = 1;
                    if ($result && $result['max_id']) {
                        $next_id = intval($result['max_id']) + 1;
                    }
                    $prerequisite_id = 'PR-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
                    
                    // Add the prerequisite
                    $prereqInsertStmt = $pdo->prepare("
                        INSERT INTO course_prerequisites (prerequisite_id, course_id, prerequisite_course_id)
                        VALUES (:prerequisite_id, :course_id, :prerequisite_course_id)
                    ");
                    $prereqInsertStmt->execute([
                        'prerequisite_id' => $prerequisite_id,
                        'course_id' => $course_id,
                        'prerequisite_course_id' => $prereq_id
                    ]);
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();        // Log activity to error_log instead
        error_log("Activity: User added new course: {$data['course_code']} - {$data['course_name']}");
        
        // If a logging function exists, use it (commented out for now)
        /*
        if (function_exists('logActivity') && isset($_SESSION['user_id'])) {
            logActivity($pdo, $_SESSION['user_id'], 'add_course', "Added new course: {$data['course_code']} - {$data['course_name']}");
        }
        */
        
        return [
            'status' => true, 
            'message' => "Course {$data['course_code']} has been successfully created."
        ];
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error adding course: " . $e->getMessage());
        return [
            'status' => false, 
            'message' => 'Database error occurred: ' . $e->getMessage()
        ];
    }
}

/**
 * Edit an existing course
 * 
 * @param PDO $pdo Database connection
 * @param array $data Form data for the course update
 * @return array Response with status and message
 */
function edit_Course($pdo, $data) 
{
    try {
        // Input validation
        if (
            empty($data['course_id']) || empty($data['course_code']) || 
            empty($data['course_name']) || empty($data['units']) || 
            empty($data['program_id']) || empty($data['status'])
        ) {
            return ['status' => false, 'message' => 'All required fields must be filled out.'];
        }

        // Check if the course exists
        $checkStmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_id = :course_id");
        $checkStmt->execute(['course_id' => $data['course_id']]);
        
        if ($checkStmt->fetchColumn() === false) {
            return ['status' => false, 'message' => 'Course not found.'];
        }

        // Check for duplicate course code (excluding current course)
        $duplicateStmt = $pdo->prepare("
            SELECT COUNT(*) FROM courses 
            WHERE course_code = :course_code AND course_id != :course_id
        ");
        $duplicateStmt->execute([
            'course_code' => $data['course_code'],
            'course_id' => $data['course_id']
        ]);
        
        if ($duplicateStmt->fetchColumn() > 0) {
            return [
                'status' => false, 
                'message' => 'Another course with this course code already exists.'
            ];
        }

        // Set has_lab to 0 or 1
        $has_lab = isset($data['has_lab']) ? 1 : 0;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update the course
        $sql = "
            UPDATE courses SET
                course_code = :course_code,
                course_name = :course_name,
                description = :description,
                units = :units,
                program_id = :program_id,
                has_lab = :has_lab,
                status = :status
            WHERE course_id = :course_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'course_code' => $data['course_code'],
            'course_name' => $data['course_name'],
            'description' => $data['description'] ?? null,
            'units' => $data['units'],
            'program_id' => $data['program_id'],
            'has_lab' => $has_lab,
            'status' => $data['status'],
            'course_id' => $data['course_id']
        ]);
          // Handle prerequisites if provided
        if (isset($data['prerequisites'])) {
            // First delete existing prerequisites
            $deletePrereqStmt = $pdo->prepare("DELETE FROM course_prerequisites WHERE course_id = ? OR prerequisite_course_id = ?");
            $deletePrereqStmt->execute([$data['course_id'], $data['course_id']]);
            
            // Add new prerequisites
            if (!empty($data['prerequisites'])) {
                $prereq_courses = explode(',', $data['prerequisites']);
                
                foreach ($prereq_courses as $prereq_code) {
                    $prereq_code = trim($prereq_code);
                    if (empty($prereq_code)) continue;
                    
                    // Get the prerequisite course ID
                    $prereqStmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = :course_code");
                    $prereqStmt->execute(['course_code' => $prereq_code]);
                    $prereq_id = $prereqStmt->fetchColumn();
                    
                    if ($prereq_id) {
                        // Generate a prerequisite ID
                        $stmt = $pdo->query("SELECT MAX(SUBSTRING(prerequisite_id, 4)) as max_id FROM course_prerequisites WHERE prerequisite_id LIKE 'PR-%'");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);

                        $next_id = 1;
                        if ($result && $result['max_id']) {
                            $next_id = intval($result['max_id']) + 1;
                        }
                        $prerequisite_id = 'PR-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
                        
                        // Add the prerequisite
                        $prereqInsertStmt = $pdo->prepare("
                            INSERT INTO course_prerequisites (prerequisite_id, course_id, prerequisite_course_id)
                            VALUES (:prerequisite_id, :course_id, :prerequisite_course_id)
                        ");
                        $prereqInsertStmt->execute([
                            'prerequisite_id' => $prerequisite_id,
                            'course_id' => $data['course_id'],
                            'prerequisite_course_id' => $prereq_id
                        ]);
                    }
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();        // Log activity to error_log instead
        error_log("Activity: User updated course: {$data['course_code']} - {$data['course_name']}");
        
        // If a logging function exists, use it (commented out for now)
        /*
        if (function_exists('logActivity') && isset($_SESSION['user_id'])) {
            logActivity($pdo, $_SESSION['user_id'], 'edit_course', "Updated course: {$data['course_code']} - {$data['course_name']}");
        }
        */
        
        return [
            'status' => true, 
            'message' => "Course {$data['course_code']} has been successfully updated."
        ];
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error editing course: " . $e->getMessage());
        return [
            'status' => false, 
            'message' => 'Database error occurred: ' . $e->getMessage()
        ];
    }
}

/**
 * Delete a course
 * 
 * @param PDO $pdo Database connection
 * @param string $courseId The course ID to delete
 * @return array Response with status and message
 */
function delete_Course($pdo, $courseId)
{
    try {
        // Debug log
        error_log("delete_Course called with course_id: " . $courseId);
        
        // Check if the course exists
        $checkStmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE course_id = ?");
        $checkStmt->execute([$courseId]);
        $course = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return [
                'status' => false,
                'message' => 'Course not found.'
            ];
        }
        
        // Check if the course is used in classes
        $classCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE course_id = ?");
        $classCheckStmt->execute([$courseId]);
        
        if ($classCheckStmt->fetchColumn() > 0) {
            return [
                'status' => false,
                'message' => "Cannot delete {$course['course_code']} - {$course['course_name']} because it is used in one or more classes."
            ];
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete prerequisites first
        try {
            $deletePrereqStmt = $pdo->prepare("DELETE FROM course_prerequisites WHERE course_id = ? OR prerequisite_course_id = ?");
            $deletePrereqStmt->execute([$courseId, $courseId]);
            error_log("Successfully deleted prerequisites for course: " . $courseId);
        } catch (PDOException $e) {
            error_log("Error deleting prerequisites: " . $e->getMessage());
            throw $e; // Re-throw to be caught by outer try-catch
        }
        
        // Delete the course
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
            $deleteStmt->execute([$courseId]);
            error_log("Successfully deleted course: " . $courseId);
        } catch (PDOException $e) {
            error_log("Error deleting course: " . $e->getMessage());
            throw $e; // Re-throw to be caught by outer try-catch
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Log activity to error_log
        error_log("Activity: User deleted course: {$course['course_code']} - {$course['course_name']}");
        
        
        return [
            'status' => true, 
            'message' => "Course {$course['course_code']} - {$course['course_name']} has been successfully deleted."
        ];
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error deleting course: " . $e->getMessage());
        return [
            'status' => false, 
            'message' => 'Database error occurred: ' . $e->getMessage()
        ];
    }
}
?>