<?php
/**
 * Grade Management Functions
 */

/**
 * Get grade data based on filters
 * 
 * @param PDO $pdo Database connection
 * @return array Array of grade data
 */
function get_GradeData($pdo) {
    $params = [];
    $whereClause = [];
    
    $sql = "SELECT ed.detail_id, ed.grade, ed.numeric_grade, ed.remarks, ed.status,
                  ed.date_added, ed.date_modified,
                  e.student_id, e.term_id,
                  s.first_name AS student_first_name, s.last_name AS student_last_name,
                  c.course_id, c.course_code, c.course_name,
                  cl.class_id, cl.section,
                  t.term_name, t.academic_year
           FROM enrollment_details ed
           JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
           JOIN students s ON e.student_id = s.student_id
           JOIN classes cl ON ed.class_id = cl.class_id
           JOIN courses c ON cl.course_id = c.course_id
           JOIN terms t ON e.term_id = t.term_id
           WHERE 1=1";
    
    // Filter by term if specified
    if (isset($_GET['term']) && !empty($_GET['term'])) {
        $whereClause[] = "e.term_id = :term_id";
        $params[':term_id'] = $_GET['term'];
    }
    
    // Filter by class if specified
    if (isset($_GET['class']) && !empty($_GET['class'])) {
        $whereClause[] = "cl.class_id = :class_id";
        $params[':class_id'] = $_GET['class'];
    }
    
    // Search by student name or ID
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $whereClause[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR e.student_id LIKE :search)";
        $params[':search'] = $searchTerm;
    }
    
    // Add where clause if any filters are applied
    if (!empty($whereClause)) {
        $sql .= " AND " . implode(" AND ", $whereClause);
    }
    
    // Order by term, student name
    $sql .= " ORDER BY t.academic_year DESC, t.term_name DESC, s.last_name ASC, s.first_name ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching grade data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get class data for grade selection
 * 
 * @param PDO $pdo Database connection
 * @return array Array of class data
 */
function get_ClassDataForGrades($pdo) {
    try {
        $sql = "SELECT cl.class_id, cl.section, c.course_code, c.course_name, t.term_name, t.academic_year
                FROM classes cl
                JOIN courses c ON cl.course_id = c.course_id
                JOIN terms t ON cl.term_id = t.term_id
                ORDER BY t.academic_year DESC, t.term_name DESC, c.course_code ASC, cl.section ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching class data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get term data for term selection
 * 
 * @param PDO $pdo Database connection
 * @return array Array of term data
 */
function get_TermData($pdo) {
    try {
        $sql = "SELECT term_id, term_name, academic_year, start_date, end_date, is_current
                FROM terms
                ORDER BY academic_year DESC, start_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching term data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get student data for student selection
 * 
 * @param PDO $pdo Database connection
 * @return array Array of student data
 */
function get_StudentData($pdo) {
    try {
        $sql = "SELECT s.student_id, s.first_name, s.last_name, s.email,
                       p.program_name, s.year_level,
                       u.status
                FROM students s
                LEFT JOIN programs p ON s.program_id = p.program_id
                JOIN users u ON s.user_id = u.user_id
                WHERE u.status = 'active'
                ORDER BY s.last_name ASC, s.first_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching student data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get available classes for a student in a term for grade entry
 * 
 * @param PDO $pdo Database connection
 * @param string $studentId Student ID
 * @param string $termId Term ID
 * @return array Array of available classes
 */
function get_available_classes_for_grading($pdo, $studentId, $termId) {
    try {
        // Validate input parameters
        if (empty($studentId) || empty($termId)) {
            error_log("get_available_classes_for_grading: Missing parameters - studentId: '$studentId', termId: '$termId'");
            return ['error' => 'Student ID and Term ID are required'];
        }
        
        error_log("get_available_classes_for_grading: Called with studentId: '$studentId', termId: '$termId'");
        
        // First, check if student exists
        $studentCheckSql = "SELECT student_id FROM students WHERE student_id = ?";
        $studentCheckStmt = $pdo->prepare($studentCheckSql);
        $studentCheckStmt->execute([$studentId]);
        if (!$studentCheckStmt->fetchColumn()) {
            error_log("get_available_classes_for_grading: Student '$studentId' not found");
            return ['error' => 'Student not found'];
        }
        
        // Check if term exists
        $termCheckSql = "SELECT term_id FROM terms WHERE term_id = ?";
        $termCheckStmt = $pdo->prepare($termCheckSql);
        $termCheckStmt->execute([$termId]);
        if (!$termCheckStmt->fetchColumn()) {
            error_log("get_available_classes_for_grading: Term '$termId' not found");
            return ['error' => 'Term not found'];
        }
        
        // Get the enrollment ID for this student and term
        $enrollSql = "SELECT enrollment_id, status FROM enrollments WHERE student_id = ? AND term_id = ?";
        $enrollStmt = $pdo->prepare($enrollSql);
        $enrollStmt->execute([$studentId, $termId]);
        $enrollment = $enrollStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enrollment) {
            error_log("get_available_classes_for_grading: No enrollment found for student '$studentId' in term '$termId'");
            
            // Debug: Show what enrollments exist for this student
            $debugSql = "SELECT enrollment_id, term_id, status FROM enrollments WHERE student_id = ?";
            $debugStmt = $pdo->prepare($debugSql);
            $debugStmt->execute([$studentId]);
            $studentEnrollments = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("get_available_classes_for_grading: Student has enrollments: " . json_encode($studentEnrollments));
            
            return ['classes' => [], 'message' => 'Student not enrolled in this term', 'debug' => $studentEnrollments];
        }
        
        $enrollmentId = $enrollment['enrollment_id'];
        error_log("get_available_classes_for_grading: Found enrollment '$enrollmentId' with status '{$enrollment['status']}'");
        
        // Get classes that student is enrolled in but doesn't have a grade yet
        // Using DISTINCT to avoid duplicates and proper NULL checking
        $sql = "SELECT DISTINCT cl.class_id, cl.section, c.course_code, c.course_name,
                       ed.detail_id, ed.grade, ed.status as enrollment_status
                FROM classes cl
                JOIN courses c ON cl.course_id = c.course_id
                JOIN enrollment_details ed ON cl.class_id = ed.class_id
                WHERE ed.enrollment_id = ?
                AND ed.status = 'enrolled'
                AND (ed.grade IS NULL OR ed.grade = '' OR ed.grade = 'NULL')
                ORDER BY c.course_code ASC, cl.section ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$enrollmentId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("get_available_classes_for_grading: Query executed, found " . count($classes) . " classes");
        
        if (count($classes) === 0) {
            // Debug: Check all enrollment details for this enrollment
            $debugDetailsSql = "SELECT ed.detail_id, ed.class_id, ed.status, ed.grade, 
                                       cl.section, c.course_code, c.course_name
                                FROM enrollment_details ed
                                JOIN classes cl ON ed.class_id = cl.class_id
                                JOIN courses c ON cl.course_id = c.course_id
                                WHERE ed.enrollment_id = ?";
            $debugDetailsStmt = $pdo->prepare($debugDetailsSql);
            $debugDetailsStmt->execute([$enrollmentId]);
            $allDetails = $debugDetailsStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("get_available_classes_for_grading: All enrollment details for this enrollment: " . json_encode($allDetails));
        }
        
        // Remove debug fields from the response for cleaner output
        $cleanClasses = array_map(function($class) {
            return [
                'class_id' => $class['class_id'],
                'section' => $class['section'],
                'course_code' => $class['course_code'],
                'course_name' => $class['course_name']
            ];
        }, $classes);
        
        error_log("get_available_classes_for_grading: Returning " . count($cleanClasses) . " classes for student '$studentId' in term '$termId'");
        return ['classes' => $cleanClasses];
        
    } catch (PDOException $e) {
        error_log("get_available_classes_for_grading: PDO Exception - " . $e->getMessage());
        error_log("get_available_classes_for_grading: PDO Error Info - " . json_encode($e->errorInfo));
        return ['error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("get_available_classes_for_grading: General Exception - " . $e->getMessage());
        return ['error' => 'Unexpected error: ' . $e->getMessage()];
    }
}

/**
 * Get grade details for a specific enrollment detail
 * 
 * @param PDO $pdo Database connection
 * @param string $detailId Enrollment detail ID
 * @return array Grade details and history
 */
function get_grade_details($pdo, $detailId) {
    try {
        error_log("get_grade_details: Called with detailId: '$detailId'");
          // Get the grade details
        $sql = "SELECT ed.detail_id, ed.grade, ed.numeric_grade, ed.remarks, ed.status,
                       ed.date_added, ed.date_modified,
                       e.student_id, e.term_id,
                       CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                       co.course_id, co.course_code, co.course_name,
                       cl.class_id, cl.section, cl.days_of_week, cl.start_time, cl.end_time, cl.room,
                       t.term_name, t.academic_year,
                       CONCAT(st.first_name, ' ', st.last_name) AS instructor_name,
                       st.staff_id AS instructor_id
                FROM enrollment_details ed
                JOIN enrollments e ON ed.enrollment_id = e.enrollment_id
                JOIN students s ON e.student_id = s.student_id
                JOIN classes cl ON ed.class_id = cl.class_id
                JOIN courses co ON cl.course_id = co.course_id
                JOIN terms t ON e.term_id = t.term_id
                LEFT JOIN staff st ON cl.instructor_id = st.staff_id
                WHERE ed.detail_id = :detail_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':detail_id' => $detailId]);
        $grade = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$grade) {
            error_log("get_grade_details: Grade not found for detailId: '$detailId'");
            return [
                'success' => false,
                'message' => 'Grade not found'
            ];
        }
        
        error_log("get_grade_details: Found grade data: " . json_encode($grade));
          // Get grade history
        $historySql = "SELECT gh.history_id, gh.previous_grade, gh.previous_numeric_grade,
                              gh.new_grade, gh.new_numeric_grade,
                              gh.changed_at AS change_date, gh.reason,
                              CONCAT(s.first_name, ' ', s.last_name) AS changed_by_name
                       FROM grade_history gh
                       JOIN staff s ON gh.changed_by = s.staff_id
                       WHERE gh.detail_id = :detail_id
                       ORDER BY gh.changed_at DESC";
        
        $historyStmt = $pdo->prepare($historySql);
        $historyStmt->execute([':detail_id' => $detailId]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("get_grade_details: Found " . count($history) . " history records");
        
        return [
            'success' => true,
            'message' => 'Grade details retrieved successfully',
            'grade' => $grade,
            'history' => $history
        ];
    } catch (PDOException $e) {
        error_log("get_grade_details: PDO Exception - " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Process grade-related requests
 * 
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Grade_Request($pdo) {
    $response = ['status' => false, 'message' => ''];
    
    error_log("process_Grade_Request: Called with GET parameters: " . json_encode($_GET));
    
    // Check for AJAX actions first
    if (isset($_GET['action'])) {
        error_log("process_Grade_Request: Processing action '{$_GET['action']}'");
        
        switch ($_GET['action']) {
            case 'get_available_classes':
                error_log("process_Grade_Request: Handling get_available_classes action");
                if (isset($_GET['student_id']) && isset($_GET['term_id'])) {
                    try {
                        $result = get_available_classes_for_grading($pdo, $_GET['student_id'], $_GET['term_id']);
                        error_log("process_Grade_Request: get_available_classes result: " . json_encode($result));
                        header('Content-Type: application/json');
                        echo json_encode($result);
                        exit;
                    } catch (Exception $e) {
                        error_log("process_Grade_Request: Exception in get_available_classes: " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Failed to get available classes: ' . $e->getMessage()]);
                        exit;
                    }
                } else {
                    error_log("process_Grade_Request: Missing parameters for get_available_classes");
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Missing required parameters: student_id and term_id']);
                    exit;
                }
                  case 'get_grade_details':
                error_log("process_Grade_Request: Handling get_grade_details action");
                if (isset($_GET['detail_id'])) {
                    try {
                        $result = get_grade_details($pdo, $_GET['detail_id']);
                        error_log("process_Grade_Request: get_grade_details result: " . json_encode($result));
                        header('Content-Type: application/json');
                        echo json_encode($result);
                        exit;
                    } catch (Exception $e) {
                        error_log("process_Grade_Request: Exception in get_grade_details: " . $e->getMessage());
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'message' => 'Failed to get grade details: ' . $e->getMessage()
                        ]);
                        exit;
                    }
                } else {
                    error_log("process_Grade_Request: Missing detail_id parameter for get_grade_details");
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required parameter: detail_id'
                    ]);
                    exit;
                }
        }
    } else {
        error_log("process_Grade_Request: No action parameter found");
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_grade':
                $response = add_Grade($pdo, $_POST);
                break;
                
            case 'edit_grade':
                $response = edit_Grade($pdo, $_POST);
                break;
                
            case 'delete_grade':
                $response = delete_Grade($pdo, $_POST);
                break;
        }
    }
    
    return $response;
}

/**
 * Add a new grade
 * 
 * @param PDO $pdo Database connection
 * @param array $data Form data
 * @return array Response with status and message
 */
function add_Grade($pdo, $data) {
    try {
        // Validate required fields
        $requiredFields = ['student_id', 'term_id', 'class_id', 'grade', 'staff_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return ['status' => false, 'message' => "Missing required field: $field"];
            }
        }
        
        // Start a transaction
        $pdo->beginTransaction();
        
        // Get the enrollment ID for this student and term
        $enrollSql = "SELECT enrollment_id FROM enrollments WHERE student_id = ? AND term_id = ?";
        $enrollStmt = $pdo->prepare($enrollSql);
        $enrollStmt->execute([$data['student_id'], $data['term_id']]);
        $enrollmentId = $enrollStmt->fetchColumn();
        
        if (!$enrollmentId) {
            $pdo->rollBack();
            return ['status' => false, 'message' => 'Student not enrolled in this term.'];
        }
        
        // Check if grade already exists
        $checkSql = "SELECT detail_id FROM enrollment_details WHERE enrollment_id = ? AND class_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$enrollmentId, $data['class_id']]);
        $existingDetailId = $checkStmt->fetchColumn();
        
        $numericGrade = !empty($data['numeric_grade']) ? $data['numeric_grade'] : null;
        $remarks = !empty($data['remarks']) ? $data['remarks'] : null;
        
        // Determine status based on grade
        $status = 'enrolled';
        if ($data['grade'] === 'DRP') {
            $status = 'dropped';
        } elseif ($data['grade'] === 'WDN') {
            $status = 'dropped';
        } elseif (!empty($data['grade'])) {
            $status = 'completed';
        }
        
        if ($existingDetailId) {
            // Update existing enrollment detail
            $updateSql = "UPDATE enrollment_details 
                          SET grade = ?, numeric_grade = ?, status = ?, remarks = ?,
                              date_modified = CURRENT_TIMESTAMP
                          WHERE detail_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $data['grade'],
                $numericGrade,
                $status,
                $remarks,
                $existingDetailId
            ]);
            
            // Add to grade history
            $historyId = 'GH-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $historySql = "INSERT INTO grade_history 
                          (history_id, detail_id, previous_grade, previous_numeric_grade, 
                           new_grade, new_numeric_grade, changed_by, reason)
                          VALUES (?, ?, NULL, NULL, ?, ?, ?, 'Initial grade entry')";
            $historyStmt = $pdo->prepare($historySql);
            $historyStmt->execute([
                $historyId,
                $existingDetailId,
                $data['grade'],
                $numericGrade,
                $data['staff_id']
            ]);
        } else {
            // Create new enrollment detail
            $detailId = 'ED-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $insertSql = "INSERT INTO enrollment_details 
                          (detail_id, enrollment_id, class_id, status, grade, numeric_grade, remarks)
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $detailId,
                $enrollmentId,
                $data['class_id'],
                $status,
                $data['grade'],
                $numericGrade,
                $remarks
            ]);
            
            // Add to grade history
            $historyId = 'GH-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $historySql = "INSERT INTO grade_history 
                          (history_id, detail_id, previous_grade, previous_numeric_grade, 
                           new_grade, new_numeric_grade, changed_by, reason)
                          VALUES (?, ?, NULL, NULL, ?, ?, ?, 'Initial grade entry')";
            $historyStmt = $pdo->prepare($historySql);
            $historyStmt->execute([
                $historyId,
                $detailId,
                $data['grade'],
                $numericGrade,
                $data['staff_id']
            ]);
        }
        
        $pdo->commit();
        return ['status' => true, 'message' => 'Grade successfully added.'];
    } catch (PDOException $e) {
        $pdo->rollBack();        error_log("Error adding grade: " . $e->getMessage());
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Edit an existing grade
 * 
 * @param PDO $pdo Database connection
 * @param array $data Form data
 * @return array Response with status and message
 */
function edit_Grade($pdo, $data) {
    try {
        // Validate required fields
        $requiredFields = ['detail_id', 'grade', 'staff_id', 'reason'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return ['status' => false, 'message' => "Missing required field: $field"];
            }
        }
        
        // Start a transaction
        $pdo->beginTransaction();
        
        // Get current grade data
        $getSql = "SELECT grade, numeric_grade FROM enrollment_details WHERE detail_id = ?";
        $getStmt = $pdo->prepare($getSql);
        $getStmt->execute([$data['detail_id']]);
        $currentGrade = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentGrade) {
            $pdo->rollBack();
            return ['status' => false, 'message' => 'Grade not found.'];
        }
        
        $numericGrade = !empty($data['numeric_grade']) ? $data['numeric_grade'] : null;
        $remarks = !empty($data['remarks']) ? $data['remarks'] : null;
        
        // Determine status based on grade
        $status = 'enrolled';
        if ($data['grade'] === 'DRP') {
            $status = 'dropped';
        } elseif ($data['grade'] === 'WDN') {
            $status = 'dropped';
        } elseif (!empty($data['grade'])) {
            $status = 'completed';
        }
        
        // Update the grade
        $updateSql = "UPDATE enrollment_details 
                      SET grade = ?, numeric_grade = ?, status = ?, remarks = ?,
                          date_modified = CURRENT_TIMESTAMP
                      WHERE detail_id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            $data['grade'],
            $numericGrade,
            $status,
            $remarks,
            $data['detail_id']
        ]);
        
        // Add to grade history
        $historyId = 'GH-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $historySql = "INSERT INTO grade_history 
                      (history_id, detail_id, previous_grade, previous_numeric_grade, 
                       new_grade, new_numeric_grade, changed_by, reason)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $historyStmt = $pdo->prepare($historySql);
        $historyStmt->execute([
            $historyId,
            $data['detail_id'],
            $currentGrade['grade'],
            $currentGrade['numeric_grade'],
            $data['grade'],
            $numericGrade,
            $data['staff_id'],
            $data['reason']
        ]);
        
        $pdo->commit();
        return ['status' => true, 'message' => 'Grade successfully updated.'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error editing grade: " . $e->getMessage());
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Delete a grade
 * 
 * @param PDO $pdo Database connection
 * @param array $data Form data
 * @return array Response with status and message
 */
function delete_Grade($pdo, $data) {
    try {
        // Validate required fields
        if (!isset($data['detail_id']) || empty($data['detail_id'])) {
            return ['status' => false, 'message' => 'Missing detail ID.'];
        }
        
        // Start a transaction
        $pdo->beginTransaction();
        
        // Delete grade history first (foreign key constraint)
        $deleteHistorySql = "DELETE FROM grade_history WHERE detail_id = ?";
        $deleteHistoryStmt = $pdo->prepare($deleteHistorySql);
        $deleteHistoryStmt->execute([$data['detail_id']]);
        
        // Then delete the enrollment detail
        $deleteDetailSql = "DELETE FROM enrollment_details WHERE detail_id = ?";
        $deleteDetailStmt = $pdo->prepare($deleteDetailSql);
        $deleteDetailStmt->execute([$data['detail_id']]);
        
        $pdo->commit();
        return ['status' => true, 'message' => 'Grade successfully deleted.'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting grade: " . $e->getMessage());
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get CSS class for grade badge color
 * 
 * @param string $grade Letter grade
 * @return string CSS class for the badge
 */
function getGradeColor($grade) {
    switch ($grade) {
        case 'A':
        case 'A-':
            return 'bg-success';
        case 'B+':
        case 'B':
        case 'B-':
            return 'bg-primary';
        case 'C+':
        case 'C':
        case 'C-':
            return 'bg-warning';
        case 'D+':
        case 'D':
            return 'bg-danger';
        case 'F':
            return 'bg-danger';
        case 'INC':
            return 'bg-warning';
        case 'DRP':
        case 'WDN':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}
?>