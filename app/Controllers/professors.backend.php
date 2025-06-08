<?php
/**
 * Functions for handling staff/professors data and operations in the SiES system
 */

/**
 * Translate access_level to user role
 * 
 * @param string $accessLevel The access level from staff table
 * @return string The corresponding role for users table
 */
function translateAccessLevelToRole($accessLevel)
{
    switch ($accessLevel) {
        case 'administrator':
            return 'admin';
        case 'supervisor':
        case 'regular':
        default:
            return 'staff';
    }
}

/**
 * Update existing staff user roles based on their access levels
 * This function can be called to sync existing data
 * 
 * @param PDO $pdo Database connection
 * @return array Result with status and message
 */
function syncStaffUserRoles($pdo)
{
    $response = [
        'status' => false,
        'message' => '',
        'updated_count' => 0
    ];
    
    try {
        $pdo->beginTransaction();
        
        // Get all staff with their current access levels
        $stmt = $pdo->prepare("
            SELECT s.staff_id, s.user_id, s.access_level, u.role as current_role
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
        ");
        $stmt->execute();
        $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updatedCount = 0;
        foreach ($staffMembers as $staff) {
            $correctRole = translateAccessLevelToRole($staff['access_level']);
            
            // Only update if the role is different
            if ($staff['current_role'] !== $correctRole) {
                $updateStmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $updateStmt->execute([$correctRole, $staff['user_id']]);
                $updatedCount++;
            }
        }
        
        $pdo->commit();
        
        $response['status'] = true;
        $response['message'] = "Successfully synchronized {$updatedCount} staff user roles.";
        $response['updated_count'] = $updatedCount;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error syncing staff user roles: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    return $response;
}

/**
 * Process all professor/staff-related requests (add, edit, delete, get)
 * 
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Professor_Request($pdo)
{
    $response = [
        'status' => false,
        'message' => '',
    ];
    
    // Process GET actions (delete, get professor details)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];
        switch ($action) {
            case 'get':
                if (isset($_GET['id'])) {
                    error_log("Processing 'get' action for staff ID: " . $_GET['id']);
                    
                    $staff = get_StaffById($pdo, $_GET['id']);
                    if ($staff) {
                        // Output as JSON and exit
                        error_log("Sending JSON response for staff: " . $staff['first_name'] . ' ' . $staff['last_name']);
                        header('Content-Type: application/json');
                        echo json_encode($staff);
                        exit;
                    } else {
                        // Return error as JSON
                        error_log("Staff not found with ID: " . $_GET['id']);
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Staff not found']);
                        exit;
                    }
                } else {
                    // Return error as JSON
                    error_log("No staff ID provided in 'get' action");
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Staff ID not provided']);
                    exit;
                }
                
            case 'delete':
                // Debug all GET parameters
                error_log('DELETE action - All GET params: ' . print_r($_GET, true));
                
                if (isset($_GET['id'])) {
                    $staffId = $_GET['id'];
                    error_log("Processing 'delete' action for staff ID: " . $staffId);
                    
                    $deleteResult = delete_Staff($pdo, $staffId);
                    if ($deleteResult['status']) {
                        $response['status'] = true;
                        $response['message'] = 'Staff member deleted successfully.';
                        error_log("Staff deleted successfully: " . $staffId);
                    } else {
                        $response['message'] = $deleteResult['message'];
                        error_log("Failed to delete staff: " . $deleteResult['message']);
                    }
                } else {
                    $response['message'] = 'Staff ID not provided.';
                    error_log("No staff ID provided for delete action");
                }
                break;
                
            default:
                $response['message'] = 'Invalid action.';
                error_log("Invalid GET action: " . $action);
                break;
        }
    }
    
    // Process POST actions (add, edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'add':
                error_log("Processing 'add' action for new staff");
                error_log('POST data: ' . print_r($_POST, true));
                
                $result = add_Staff($pdo, $_POST);
                if ($result['status']) {
                    $response['status'] = true;
                    $response['message'] = 'Staff member added successfully.';
                    error_log("Staff added successfully");
                } else {
                    $response['message'] = $result['message'];
                    error_log("Failed to add staff: " . $result['message']);
                }
                break;
                
            case 'edit':
                error_log("Processing 'edit' action");
                error_log('POST data: ' . print_r($_POST, true));
                
                if (!isset($_POST['staff_id']) || empty($_POST['staff_id'])) {
                    $response['message'] = 'Staff ID is required for editing.';
                    error_log("No staff ID provided for edit action");
                    break;
                }
                
                $result = edit_Staff($pdo, $_POST);
                if ($result['status']) {
                    $response['status'] = true;
                    $response['message'] = 'Staff member updated successfully.';
                    error_log("Staff updated successfully: " . $_POST['staff_id']);
                } else {
                    $response['message'] = $result['message'];
                    error_log("Failed to update staff: " . $result['message']);
                }
                break;
                
            default:
                $response['message'] = 'Invalid action.';
                error_log("Invalid POST action: " . $action);
                break;
        }
    }
    
    return $response;
}

/**
 * Generate the next staff ID in format SF-00000
 * 
 * @param PDO $pdo Database connection
 * @return string Next staff ID
 */
function generateNextStaffId($pdo)
{
    try {
        $stmt = $pdo->query("SELECT staff_id FROM staff ORDER BY staff_id DESC LIMIT 1");
        $lastStaff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastStaff) {
            // Extract the numeric part and increment
            $lastNumber = intval(substr($lastStaff['staff_id'], 3));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return 'SF-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        error_log("Error generating staff ID: " . $e->getMessage());
        return 'SF-00001'; // Fallback
    }
}

/**
 * Generate the next user ID in format US-00000
 * 
 * @param PDO $pdo Database connection
 * @return string Next user ID
 */
function generateNextUserId($pdo)
{
    try {
        $stmt = $pdo->query("SELECT user_id FROM users ORDER BY user_id DESC LIMIT 1");
        $lastUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastUser) {
            // Extract the numeric part and increment
            $lastNumber = intval(substr($lastUser['user_id'], 3));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return 'US-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        error_log("Error generating user ID: " . $e->getMessage());
        return 'US-00001'; // Fallback
    }
}

/**
 * Add a new staff member
 * 
 * @param PDO $pdo Database connection
 * @param array $data Staff data from form
 * @return array Result with status and message
 */
function add_Staff($pdo, $data)
{
    $response = [
        'status' => false,
        'message' => ''
    ];
    
    try {
        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'department', 'position', 'email', 'login_id', 'password'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $response['message'] = "Field '{$field}' is required.";
                return $response;
            }
        }
        
        // Validate login_id format
        if (!preg_match('/^[A-Z0-9]{2}-\d{5}$/', $data['login_id'])) {
            $response['message'] = 'Login ID must be in format XX-00000 (e.g., SF-00001)';
            return $response;
        }
        
        // Check if login_id already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE login_id = ?");
        $stmt->execute([$data['login_id']]);
        if ($stmt->fetch()) {
            $response['message'] = 'Login ID already exists.';
            return $response;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT staff_id FROM staff WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $response['message'] = 'Email already exists.';
            return $response;
        }
        
        $pdo->beginTransaction();
        
        // Generate IDs
        $userId = generateNextUserId($pdo);
        $staffId = generateNextStaffId($pdo);
          // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Determine access level and corresponding role
        $accessLevel = isset($data['access_level']) ? $data['access_level'] : 'regular';
        $userRole = translateAccessLevelToRole($accessLevel);
        
        // Insert into users table
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, login_id, password, role, status) 
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$userId, $data['login_id'], $hashedPassword, $userRole]);
          // Insert into staff table
        $stmt = $pdo->prepare("
            INSERT INTO staff (staff_id, user_id, first_name, last_name, department, position, phone_number, email, access_level) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $phoneNumber = isset($data['phone_number']) ? $data['phone_number'] : null;
        
        $stmt->execute([
            $staffId,
            $userId,
            trim($data['first_name']),
            trim($data['last_name']),
            trim($data['department']),
            trim($data['position']),
            $phoneNumber,
            trim($data['email']),
            $accessLevel
        ]);
        
        $pdo->commit();
        
        $response['status'] = true;
        $response['message'] = 'Staff member added successfully.';
        $response['staff_id'] = $staffId;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding staff: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    return $response;
}

/**
 * Edit an existing staff member
 * 
 * @param PDO $pdo Database connection
 * @param array $data Staff data from form
 * @return array Result with status and message
 */
function edit_Staff($pdo, $data)
{
    $response = [
        'status' => false,
        'message' => ''
    ];
    
    try {
        // Validate required fields
        $requiredFields = ['staff_id', 'first_name', 'last_name', 'department', 'position', 'email'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $response['message'] = "Field '{$field}' is required.";
                return $response;
            }
        }
        
        // Check if staff exists
        $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
        $stmt->execute([$data['staff_id']]);
        $staffRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staffRecord) {
            $response['message'] = 'Staff member not found.';
            return $response;
        }
        
        // Check if email already exists for another staff member
        $stmt = $pdo->prepare("SELECT staff_id FROM staff WHERE email = ? AND staff_id != ?");
        $stmt->execute([$data['email'], $data['staff_id']]);
        if ($stmt->fetch()) {
            $response['message'] = 'Email already exists for another staff member.';
            return $response;
        }
        
        $pdo->beginTransaction();
        
        // Update staff table
        $stmt = $pdo->prepare("
            UPDATE staff 
            SET first_name = ?, last_name = ?, department = ?, position = ?, 
                phone_number = ?, email = ?, access_level = ?
            WHERE staff_id = ?
        ");
        
        $accessLevel = isset($data['access_level']) ? $data['access_level'] : 'regular';
        $phoneNumber = isset($data['phone_number']) ? $data['phone_number'] : null;
          $stmt->execute([
            trim($data['first_name']),
            trim($data['last_name']),
            trim($data['department']),
            trim($data['position']),
            $phoneNumber,
            trim($data['email']),
            $accessLevel,
            $data['staff_id']
        ]);
        
        // Update user role based on access level
        $userRole = translateAccessLevelToRole($accessLevel);
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $stmt->execute([$userRole, $staffRecord['user_id']]);
        
        // Update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashedPassword, $staffRecord['user_id']]);
        }
        
        // Update login_id if provided and different
        if (isset($data['login_id']) && !empty($data['login_id'])) {
            // Validate login_id format
            if (!preg_match('/^[A-Z0-9]{2}-\d{5}$/', $data['login_id'])) {
                $pdo->rollBack();
                $response['message'] = 'Login ID must be in format XX-00000 (e.g., SF-00001)';
                return $response;
            }
            
            // Check if login_id already exists for another user
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE login_id = ? AND user_id != ?");
            $stmt->execute([$data['login_id'], $staffRecord['user_id']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $response['message'] = 'Login ID already exists for another user.';
                return $response;
            }
            
            $stmt = $pdo->prepare("UPDATE users SET login_id = ? WHERE user_id = ?");
            $stmt->execute([$data['login_id'], $staffRecord['user_id']]);
        }
        
        $pdo->commit();
        
        $response['status'] = true;
        $response['message'] = 'Staff member updated successfully.';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating staff: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    return $response;
}

/**
 * Delete a staff member
 * 
 * @param PDO $pdo Database connection
 * @param string $staffId Staff ID to delete
 * @return array Result with status and message
 */
function delete_Staff($pdo, $staffId)
{
    $response = [
        'status' => false,
        'message' => ''
    ];
    
    try {
        // Check if staff exists and get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
        $stmt->execute([$staffId]);
        $staffRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staffRecord) {
            $response['message'] = 'Staff member not found.';
            return $response;
        }
        
        // Check if staff is assigned to any classes as instructor
        $stmt = $pdo->prepare("SELECT COUNT(*) as class_count FROM classes WHERE instructor_id = ?");
        $stmt->execute([$staffId]);
        $classCount = $stmt->fetch(PDO::FETCH_ASSOC)['class_count'];
        
        if ($classCount > 0) {
            $response['message'] = 'Cannot delete staff member. They are assigned as instructor to ' . $classCount . ' class(es).';
            return $response;
        }
        
        // Check if staff has approved enrollments
        $stmt = $pdo->prepare("SELECT COUNT(*) as approval_count FROM enrollments WHERE approved_by = ?");
        $stmt->execute([$staffId]);
        $approvalCount = $stmt->fetch(PDO::FETCH_ASSOC)['approval_count'];
        
        if ($approvalCount > 0) {
            $response['message'] = 'Cannot delete staff member. They have approved ' . $approvalCount . ' enrollment(s).';
            return $response;
        }
          $pdo->beginTransaction();
        
        // Delete from users table (staff will be deleted by CASCADE)
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$staffRecord['user_id']]);
        
        $pdo->commit();
        
        $response['status'] = true;
        $response['message'] = 'Staff member deleted successfully.';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting staff: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    return $response;
}

/**
 * Get staff member by ID with user information
 * 
 * @param PDO $pdo Database connection
 * @param string $staffId Staff ID
 * @return array|false Staff data or false if not found
 */
function get_StaffById($pdo, $staffId)
{
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.login_id, u.role, u.status as user_status, u.created_at, u.last_login
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.staff_id = ?
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting staff by ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all staff members with pagination
 * 
 * @param PDO $pdo Database connection
 * @param int $page Page number (1-based)
 * @param int $perPage Records per page
 * @param string $search Search term (optional)
 * @param string $department Filter by department (optional)
 * @return array Staff data with pagination info
 */
function get_AllStaff($pdo, $page = 1, $perPage = 10, $search = '', $department = '')
{
    try {
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR u.login_id LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($department)) {
            $whereConditions[] = "s.department = ?";
            $params[] = $department;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
            {$whereClause}
        ";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get staff data
        $sql = "
            SELECT s.*, u.login_id, u.role, u.status as user_status, u.created_at, u.last_login
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
            {$whereClause}
            ORDER BY s.last_name, s.first_name
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'staff' => $staff,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting all staff: " . $e->getMessage());
        return [
            'staff' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'total_pages' => 0
            ]
        ];
    }
}

/**
 * Get all unique departments from staff table
 * 
 * @param PDO $pdo Database connection
 * @return array List of departments
 */
function get_AllDepartments($pdo)
{
    try {
        $stmt = $pdo->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL ORDER BY department");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get staff members by department
 * 
 * @param PDO $pdo Database connection
 * @param string $department Department name
 * @return array Staff data
 */
function get_StaffByDepartment($pdo, $department)
{
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.login_id, u.status as user_status
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.department = ? AND u.status = 'active'
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$department]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting staff by department: " . $e->getMessage());
        return [];
    }
}

/**
 * Get staff members who can be instructors (for class assignment)
 * 
 * @param PDO $pdo Database connection
 * @return array Staff data suitable for instructor assignment
 */
function get_InstructorsList($pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT s.staff_id, s.first_name, s.last_name, s.department, s.position
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
            WHERE u.status = 'active'
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting instructors list: " . $e->getMessage());
        return [];
    }
}

/**
 * Update staff status (activate/deactivate through user table)
 * 
 * @param PDO $pdo Database connection
 * @param string $staffId Staff ID
 * @param string $status New status ('active', 'inactive', 'suspended')
 * @return array Result with status and message
 */
function update_StaffStatus($pdo, $staffId, $status)
{
    $response = [
        'status' => false,
        'message' => ''
    ];
    
    $validStatuses = ['active', 'inactive', 'suspended'];
    if (!in_array($status, $validStatuses)) {
        $response['message'] = 'Invalid status. Must be one of: ' . implode(', ', $validStatuses);
        return $response;
    }
    
    try {
        // Get user_id from staff table
        $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
        $stmt->execute([$staffId]);
        $staffRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staffRecord) {
            $response['message'] = 'Staff member not found.';
            return $response;
        }
        
        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->execute([$status, $staffRecord['user_id']]);
        
        $response['status'] = true;
        $response['message'] = 'Staff status updated successfully.';
        
    } catch (PDOException $e) {
        error_log("Error updating staff status: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    return $response;
}
?>