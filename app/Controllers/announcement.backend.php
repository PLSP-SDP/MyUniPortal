<?php
/**
 * Handles all announcement operations (add, update, delete)
 * 
 * @param PDO $pdo Database connection
 * @param string $action Action to perform (add, update, delete)
 * @param array $data Form data for add/update operations
 * @param string $id Announcement ID for delete operation
 * @return array Response with status and message
 */

function data_Announcement($pdo)
{
    try {
        // Start building the SQL query
        $sql = "
            SELECT 
                a.announcement_id,
                a.title,
                a.content,
                a.created_by,
                CONCAT(s.first_name, ' ', s.last_name) AS created_by_name,
                a.created_at,
                a.start_date,
                a.end_date,
                a.is_public,
                a.target_role
            FROM 
                announcements a
            LEFT JOIN 
                staff s ON a.created_by = s.staff_id
            WHERE 1=1
        ";

        $params = [];

        // Apply visibility filter if set
        if (isset($_GET['visibility']) && $_GET['visibility'] !== '') {
            $sql .= " AND a.is_public = :visibility";
            $params['visibility'] = $_GET['visibility'];
        }

        // Apply role filter if set
        if (isset($_GET['role']) && $_GET['role'] !== '') {
            $sql .= " AND (a.target_role = :role OR a.target_role = 'all')";
            $params['role'] = $_GET['role'];
        }

        // Log the query and parameters for debugging
        error_log("Announcement SQL query: " . $sql);
        error_log("Announcement parameters: " . print_r($params, true));

        // Order by created_at DESC
        $sql .= " ORDER BY a.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching announcements: " . $e->getMessage());
        return [];
    }
}
function handle_Announcement($pdo, $action, $data = [], $id = '')
{
    $response = [
        'status' => false,
        'message' => '',
    ];

    try {
        switch ($action) {
            case 'add':
                // Validate required fields
                if (empty($data['title']) || empty($data['content']) || empty($data['staff_id'])) {
                    $response['message'] = "Title, content, and staff ID are required.";
                    return $response;
                }

                // Verify staff_id exists in the staff table
                $stmt = $pdo->prepare("SELECT staff_id, CONCAT(first_name, ' ', last_name) as full_name FROM staff WHERE staff_id = :staff_id");
                $stmt->bindParam(':staff_id', $data['staff_id'], PDO::PARAM_STR);
                $stmt->execute();
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$staff) {
                    $response['message'] = "Invalid staff ID provided.";
                    return $response;
                }

                // Generate new announcement ID
                $stmt = $pdo->query("SELECT MAX(SUBSTRING(announcement_id, 4)) as max_id FROM announcements");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $next_id = ($result['max_id']) ? intval($result['max_id']) + 1 : 1;
                $announcement_id = 'AN-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);

                // Insert new announcement
                $sql = "INSERT INTO announcements (
                            announcement_id, title, content, created_by, 
                            start_date, end_date, is_public, target_role
                        ) VALUES (
                            :announcement_id, :title, :content, :created_by, 
                            :start_date, :end_date, :is_public, :target_role
                        )";

                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':announcement_id', $announcement_id, PDO::PARAM_STR);
                $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
                $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
                $stmt->bindParam(':created_by', $staff['staff_id'], PDO::PARAM_STR);
                $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
                $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
                $stmt->bindParam(':is_public', $data['is_public'], PDO::PARAM_INT);
                $stmt->bindParam(':target_role', $data['target_role'], PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $response['status'] = true;
                    $response['message'] = "Announcement posted successfully.";

                } else {
                    $response['message'] = "Failed to post announcement.";
                }
                break;

            case 'update':
                // Validate required fields
                if (empty($data['announcement_id']) || empty($data['title']) || empty($data['content'])) {
                    $response['message'] = "Announcement ID, title, and content are required.";
                    return $response;
                }

                // Update existing announcement
                $sql = "UPDATE announcements 
                        SET title = :title,
                            content = :content,
                            start_date = :start_date,
                            end_date = :end_date,
                            is_public = :is_public,
                            target_role = :target_role
                        WHERE announcement_id = :announcement_id";

                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':announcement_id', $data['announcement_id'], PDO::PARAM_STR);
                $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
                $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
                $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
                $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
                $stmt->bindParam(':is_public', $data['is_public'], PDO::PARAM_INT);
                $stmt->bindParam(':target_role', $data['target_role'], PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $response['status'] = true;
                    $response['message'] = "Announcement updated successfully.";

                    // Log activity if function exists
                    if (function_exists('log_activity') && isset($_SESSION['user_id'])) {
                        log_activity($pdo, $_SESSION['user_id'], 'update_announcement', "Updated announcement: {$data['title']}");
                    }
                } else {
                    $response['message'] = "Failed to update announcement.";
                }
                break;

            case 'delete':
                // Validate required fields
                if (empty($id)) {
                    $response['message'] = "Announcement ID is required.";
                    return $response;
                }

                // Get announcement title for logging before deletion
                $stmt = $pdo->prepare("SELECT title FROM announcements WHERE announcement_id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_STR);
                $stmt->execute();
                $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

                // Delete announcement
                $sql = "DELETE FROM announcements WHERE announcement_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $response['status'] = true;
                    $response['message'] = "Announcement deleted successfully.";

                    // Log activity if function exists
                    if (function_exists('log_activity') && isset($_SESSION['user_id']) && isset($announcement['title'])) {
                        log_activity($pdo, $_SESSION['user_id'], 'delete_announcement', "Deleted announcement: {$announcement['title']}");
                    }
                } else {
                    $response['message'] = "Failed to delete announcement.";
                }
                break;

            default:
                $response['message'] = "Invalid action.";
                break;
        }
    } catch (PDOException $e) {
        error_log("Error in handle_Announcement: " . $e->getMessage());
        $response['message'] = "Database error occurred: " . $e->getMessage();
    }

    return $response;
}

/**
 * Process announcement actions and form submissions
 * This function should be called in your controller file
 * 
 * @param PDO $pdo Database connection
 * @return array Response with status and message
 */
function process_Announcement_Request($pdo)
{
    $response = [
        'status' => false,
        'message' => ''
    ];

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['subpage']) && $_GET['subpage'] === 'Announcement') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        if ($action === 'add' || $action === 'update') {
            $response = handle_Announcement($pdo, $action, $_POST);
        }
    }

    // Process delete action
    if (
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        isset($_GET['subpage']) && $_GET['subpage'] === 'Announcement' &&
        isset($_GET['action']) && $_GET['action'] === 'delete' &&
        isset($_GET['id'])
    ) {

        $response = handle_Announcement($pdo, 'delete', [], $_GET['id']);
    }

    return $response;
}

/**
 * Get active announcements for display on dashboards, etc.
 * 
 * @param PDO $pdo Database connection
 * @param string $role User role for targeted announcements
 * @param int $limit Maximum number of announcements to return (0 for all)
 * @return array Array of active announcements
 */
function get_Active_Announcements($pdo, $role = 'all', $limit = 5)
{
    try {
        $today = date('Y-m-d');

        $sql = "SELECT * FROM announcements
                WHERE (start_date IS NULL OR start_date <= :today)
                AND (end_date IS NULL OR end_date >= :today)
                AND (is_public = 1 OR target_role = 'all' OR target_role = :role)
                ORDER BY created_at DESC";

        if ($limit > 0) {
            $sql .= " LIMIT :limit";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':today', $today, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);

        if ($limit > 0) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching active announcements: " . $e->getMessage());
        return [];
    }
}
?>