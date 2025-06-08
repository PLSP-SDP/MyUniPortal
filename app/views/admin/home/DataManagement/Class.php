<?php
$class_response = process_Class_Request($pdo);
$staffId = getStaffIdByUserId($userID);

// Make sure search term is preserved
if (isset($_GET['search'])) {
    $_GET['search'] = trim($_GET['search']);
}

// Add debug function to check database content
function debugCheckClasses($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM classes");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Total classes in database: " . ($result['total'] ?? 'unknown'));
        
        if ($result['total'] > 0) {
            $sample = $pdo->query("SELECT class_id FROM classes LIMIT 5");
            $sampleData = $sample->fetchAll(PDO::FETCH_ASSOC);
            error_log("Sample class IDs: " . json_encode($sampleData));
        }
        
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error checking classes: " . $e->getMessage());
        return 0;
    }
}

// Check if database has any classes at all
$totalClasses = debugCheckClasses($pdo);

// Get class data with filters from URL parameters ($_GET)
$classData = get_ClassData($pdo);

// Debug information for search results
if (isset($_GET['search']) && $_GET['search'] !== '') {
    error_log("Search term: " . $_GET['search']);
    error_log("Results found: " . count($classData));
    error_log("Total classes in DB: " . $totalClasses);
}

// Get other data for dropdowns
$courses = get_AllCourses($pdo);
$terms = get_AllTerms($pdo);
$instructors = get_AllInstructors($pdo);
?>

<div>
    <div class="navbg p-3 rounded rounded-3 shadow-sm">
        <div class="d-flex justify-content-between flex-wrap align-items-center">
            <h5 class="mb-0">Class Management</h5>
            <div>
                <button type="button" class="btn btn-outline-secondary me-2" id="refreshClassBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    Add Class <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Container for system messages -->
    <div id="alertContainer" class="mt-3">
        <?php if (isset($_SESSION['class_response']) && !empty($_SESSION['class_response']['message'])): ?>
            <div id="persistentAlert" class="alert <?= $_SESSION['class_response']['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['class_response']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['class_response']); // Clear after displaying ?>
        <?php endif; ?>
    </div>

    <!-- Search and Filter Controls -->
    <div class="card shadow-sm mb-3 mt-3">
        <div class="card-body">
            <form id="classSearchForm" class="row g-3" method="GET">
                <input type="hidden" name="page" value="Manage">
                <input type="hidden" name="subpage" value="Class">
                
                <div class="col-md-3">
                    <label for="filterTerm" class="form-label">Term</label>
                    <select class="form-select form-select-sm" id="filterTerm" name="term">
                        <option value="">All Terms</option>
                        <?php foreach ($terms as $term): ?>
                            <option value="<?= htmlspecialchars($term['term_id']) ?>" <?= isset($_GET['term']) && $_GET['term'] === $term['term_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($term['term_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filterCourse" class="form-label">Course</label>
                    <select class="form-select form-select-sm" id="filterCourse" name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= htmlspecialchars($course['course_id']) ?>" <?= isset($_GET['course']) && $_GET['course'] === $course['course_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filterInstructor" class="form-label">Instructor</label>
                    <select class="form-select form-select-sm" id="filterInstructor" name="instructor">
                        <option value="">All Instructors</option>
                        <?php foreach ($instructors as $instructor): ?>
                            <option value="<?= htmlspecialchars($instructor['staff_id']) ?>" <?= isset($_GET['instructor']) && $_GET['instructor'] === $instructor['staff_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($instructor['last_name']) ?>, <?= htmlspecialchars($instructor['first_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="filterStatus" name="status">
                        <option value="">All Statuses</option>
                        <option value="open" <?= isset($_GET['status']) && $_GET['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="closed" <?= isset($_GET['status']) && $_GET['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="cancelled" <?= isset($_GET['status']) && $_GET['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-9">
                    <label for="searchClass" class="form-label">Search</label>
                    <input type="text" class="form-control form-control-sm" id="searchClass" name="search" 
                           placeholder="Search by class section or course code/name..." 
                           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                </div>
                  <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success btn-sm w-100" id="searchClassBtn">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <!-- Debug info to verify search parameters -->
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                <div class="col-12">
                    <small class="text-muted">Searching for: "<?= htmlspecialchars($_GET['search']) ?>"</small>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">Class ID</th>
                            <th width="15%">Course</th>
                            <th width="10%">Section</th>
                            <th width="15%">Schedule</th>
                            <th width="15%">Instructor</th>
                            <th width="10%">Room</th>
                            <th width="10%">Status</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classData)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-search fs-1 text-muted mb-2"></i>
                                        <p class="mb-0">No Classes found.</p>
                                        <p class="text-muted small">Try adjusting your search criteria or add a new course.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classData as $class): ?>
                                <?php 
                                    // Format schedule
                                    $schedule = $class['days_of_week'] . ' ' . 
                                               date('g:ia', strtotime($class['start_time'])) . ' - ' . 
                                               date('g:ia', strtotime($class['end_time']));
                                               
                                    // Get enrollment count
                                    $enrolledCount = get_EnrolledCount($pdo, $class['class_id']);
                                    
                                    // Determine status badge class
                                    $statusBadgeClass = '';
                                    switch ($class['status']) {
                                        case 'open':
                                            $statusBadgeClass = 'bg-success';
                                            break;
                                        case 'closed':
                                            $statusBadgeClass = 'bg-warning';
                                            break;
                                        case 'cancelled':
                                            $statusBadgeClass = 'bg-danger';
                                            break;
                                        default:
                                            $statusBadgeClass = 'bg-secondary';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($class['class_id']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($class['course_code']) ?></strong><br>
                                        <small><?= htmlspecialchars($class['course_name']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($class['section']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($schedule) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($class['term_name']) ?></small>
                                    </td>
                                    <td>
                                        <?= $class['instructor_name'] ? htmlspecialchars($class['instructor_name']) : '<span class="text-muted">Not assigned</span>' ?>
                                    </td>
                                    <td><?= htmlspecialchars($class['room']) ?></td>
                                    <td>
                                        <span class="badge <?= $statusBadgeClass ?>">
                                            <?= ucfirst(htmlspecialchars($class['status'])) ?>
                                        </span>
                                        <br>
                                        <small><?= $enrolledCount ?>/<?= $class['max_students'] ?> enrolled</small>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info viewClassBtn mb-1" 
                                                data-bs-toggle="modal" data-bs-target="#viewClassModal"
                                                data-class-id="<?= htmlspecialchars($class['class_id']) ?>"
                                                data-bs-toggle="tooltip" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary editClassBtn mb-1"
                                                data-bs-toggle="modal" data-bs-target="#editClassModal"
                                                data-class-id="<?= htmlspecialchars($class['class_id']) ?>"
                                                data-bs-toggle="tooltip" title="Edit Class">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($enrolledCount == 0): ?>
                                            <button type="button" class="btn btn-sm btn-danger deleteClassBtn mb-1"
                                                    data-class-id="<?= htmlspecialchars($class['class_id']) ?>"
                                                    data-course-code="<?= htmlspecialchars($class['course_code']) ?>"
                                                    data-section="<?= htmlspecialchars($class['section']) ?>"
                                                    data-bs-toggle="tooltip" title="Delete Class">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary mb-1" disabled
                                                    data-bs-toggle="tooltip" title="Cannot delete class with enrolled students">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">
                        Showing <?= count($classData) ?> classes
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="addClassModalLabel">Add New Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addClassForm" method="post" action="?page=Manage&subpage=Class&action=add_class" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Select course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= htmlspecialchars($course['course_id']) ?>">
                                        <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a course
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="term_id" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="term_id" name="term_id" required>
                                <option value="">Select term</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= htmlspecialchars($term['term_id']) ?>">
                                        <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a term
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="section" name="section" required>
                            <div class="invalid-feedback">
                                Please enter a section
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="instructor_id" class="form-label">Instructor</label>
                            <select class="form-select" id="instructor_id" name="instructor_id">
                                <option value="">Assign later</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?= htmlspecialchars($instructor['staff_id']) ?>">
                                        <?= htmlspecialchars($instructor['last_name']) ?>, <?= htmlspecialchars($instructor['first_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="room" class="form-label">Room <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="room" name="room" required>
                            <div class="invalid-feedback">
                                Please enter a room
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="days_of_week" class="form-label">Days <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="days_of_week" name="days_of_week" 
                                   placeholder="e.g. MWF, TTh" required>
                            <div class="invalid-feedback">
                                Please enter days of the week
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                            <div class="invalid-feedback">
                                Please enter start time
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                            <div class="invalid-feedback">
                                Please enter end time
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="max_students" class="form-label">Maximum Students <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="max_students" name="max_students" 
                                   min="1" max="200" value="40" required>
                            <div class="invalid-feedback">
                                Please enter maximum number of students
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select status
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addClassForm" class="btn btn-success">Create Class</button>
            </div>
        </div>
    </div>
</div>

<!-- View Class Modal -->
<div class="modal fade" id="viewClassModal" tabindex="-1" aria-labelledby="viewClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="viewClassModalLabel">Class Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Class Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Class ID:</td>
                                <td id="view_class_id"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Section:</td>
                                <td id="view_section"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td id="view_status"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Course Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Course:</td>
                                <td id="view_course"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Term:</td>
                                <td id="view_term"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Instructor:</td>
                                <td id="view_instructor"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Schedule</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Days:</td>
                                <td id="view_days"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Time:</td>
                                <td id="view_time"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Room:</td>
                                <td id="view_room"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Enrollment</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Current:</td>
                                <td id="view_current_enrollment"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Maximum:</td>
                                <td id="view_max_students"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Available:</td>
                                <td id="view_available_slots"></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary editFromViewBtn" data-bs-toggle="modal" 
                        data-bs-target="#editClassModal">Edit Class</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="editClassModalLabel">Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editClassForm" method="post" action="?page=Manage&subpage=Class&action=edit_class" class="needs-validation" novalidate>
                    <input type="hidden" id="edit_class_id" name="class_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_course_id" name="course_id" required>
                                <option value="">Select course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= htmlspecialchars($course['course_id']) ?>">
                                        <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a course
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_term_id" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_term_id" name="term_id" required>
                                <option value="">Select term</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= htmlspecialchars($term['term_id']) ?>">
                                        <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a term
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_section" class="form-label">Section <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_section" name="section" required>
                            <div class="invalid-feedback">
                                Please enter a section
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_instructor_id" class="form-label">Instructor</label>
                            <select class="form-select" id="edit_instructor_id" name="instructor_id">
                                <option value="">Assign later</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?= htmlspecialchars($instructor['staff_id']) ?>">
                                        <?= htmlspecialchars($instructor['last_name']) ?>, <?= htmlspecialchars($instructor['first_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_room" class="form-label">Room <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_room" name="room" required>
                            <div class="invalid-feedback">
                                Please enter a room
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_days_of_week" class="form-label">Days <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_days_of_week" name="days_of_week" 
                                   placeholder="e.g. MWF, TTh" required>
                            <div class="invalid-feedback">
                                Please enter days of the week
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            <div class="invalid-feedback">
                                Please enter start time
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            <div class="invalid-feedback">
                                Please enter end time
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_max_students" class="form-label">Maximum Students <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_max_students" name="max_students" 
                                   min="1" max="200" required>
                            <div class="invalid-feedback">
                                Please enter maximum number of students
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select status
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editClassForm" class="btn btn-primary">Update Class</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Class Confirmation Modal -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="deleteClassModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the class <span id="delete_class_info" class="fw-bold"></span>?</p>
                <p class="text-danger"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteClassBtn" class="btn btn-danger">Delete Class</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    
    // Handle persistent alerts with auto-hide after a longer period
    const persistentAlert = document.getElementById('persistentAlert');
    if (persistentAlert) {
        // Set a timer to auto-hide the alert after 10 seconds
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(persistentAlert);
            bsAlert.close();
        }, 10000); // 10 seconds
    }
    
    // Form validation for all forms
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // View class details functionality
    const viewButtons = document.querySelectorAll('.viewClassBtn');
    Array.from(viewButtons).forEach(button => {
        button.addEventListener('click', function() {
            const classId = this.getAttribute('data-class-id');
              // Fetch class data from server
            fetch(`?page=Manage&subpage=Class&action=get_class&id=${classId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    console.log('Response received:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    // Check if data contains an error message
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // Populate view modal with data
                    document.getElementById('view_class_id').textContent = data.class_id;
                    document.getElementById('view_section').textContent = data.section;
                    document.getElementById('view_status').innerHTML = `<span class="badge ${getStatusBadgeClass(data.status)}">${capitalize(data.status)}</span>`;
                    document.getElementById('view_course').textContent = `${data.course_code} - ${data.course_name}`;
                    document.getElementById('view_term').textContent = data.term_name;
                    document.getElementById('view_instructor').textContent = data.instructor_name || 'Not assigned';
                    document.getElementById('view_days').textContent = data.days_of_week;
                    document.getElementById('view_time').textContent = `${formatTime(data.start_time)} - ${formatTime(data.end_time)}`;
                    document.getElementById('view_room').textContent = data.room;
                    document.getElementById('view_current_enrollment').textContent = data.enrolled_count;
                    document.getElementById('view_max_students').textContent = data.max_students;
                    document.getElementById('view_available_slots').textContent = data.max_students - data.enrolled_count;
                    
                    // Set the class ID for the edit button
                    const editFromViewBtn = document.querySelector('.editFromViewBtn');
                    if (editFromViewBtn) {
                        editFromViewBtn.setAttribute('data-class-id', data.class_id);
                    }
                })
                .catch(error => {
                    console.error('Error details:', error);
                    alert('An error occurred while fetching class data: ' + error.message);
                });
        });
    });
    
    // Edit class functionality
    const editButtons = document.querySelectorAll('.editClassBtn');
    Array.from(editButtons).forEach(button => {
        button.addEventListener('click', function() {
            const classId = this.getAttribute('data-class-id');
            loadClassForEdit(classId);
        });
    });
    
    // Edit from view functionality
    const editFromViewBtn = document.querySelector('.editFromViewBtn');
    if (editFromViewBtn) {
        editFromViewBtn.addEventListener('click', function() {
            const classId = this.getAttribute('data-class-id');
            
            // Close the view modal
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewClassModal'));
            viewModal.hide();
            
            // Load class data for editing
            loadClassForEdit(classId);
        });
    }
      function loadClassForEdit(classId) {
        // Fetch class data from server
        fetch(`?page=Manage&subpage=Class&action=get_class&id=${classId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                console.log('Edit response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Edit data received:', data);
                // Check if data contains an error message
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Populate edit form with data
                document.getElementById('edit_class_id').value = data.class_id;
                document.getElementById('edit_course_id').value = data.course_id;
                document.getElementById('edit_term_id').value = data.term_id;
                document.getElementById('edit_section').value = data.section;
                document.getElementById('edit_instructor_id').value = data.instructor_id || '';
                document.getElementById('edit_room').value = data.room;
                document.getElementById('edit_days_of_week').value = data.days_of_week;
                document.getElementById('edit_start_time').value = formatTimeForInput(data.start_time);
                document.getElementById('edit_end_time').value = formatTimeForInput(data.end_time);
                document.getElementById('edit_max_students').value = data.max_students;
                document.getElementById('edit_status').value = data.status;
            })
            .catch(error => {
                console.error('Edit error details:', error);
                alert('An error occurred while fetching class data for editing: ' + error.message);
            });
    }
    
    // Delete class functionality
    const deleteButtons = document.querySelectorAll('.deleteClassBtn');
    Array.from(deleteButtons).forEach(button => {
        button.addEventListener('click', function() {
            const classId = this.getAttribute('data-class-id');
            const courseCode = this.getAttribute('data-course-code');
            const section = this.getAttribute('data-section');
            
            // Set delete modal info
            document.getElementById('delete_class_info').textContent = `${courseCode} (${section})`;
            
            // Set the href for the delete confirmation button
            document.getElementById('confirmDeleteClassBtn').href = `?page=Manage&subpage=Class&action=delete_class&id=${classId}`;
            
            // Show delete modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteClassModal'));
            deleteModal.show();
        });
    });
      // Log search form submission
    const searchForm = document.getElementById('classSearchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            // Don't actually prevent submission, but log what's being submitted
            console.log('Form submitted with search:', document.getElementById('searchClass').value);
            const formData = new FormData(this);
            const searchParams = {};
            for (const [key, value] of formData.entries()) {
                searchParams[key] = value;
            }
            console.log('All search parameters:', searchParams);
        });
    }
    
    // Filter reset functionality
    const resetBtn = document.getElementById('resetClassFilters');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            window.location.href = '?page=Manage&subpage=Class';
        });
    }
    
    // Refresh button functionality
    const refreshBtn = document.getElementById('refreshClassBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            window.location.href = '?page=Manage&subpage=Class';
        });
    }
    
    // Helper functions
    function formatTime(timeString) {
        if (!timeString) return '—';
        try {
            // Convert 24h format to 12h format
            const date = new Date(`2000-01-01T${timeString}`);
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        } catch (error) {
            return timeString;
        }
    }
    
    function formatTimeForInput(timeString) {
        if (!timeString) return '';
        try {
            // Ensure the time string is in HH:MM format for the time input element
            const date = new Date(`2000-01-01T${timeString}`);
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            return `${hours}:${minutes}`;
        } catch (error) {
            return timeString;
        }
    }
    
    function capitalize(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    
    function getStatusBadgeClass(status) {
        switch (status) {
            case 'open':
                return 'bg-success';
            case 'closed':
                return 'bg-warning';
            case 'cancelled':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }
});
</script>