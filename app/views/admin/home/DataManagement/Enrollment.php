<?php   
// Get all enrollments with a simple direct query
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.enrollment_id, e.student_id, e.term_id, e.enrollment_date, 
            e.status, e.approved_by, e.approved_date, e.notes,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            t.term_name, t.start_date, t.end_date,
            CONCAT(t.start_date, ' - ', t.end_date) AS term_period,
            CONCAT(st.first_name, ' ', st.last_name) AS staff_name
        FROM 
            enrollments e
        JOIN 
            students s ON e.student_id = s.student_id
        JOIN 
            terms t ON e.term_id = t.term_id
        LEFT JOIN 
            staff st ON e.approved_by = st.staff_id
        ORDER BY 
            e.enrollment_date DESC, e.enrollment_id DESC
    ");
    $stmt->execute();
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching enrollments: " . $e->getMessage());
    $enrollments = [];
}

// Fetch all terms for dropdowns
try {
    $stmt = $pdo->prepare("SELECT term_id, term_name, start_date, end_date FROM terms ORDER BY start_date DESC");
    $stmt->execute();
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching terms: " . $e->getMessage());
    $terms = [];
}

// Fetch all students for dropdowns
try {
    $stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM students ORDER BY last_name, first_name");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
    $students = [];
}

// Fetch staff for approval dropdowns
try {
    $stmt = $pdo->prepare("SELECT staff_id, first_name, last_name FROM staff ORDER BY last_name, first_name");
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching staff: " . $e->getMessage());
    $staff = [];
}

// Fetch all classes for enrollment - we'll filter by term via JavaScript
try {
    $stmt = $pdo->prepare("
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
        WHERE c.status = 'open'
        ORDER BY t.term_name, co.course_code, c.section
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the number of classes found
    error_log("DEBUG: Found " . count($classes) . " classes for enrollment");
    if (count($classes) > 0) {
        error_log("DEBUG: First class: " . json_encode($classes[0]));
    }
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
}

// Get the current staff ID for the logged-in user (if applicable)
$currentStaffId = getStaffIdByUserId($_SESSION['user_id'] ?? null);
?>

<!-- Enrollment Page Content -->
<div>
    <!-- Header -->
    <div class="navbg p-3 rounded rounded-3 shadow-sm">
        <div class="d-flex justify-content-between flex-wrap align-items-center">
            <h5 class="mb-0">Enrollment Management</h5>
            <div>
                <button type="button" class="btn btn-outline-secondary me-2" id="refreshEnrollmentBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
                    <i class="bi bi-plus-lg"></i> Add Enrollment
                </button>
            </div>
        </div>
    </div>    <!-- Alert Container for system messages -->
    <div id="alertContainer" class="mt-3">
        <?php if (isset($_SESSION['enrollment_response']['message'])): ?>
            <div class="alert <?= $_SESSION['enrollment_response']['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['enrollment_response']['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['enrollment_response']); ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>    <!-- Search and Filter Controls -->
    <div class="card shadow-sm mb-3 mt-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">                    <label for="filterTerm" class="form-label">Filter by Term</label>
                    <select class="form-select form-select-sm" id="filterTerm">
                        <option value="">All Terms</option>
                        <?php foreach ($terms as $term): ?>
                        <option value="<?= $term['term_id'] ?>" <?= (isset($_GET['filter_term']) && $_GET['filter_term'] == $term['term_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($term['term_name']) ?>
                        </option>
                        <?php endforeach; ?>                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Filter by Status</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                        <option value="cancelled" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="searchEnrollment" class="form-label">Search</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="searchEnrollment" 
                               placeholder="Search by ID, Student ID, Name, or Staff..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button class="btn btn-outline-success" type="button" id="searchEnrollmentBtn">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Student</th>
                            <th>Term</th>
                            <th class="text-center">Enrollment Date</th>
                            <th class="text-center">Status</th>
                            <th>Approved By</th>
                            <th class="text-center">Approved Date</th>
                            <th>Notes</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($enrollments)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-search fs-1 text-muted mb-2"></i>
                                        <p class="mb-0">No Enrollment found.</p>
                                        <p class="text-muted small">Try adjusting your search criteria or add a new Enrollment.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $row): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($row['enrollment_id']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['student_id']) ?>
                                        <?php if(isset($row['student_name'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($row['student_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['term_id']) ?>
                                        <?php if(isset($row['term_name'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($row['term_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= date('M d, Y', strtotime($row['enrollment_date'])) ?></td>
                                    <td class="text-center">
                                        <span class="badge 
                                            <?= match($row['status']) {
                                                'approved' => 'bg-success',
                                                'pending' => 'bg-warning text-dark',
                                                'rejected' => 'bg-danger',
                                                'cancelled' => 'bg-secondary',
                                                default => 'bg-light text-dark'
                                            } ?>
                                        ">
                                            <?= ucfirst(htmlspecialchars($row['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['approved_by']): ?>
                                            <?= htmlspecialchars($row['approved_by']) ?>
                                            <?php if(isset($row['staff_name'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($row['staff_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $row['approved_date'] ? date('M d, Y', strtotime($row['approved_date'])) : '—' ?></td>
                                    <td>
                                        <?php if ($row['notes']): ?>
                                            <span class="text-truncate d-inline-block" style="max-width: 150px;" data-bs-toggle="tooltip" title="<?= htmlspecialchars($row['notes']) ?>">
                                                <?= htmlspecialchars($row['notes']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary viewEnrollmentBtn" 
                                                    data-id="<?= htmlspecialchars($row['enrollment_id']) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewEnrollmentModal">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-outline-secondary editEnrollmentBtn" 
                                                    data-id="<?= htmlspecialchars($row['enrollment_id']) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editEnrollmentModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                              <?php if ($row['status'] == 'pending'): ?>
                                                <button type="button" class="btn btn-success approveEnrollmentBtn" 
                                                        data-id="<?= htmlspecialchars($row['enrollment_id']) ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#approveEnrollmentModal">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-danger rejectEnrollmentBtn" 
                                                        data-id="<?= htmlspecialchars($row['enrollment_id']) ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rejectEnrollmentModal">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-outline-danger deleteEnrollmentBtn" 
                                                    data-id="<?= htmlspecialchars($row['enrollment_id']) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteEnrollmentModal">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Enrollment Modal -->
<div class="modal fade" id="addEnrollmentModal" tabindex="-1" aria-labelledby="addEnrollmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="addEnrollmentModalLabel">Add New Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addEnrollmentForm" action="../../../app/main_proc.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_enrollment">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student <span class="text-danger">*</span></label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="" selected disabled>-- Select Student --</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?= htmlspecialchars($student['student_id']) ?>">
                                    <?= htmlspecialchars($student['student_id']) ?> - <?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a student.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="term_id" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="term_id" name="term_id" required>
                                <option value="" selected disabled>-- Select Term --</option>
                                <?php foreach ($terms as $term): ?>
                                <option value="<?= htmlspecialchars($term['term_id']) ?>">
                                    <?= htmlspecialchars($term['term_name']) ?> (<?= date('M Y', strtotime($term['start_date'])) ?> - <?= date('M Y', strtotime($term['end_date'])) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a term.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="enrollment_date" class="form-label">Enrollment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="enrollment_date" name="enrollment_date" value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">Please provide an enrollment date.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" selected>Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                      <div class="row mb-3 approved-fields" style="display: none;">
                        <div class="col-md-6">
                            <label for="approved_by" class="form-label">Approved By <span class="text-danger">*</span></label>
                            <select class="form-select" id="approved_by" name="approved_by">
                                <option value="" selected disabled>-- Select Staff --</option>
                                <?php if ($currentStaffId): ?>
                                <option value="<?= htmlspecialchars($currentStaffId) ?>">Current User (<?= htmlspecialchars($currentStaffId) ?>)</option>
                                <?php endif; ?>
                                <?php foreach ($staff as $member): ?>
                                <option value="<?= htmlspecialchars($member['staff_id']) ?>">
                                    <?= htmlspecialchars($member['staff_id']) ?> - <?= htmlspecialchars($member['first_name']) ?> <?= htmlspecialchars($member['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select who approved this enrollment.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="approved_date" class="form-label">Approval Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="approved_date" name="approved_date" value="<?= date('Y-m-d') ?>">
                            <div class="invalid-feedback">Please provide an approval date.</div>
                        </div>
                    </div>
                      <!-- Classes Selection Section (only shown for approved enrollments) -->
                    <div class="mb-4" id="classesSection" style="display: none;">
                        <label class="form-label">Select Classes <span class="text-danger">*</span></label>
                        <div class="border rounded p-3" style="background-color: #f8f9fa;">
                            <div id="classesContainer" class="mb-2">
                                <small class="text-muted">Please select a term first to see available classes.</small>
                            </div>
                            <div id="selectedClassesDisplay" class="mt-2" style="display: none;">
                                <strong>Selected Classes:</strong>
                                <div id="selectedClassesList" class="mt-2"></div>
                            </div>
                            <div class="invalid-feedback d-block" id="classesError" style="display: none;">
                                Please select at least one class for this enrollment.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000"></textarea>
                        <div class="form-text">Optional: Add any relevant information about this enrollment.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addEnrollmentForm" class="btn btn-success">Save Enrollment</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Enrollment Modal -->
<div class="modal fade" id="editEnrollmentModal" tabindex="-1" aria-labelledby="editEnrollmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="editEnrollmentModalLabel">Edit Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEnrollmentForm" action="../../../app/main_proc.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update_enrollment">
                    <input type="hidden" name="enrollment_id" id="edit_enrollment_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_student_id" class="form-label">Student <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_student_id" name="student_id" required>
                                <option value="" disabled>-- Select Student --</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?= htmlspecialchars($student['student_id']) ?>">
                                    <?= htmlspecialchars($student['student_id']) ?> - <?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a student.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_term_id" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_term_id" name="term_id" required>
                                <option value="" disabled>-- Select Term --</option>
                                <?php foreach ($terms as $term): ?>
                                <option value="<?= htmlspecialchars($term['term_id']) ?>">
                                    <?= htmlspecialchars($term['term_name']) ?> (<?= date('M Y', strtotime($term['start_date'])) ?> - <?= date('M Y', strtotime($term['end_date'])) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a term.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_enrollment_date" class="form-label">Enrollment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_enrollment_date" name="enrollment_date" required>
                            <div class="invalid-feedback">Please provide an enrollment date.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3 edit-approved-fields">
                        <div class="col-md-6">
                            <label for="edit_approved_by" class="form-label">Approved By <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_approved_by" name="approved_by">
                                <option value="" selected disabled>-- Select Staff --</option>
                                <?php if ($currentStaffId): ?>
                                <option value="<?= htmlspecialchars($currentStaffId) ?>">Current User (<?= htmlspecialchars($currentStaffId) ?>)</option>
                                <?php endif; ?>
                                <?php foreach ($staff as $member): ?>
                                <option value="<?= htmlspecialchars($member['staff_id']) ?>">
                                    <?= htmlspecialchars($member['staff_id']) ?> - <?= htmlspecialchars($member['first_name']) ?> <?= htmlspecialchars($member['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select who approved this enrollment.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_approved_date" class="form-label">Approval Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_approved_date" name="approved_date">
                            <div class="invalid-feedback">Please provide an approval date.</div>                        </div>
                    </div>
                    
                    <!-- Classes Selection Section for Edit (only shown for approved enrollments) -->
                    <div class="mb-4" id="editClassesSection" style="display: none;">
                        <label class="form-label">Select Classes <span class="text-danger">*</span></label>
                        <div class="border rounded p-3" style="background-color: #f8f9fa;">
                            <div id="editClassesContainer" class="mb-2">
                                <small class="text-muted">Please select a term first to see available classes.</small>
                            </div>
                            <div id="editSelectedClassesDisplay" class="mt-2" style="display: none;">
                                <strong>Selected Classes:</strong>
                                <div id="editSelectedClassesList" class="mt-2"></div>
                            </div>
                            <div class="invalid-feedback d-block" id="editClassesError" style="display: none;">
                                Please select at least one class for this enrollment.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3" maxlength="1000"></textarea>
                        <div class="form-text">Optional: Add any relevant information about this enrollment.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editEnrollmentForm" class="btn btn-primary">Update Enrollment</button>
            </div>
        </div>
    </div>
</div>

<!-- View Enrollment Modal -->
<div class="modal fade" id="viewEnrollmentModal" tabindex="-1" aria-labelledby="viewEnrollmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="viewEnrollmentModalLabel">Enrollment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Enrollment Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Enrollment ID:</td>
                                <td id="view_enrollment_id"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Enrollment Date:</td>
                                <td id="view_enrollment_date"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td id="view_status"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Approval Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Approved By:</td>
                                <td id="view_approved_by"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Approval Date:</td>
                                <td id="view_approved_date"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Student Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Student ID:</td>
                                <td id="view_student_id"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Name:</td>
                                <td id="view_student_name"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Term Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Term ID:</td>
                                <td id="view_term_id"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Term Name:</td>
                                <td id="view_term_name"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Term Period:</td>
                                <td id="view_term_period"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                  <!-- Enrolled Classes Section -->
                <div class="mb-3" id="viewEnrolledClassesSection" style="display: none;">
                    <h6 class="fw-bold">Enrolled Classes</h6>
                    <div id="viewEnrolledClassesList" class="p-2 bg-light rounded">
                        <small class="text-muted">No classes enrolled</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6 class="fw-bold">Notes</h6>
                    <div id="view_notes" class="p-2 bg-light rounded"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary editFromViewBtn" data-bs-toggle="modal" data-bs-target="#editEnrollmentModal">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Enrollment Modal -->
<div class="modal fade" id="approveEnrollmentModal" tabindex="-1" aria-labelledby="approveEnrollmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="approveEnrollmentModalLabel">Approve Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="approveEnrollmentForm" action="../../../app/main_proc.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="approve_enrollment">
                    <input type="hidden" name="enrollment_id" id="approve_enrollment_id">
                    <input type="hidden" name="approve_term_id" id="approve_term_id">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> You are about to approve enrollment with ID: <strong id="approve_enrollment_display"></strong>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="mb-3">
                                <label for="approve_approved_by" class="form-label">Approved By <span class="text-danger">*</span></label>
                                <select class="form-select" id="approve_approved_by" name="approved_by" required>
                                    <option value="" selected disabled>-- Select Staff --</option>
                                    <?php if ($currentStaffId): ?>
                                    <option value="<?= htmlspecialchars($currentStaffId) ?>">Current User (<?= htmlspecialchars($currentStaffId) ?>)</option>
                                    <?php endif; ?>
                                    <?php foreach ($staff as $member): ?>
                                    <option value="<?= htmlspecialchars($member['staff_id']) ?>">
                                        <?= htmlspecialchars($member['staff_id']) ?> - <?= htmlspecialchars($member['first_name']) ?> <?= htmlspecialchars($member['last_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select who is approving this enrollment.</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="mb-3">
                                <label for="approve_approved_date" class="form-label">Approval Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="approve_approved_date" name="approved_date" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Please provide an approval date.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Class Selection Section -->
                    <div class="mb-3" id="approveClassesSection">
                        <label class="form-label">Select Classes <span class="text-danger">*</span></label>
                        <small class="text-muted d-block mb-2">Choose the classes for this approved enrollment</small>
                        
                        <div id="approveClassesContainer" class="border rounded p-3 mb-2">
                            <small class="text-muted">Loading classes...</small>
                        </div>
                        
                        <!-- Selected Classes Display -->
                        <div id="approveSelectedClassesDisplay" style="display: none;">
                            <label class="form-label">Selected Classes:</label>
                            <div id="approveSelectedClassesList"></div>
                        </div>
                        
                        <!-- Error message for classes -->
                        <div id="approveClassesError" class="text-danger mt-2" style="display: none;">
                            <small>Please select at least one class for the approved enrollment.</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="approve_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="approve_notes" name="notes" rows="2" maxlength="1000"></textarea>
                        <div class="form-text">Optional: Add any notes about this approval.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="approveEnrollmentForm" class="btn btn-success">Confirm Approval</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Enrollment Modal -->
<div class="modal fade" id="rejectEnrollmentModal" tabindex="-1" aria-labelledby="rejectEnrollmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="rejectEnrollmentModalLabel">Reject Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rejectEnrollmentForm" action="../../../app/main_proc.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="reject_enrollment">
                    <input type="hidden" name="enrollment_id" id="reject_enrollment_id">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> You are about to reject enrollment with ID: <strong id="reject_enrollment_display"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reject_approved_date" class="form-label">Rejection Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="reject_approved_date" name="approved_date" value="<?= date('Y-m-d') ?>" required>
                        <div class="invalid-feedback">Please provide a rejection date.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reject_reason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_reason" name="notes" rows="3" maxlength="1000" required></textarea>
                        <div class="invalid-feedback">Please provide a reason for rejecting this enrollment.</div>
                    </div>                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="rejectEnrollmentForm" class="btn btn-danger">Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Enrollment Modal -->
<div class="modal fade" id="deleteEnrollmentModal" tabindex="-1" aria-labelledby="deleteEnrollmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="deleteEnrollmentModalLabel">Delete Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="deleteEnrollmentForm" action="../../../app/main_proc.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="delete_enrollment">
                    <input type="hidden" name="enrollment_id" id="delete_enrollment_id">
                    
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Warning:</strong> You are about to permanently delete enrollment with ID: <strong id="delete_enrollment_display"></strong>
                        <br><br>
                        This action will also delete all associated enrollment details and grades. This cannot be undone.
                    </div>
                    
                    <div class="mb-3">
                        <label for="delete_reason" class="form-label">Reason for Deletion <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delete_reason" name="deletion_reason" rows="3" maxlength="1000" required></textarea>
                        <div class="invalid-feedback">Please provide a reason for deleting this enrollment.</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDeletion" required>
                        <label class="form-check-label" for="confirmDeletion">
                            I understand that this action is permanent and cannot be undone
                        </label>
                        <div class="invalid-feedback">You must confirm that you understand this action is permanent.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteEnrollmentForm" class="btn btn-danger">Delete Enrollment</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));    // Classes data from PHP
    const classesData = <?= json_encode($classes) ?>;
    let selectedClasses = [];
    let editSelectedClasses = [];
    let approveSelectedClasses = [];
    
    // Handle status selection change to show/hide classes section and approved fields
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            const classesSection = document.getElementById('classesSection');
            const approvedFields = document.querySelector('.approved-fields');
            const selectedStatus = this.value;
            
            if (selectedStatus === 'approved') {
                classesSection.style.display = 'block';
                approvedFields.style.display = 'flex';
                // Set required attributes for approved fields
                document.getElementById('approved_by').setAttribute('required', '');
                document.getElementById('approved_date').setAttribute('required', '');
                
                // If term is already selected, update classes
                const termSelect = document.getElementById('term_id');
                if (termSelect && termSelect.value) {
                    updateClassesForTerm(termSelect.value);
                }
            } else {
                classesSection.style.display = 'none';
                approvedFields.style.display = 'none';
                // Remove required attributes from approved fields
                document.getElementById('approved_by').removeAttribute('required');
                document.getElementById('approved_date').removeAttribute('required');
                // Clear selected classes
                selectedClasses = [];
                updateSelectedClassesDisplay();
            }
        });
    }

    // Handle term selection change to update classes
    const termSelect = document.getElementById('term_id');
    if (termSelect) {
        termSelect.addEventListener('change', function() {
            const selectedTermId = this.value;
            ('Term selected:', selectedTermId);
            updateClassesForTerm(selectedTermId);
        });
    }
      // Handle edit status selection change to show/hide classes section
    const editStatusSelect = document.getElementById('edit_status');
    if (editStatusSelect) {
        editStatusSelect.addEventListener('change', function() {
            const editClassesSection = document.getElementById('editClassesSection');
            const selectedStatus = this.value;
            
            if (selectedStatus === 'approved') {
                editClassesSection.style.display = 'block';
                // If term is already selected, update classes
                const editTermSelect = document.getElementById('edit_term_id');
                if (editTermSelect && editTermSelect.value) {
                    updateEditClassesForTerm(editTermSelect.value);
                }
            } else {
                editClassesSection.style.display = 'none';
                // Clear selected classes
                editSelectedClasses = [];
                updateEditSelectedClassesDisplay();
            }
        });
    }

    // Handle edit term selection change to update classes
    const editTermSelect = document.getElementById('edit_term_id');
    if (editTermSelect) {
        editTermSelect.addEventListener('change', function() {
            const selectedTermId = this.value;
            ('Edit Term selected:', selectedTermId);
            updateEditClassesForTerm(selectedTermId);
        });
    }
      // Function to update classes based on selected term
    function updateClassesForTerm(termId) {
        const classesContainer = document.getElementById('classesContainer');
        const classesSection = document.getElementById('classesSection');
        
        if (!termId) {
            classesContainer.innerHTML = '<small class="text-muted">Please select a term first to see available classes.</small>';
            selectedClasses = [];
            updateSelectedClassesDisplay();
            return;
        }
        
        // Show classes section if status is approved
        const statusSelect = document.getElementById('status');
        if (statusSelect && statusSelect.value === 'approved') {
            classesSection.style.display = 'block';
        }
        
        // Filter classes for the selected term
        const termClasses = classesData.filter(cls => cls.term_id === termId);
        
        if (termClasses.length === 0) {
            classesContainer.innerHTML = '<small class="text-muted">No classes available for the selected term.</small>';
            selectedClasses = [];
            updateSelectedClassesDisplay();
            return;
        }
        
        // Build classes checkboxes HTML
        let classesHtml = '<div class="row">';
        termClasses.forEach((cls, index) => {
            const timeInfo = cls.start_time && cls.end_time ? 
                `${cls.start_time} - ${cls.end_time}` : 'Time TBD';
            const daysInfo = cls.days_of_week || 'Days TBD';
            const instructorInfo = cls.instructor_name || 'TBD';
            
            classesHtml += `
                <div class="col-md-6 mb-2">
                    <div class="form-check">
                        <input class="form-check-input class-checkbox" type="checkbox" 
                               value="${cls.class_id}" id="class_${cls.class_id}">
                        <label class="form-check-label" for="class_${cls.class_id}">
                            <strong>${cls.course_code} - ${cls.section}</strong><br>
                            <small class="text-muted">
                                ${cls.course_name}<br>
                                ${daysInfo}, ${timeInfo}<br>
                                Room: ${cls.room || 'TBD'} | Instructor: ${instructorInfo}
                            </small>
                        </label>
                    </div>
                </div>
            `;
        });
        classesHtml += '</div>';
        
        classesContainer.innerHTML = classesHtml;
        
        // Add event listeners to checkboxes
        const classCheckboxes = document.querySelectorAll('.class-checkbox');
        classCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedClasses.push(this.value);
                } else {
                    selectedClasses = selectedClasses.filter(id => id !== this.value);
                }
                updateSelectedClassesDisplay();
            });
        });
    }
    
    // Function to update selected classes display
    function updateSelectedClassesDisplay() {
        const displayDiv = document.getElementById('selectedClassesDisplay');
        const listDiv = document.getElementById('selectedClassesList');
        const errorDiv = document.getElementById('classesError');
        
        if (selectedClasses.length === 0) {
            displayDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            return;
        }
        
        displayDiv.style.display = 'block';
        errorDiv.style.display = 'none';
        
        let listHtml = '';
        selectedClasses.forEach(classId => {
            const classData = classesData.find(cls => cls.class_id === classId);
            if (classData) {
                listHtml += `
                    <span class="badge bg-primary me-2 mb-1">
                        ${classData.course_code} - ${classData.section}
                        <input type="hidden" name="selected_classes[]" value="${classId}">
                    </span>
                `;
            }
        });
          listDiv.innerHTML = listHtml;
    }
      // Function to update classes based on selected term for EDIT modal
    function updateEditClassesForTerm(termId) {
        const classesContainer = document.getElementById('editClassesContainer');
        const editClassesSection = document.getElementById('editClassesSection');
        
        ('updateEditClassesForTerm called with termId:', termId);
        ('classesData available:', classesData ? classesData.length : 'No data');
        
        if (!termId) {
            classesContainer.innerHTML = '<small class="text-muted">Please select a term first to see available classes.</small>';
            editSelectedClasses = [];
            updateEditSelectedClassesDisplay();
            return;
        }
        
        // Show classes section if status is approved
        const editStatusSelect = document.getElementById('edit_status');
        if (editStatusSelect && editStatusSelect.value === 'approved') {
            editClassesSection.style.display = 'block';
        }
        
        // Filter classes for the selected term
        const termClasses = classesData.filter(cls => cls.term_id === termId);
        
        ('Classes found for term', termId, ':', termClasses.length);
        
        if (termClasses.length === 0) {
            classesContainer.innerHTML = '<small class="text-muted">No classes available for the selected term.</small>';
            editSelectedClasses = [];
            updateEditSelectedClassesDisplay();
            return;
        }
        
        // Build classes checkboxes HTML
        let classesHtml = '<div class="row">';
        termClasses.forEach((cls, index) => {
            const timeInfo = cls.start_time && cls.end_time ? 
                `${cls.start_time} - ${cls.end_time}` : 'Time TBD';
            const daysInfo = cls.days_of_week || 'Days TBD';
            const instructorInfo = cls.instructor_name || 'TBD';
            const isChecked = editSelectedClasses.includes(cls.class_id) ? 'checked' : '';
            
            classesHtml += `
                <div class="col-md-6 mb-2">
                    <div class="form-check">
                        <input class="form-check-input edit-class-checkbox" type="checkbox" 
                               value="${cls.class_id}" id="edit_class_${cls.class_id}" ${isChecked}>
                        <label class="form-check-label" for="edit_class_${cls.class_id}">
                            <strong>${cls.course_code} - ${cls.section}</strong><br>
                            <small class="text-muted">
                                ${cls.course_name}<br>
                                ${daysInfo}, ${timeInfo}<br>
                                Room: ${cls.room || 'TBD'} | Instructor: ${instructorInfo}
                            </small>
                        </label>
                    </div>
                </div>
            `;
        });
        classesHtml += '</div>';
        
        classesContainer.innerHTML = classesHtml;
        
        // Add event listeners to checkboxes
        const editClassCheckboxes = document.querySelectorAll('.edit-class-checkbox');
        editClassCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    editSelectedClasses.push(this.value);
                } else {
                    editSelectedClasses = editSelectedClasses.filter(id => id !== this.value);
                }
                updateEditSelectedClassesDisplay();
            });
        });
    }
    
    // Function to update selected classes display for EDIT modal
    function updateEditSelectedClassesDisplay() {
        const displayDiv = document.getElementById('editSelectedClassesDisplay');
        const listDiv = document.getElementById('editSelectedClassesList');
        const errorDiv = document.getElementById('editClassesError');
        
        if (editSelectedClasses.length === 0) {
            displayDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            return;
        }
        
        displayDiv.style.display = 'block';
        errorDiv.style.display = 'none';
        
        let listHtml = '';
        editSelectedClasses.forEach(classId => {
            const classData = classesData.find(cls => cls.class_id === classId);
            if (classData) {
                listHtml += `
                    <span class="badge bg-primary me-2 mb-1">
                        ${classData.course_code} - ${classData.section}
                        <input type="hidden" name="selected_classes[]" value="${classId}">
                    </span>
                `;
            }
        });
        
        listDiv.innerHTML = listHtml;
    }

    // Function to update classes based on selected term for APPROVE modal
    function updateApproveClassesForTerm(termId) {
        const classesContainer = document.getElementById('approveClassesContainer');
        
        ('updateApproveClassesForTerm called with termId:', termId);
        
        if (!termId) {
            classesContainer.innerHTML = '<small class="text-muted">Please select a term first to see available classes.</small>';
            approveSelectedClasses = [];
            updateApproveSelectedClassesDisplay();
            return;
        }
        
        // Filter classes for the selected term
        const termClasses = classesData.filter(cls => cls.term_id === termId);
        
        ('Classes found for term', termId, ':', termClasses.length);
        
        if (termClasses.length === 0) {
            classesContainer.innerHTML = '<small class="text-muted">No classes available for the selected term.</small>';
            approveSelectedClasses = [];
            updateApproveSelectedClassesDisplay();
            return;
        }
        
        // Build classes checkboxes HTML
        let classesHtml = '<div class="row">';
        termClasses.forEach((cls, index) => {
            const timeInfo = cls.start_time && cls.end_time ? 
                `${cls.start_time} - ${cls.end_time}` : 'Time TBD';
            const daysInfo = cls.days_of_week || 'Days TBD';
            const instructorInfo = cls.instructor_name || 'TBD';
            const isChecked = approveSelectedClasses.includes(cls.class_id) ? 'checked' : '';
            
            classesHtml += `
                <div class="col-md-6 mb-2">
                    <div class="form-check">
                        <input class="form-check-input approve-class-checkbox" type="checkbox" 
                               value="${cls.class_id}" id="approve_class_${cls.class_id}" ${isChecked}>
                        <label class="form-check-label" for="approve_class_${cls.class_id}">
                            <strong>${cls.course_code} - ${cls.section}</strong><br>
                            <small class="text-muted">
                                ${cls.course_name}<br>
                                ${daysInfo}, ${timeInfo}<br>
                                Room: ${cls.room || 'TBD'} | Instructor: ${instructorInfo}
                            </small>
                        </label>
                    </div>
                </div>
            `;
        });
        classesHtml += '</div>';
        
        classesContainer.innerHTML = classesHtml;
        
        // Add event listeners to checkboxes
        const approveClassCheckboxes = document.querySelectorAll('.approve-class-checkbox');
        approveClassCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    approveSelectedClasses.push(this.value);
                } else {
                    approveSelectedClasses = approveSelectedClasses.filter(id => id !== this.value);
                }
                updateApproveSelectedClassesDisplay();
            });
        });
    }
    
    // Function to update selected classes display for APPROVE modal
    function updateApproveSelectedClassesDisplay() {
        const displayDiv = document.getElementById('approveSelectedClassesDisplay');
        const listDiv = document.getElementById('approveSelectedClassesList');
        const errorDiv = document.getElementById('approveClassesError');
        
        if (approveSelectedClasses.length === 0) {
            displayDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            return;
        }
        
        displayDiv.style.display = 'block';
        errorDiv.style.display = 'none';
        
        let listHtml = '';
        approveSelectedClasses.forEach(classId => {
            const classData = classesData.find(cls => cls.class_id === classId);
            if (classData) {
                listHtml += `
                    <span class="badge bg-primary me-2 mb-1">
                        ${classData.course_code} - ${classData.section}
                        <input type="hidden" name="selected_classes[]" value="${classId}">
                    </span>
                `;
            }
        });        
        listDiv.innerHTML = listHtml;
    }    // Handle edit button click using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.editEnrollmentBtn')) {
            const button = e.target.closest('.editEnrollmentBtn');
            const enrollmentId = button.getAttribute('data-id');
            fetchEnrollmentData(enrollmentId, 'edit');
        }
    });
    
    // Handle view button click using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.viewEnrollmentBtn')) {
            const button = e.target.closest('.viewEnrollmentBtn');
            const enrollmentId = button.getAttribute('data-id');
            fetchEnrollmentData(enrollmentId, 'view');
        }
    });    
    // Handle approve button click using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.approveEnrollmentBtn')) {
            const button = e.target.closest('.approveEnrollmentBtn');
            const enrollmentId = button.getAttribute('data-id');
            
            // Fetch enrollment data to get the term
            fetch(`../../../app/main_proc.php?action=get_enrollment&id=${enrollmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const enrollment = data.enrollment;
                        
                        // Set basic approval fields
                        document.getElementById('approve_enrollment_id').value = enrollmentId;
                        document.getElementById('approve_enrollment_display').textContent = enrollmentId;
                        document.getElementById('approve_term_id').value = enrollment.term_id;
                        
                        // Auto-select current user if available
                        if (document.querySelector('#approve_approved_by option[value="<?= $currentStaffId ?>"]')) {
                            document.querySelector('#approve_approved_by').value = "<?= $currentStaffId ?>";
                        }
                          // Reset and load classes for the term
                        approveSelectedClasses = [];
                        
                        // Pre-populate with already enrolled classes if they exist
                        if (enrollment.enrollment_details && enrollment.enrollment_details.length > 0) {
                            approveSelectedClasses = enrollment.enrollment_details.map(detail => detail.class_id);
                        }
                        
                        updateApproveClassesForTerm(enrollment.term_id);
                    } else {
                        console.error('Failed to fetch enrollment data:', data.message);
                        showAlert('danger', 'Failed to load enrollment data');
                    }                })
                .catch(error => {
                    console.error('Error fetching enrollment data:', error);
                    showAlert('danger', 'An error occurred while loading enrollment data');
                });
        }
    });
    
    // Handle reject button click using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.rejectEnrollmentBtn')) {
            const button = e.target.closest('.rejectEnrollmentBtn');
            const enrollmentId = button.getAttribute('data-id');
            document.getElementById('reject_enrollment_id').value = enrollmentId;
            document.getElementById('reject_enrollment_display').textContent = enrollmentId;
            
            // Auto-select current user if available
            if (document.querySelector('#reject_approved_by option[value="<?= $currentStaffId ?>"]')) {
                document.querySelector('#reject_approved_by').value = "<?= $currentStaffId ?>";
            }
        }
    });
    
    // Handle delete button click using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.deleteEnrollmentBtn')) {
            const button = e.target.closest('.deleteEnrollmentBtn');
            const enrollmentId = button.getAttribute('data-id');
            document.getElementById('delete_enrollment_id').value = enrollmentId;
            document.getElementById('delete_enrollment_display').textContent = enrollmentId;
        }
    });
    
    // Handle edit from view button
    const editFromViewBtn = document.querySelector('.editFromViewBtn');
    if (editFromViewBtn) {
        editFromViewBtn.addEventListener('click', function() {
            const enrollmentId = document.getElementById('view_enrollment_id').textContent;
            document.getElementById('viewEnrollmentModal').classList.remove('show');
            document.querySelector('.modal-backdrop').remove();
            fetchEnrollmentData(enrollmentId, 'edit');
        });
    }
      // Form validation for approve enrollment form
    const approveEnrollmentForm = document.getElementById('approveEnrollmentForm');
    if (approveEnrollmentForm) {
        approveEnrollmentForm.addEventListener('submit', function(event) {
            const errorDiv = document.getElementById('approveClassesError');
            
            // Check for classes selection
            if (approveSelectedClasses.length === 0) {
                event.preventDefault();
                event.stopPropagation();
                errorDiv.style.display = 'block';
                return false;
            } else {
                errorDiv.style.display = 'none';
            }
        });
    }

    // Form validation for add enrollment form
    const addEnrollmentForm = document.getElementById('addEnrollmentForm');
    if (addEnrollmentForm) {
        addEnrollmentForm.addEventListener('submit', function(event) {
            const statusSelect = document.getElementById('status');
            const approvedBy = document.getElementById('approved_by');
            const approvedDate = document.getElementById('approved_date');
            const classesError = document.getElementById('classesError');
            
            let hasErrors = false;
            
            // Check if status is approved
            if (statusSelect.value === 'approved') {
                // Check approved by field
                if (!approvedBy.value) {
                    event.preventDefault();
                    event.stopPropagation();
                    approvedBy.classList.add('is-invalid');
                    hasErrors = true;
                } else {
                    approvedBy.classList.remove('is-invalid');
                }
                
                // Check approved date field
                if (!approvedDate.value) {
                    event.preventDefault();
                    event.stopPropagation();
                    approvedDate.classList.add('is-invalid');
                    hasErrors = true;
                } else {
                    approvedDate.classList.remove('is-invalid');
                }
                
                // Check for classes selection
                if (selectedClasses.length === 0) {
                    event.preventDefault();
                    event.stopPropagation();
                    classesError.style.display = 'block';
                    hasErrors = true;
                } else {
                    classesError.style.display = 'none';
                }
            }
            
            if (hasErrors) {
                return false;
            }
        });
    }    // Filter and Search functionality
    const filterTerm = document.getElementById('filterTerm');
    const filterStatus = document.getElementById('filterStatus');
    const searchInput = document.getElementById('searchEnrollment');
    const searchBtn = document.getElementById('searchEnrollmentBtn');
    const refreshBtn = document.getElementById('refreshEnrollmentBtn');    
    // Function to display active filters
    function updateActiveFilters() {
        const filters = [];
        
        if (filterTerm.value) {
            const termText = filterTerm.options[filterTerm.selectedIndex].text;
            filters.push({ type: 'Term', value: termText, clear: () => filterTerm.value = '' });
        }
        
        if (filterStatus.value) {
            filters.push({ type: 'Status', value: filterStatus.value, clear: () => filterStatus.value = '' });
        }
        
        if (searchInput.value.trim()) {
            filters.push({ type: 'Search', value: searchInput.value.trim(), clear: () => searchInput.value = '' });
        }
        
        // Note: Active filters display removed as per requirements
    }    // Function to apply filters and search
    function applyFiltersAndSearch() {
        const termFilter = filterTerm.value;
        const statusFilter = filterStatus.value.toLowerCase();
        const searchText = searchInput.value.toLowerCase().trim();
        
        const tableRows = document.querySelectorAll('tbody tr:not(.no-results-row)');
        let visibleCount = 0;
        
        tableRows.forEach(row => {
            // Skip the "no data" row
            if (row.querySelector('td[colspan]')) {
                
                return;
            }
            
            let showRow = true;
            
            // Get row data more accurately
            const cells = row.querySelectorAll('td');
            const enrollmentId = cells[0]?.textContent?.toLowerCase().trim() || '';
            const studentCell = cells[1]?.textContent?.toLowerCase().trim() || '';
            const termCell = cells[2]?.textContent?.toLowerCase().trim() || '';
            const enrollmentDateText = cells[3]?.textContent?.trim() || '';
            const statusBadge = cells[4]?.querySelector('.badge')?.textContent?.toLowerCase().trim() || '';
            const approvedByCell = cells[5]?.textContent?.toLowerCase().trim() || '';           // Extract term ID and student ID for more precise filtering
            // For term filtering, extract term ID more reliably
            const termCellText = cells[2]?.textContent || '';
            // Try different approaches to extract term ID
            let termId = '';
            if (termCellText.includes('\n')) {
                // If there's a line break, take the first part (which should be the term_id)
                termId = termCellText.split('\n')[0]?.trim();
            } else {
                // If no line break, check if it's just the term ID or contains other text
                // Look for numeric pattern or use the whole text
                const numericMatch = termCellText.match(/^\d+/);
                termId = numericMatch ? numericMatch[0] : termCellText.trim();
            }
            
            const studentId = cells[1]?.textContent?.split('\n')[0]?.trim() || '';
              // Apply term filter (match by term ID)
            if (termFilter) {
                let termMatches = false;
                
                // Primary approach: match by extracted term ID
                if (String(termId).trim() === String(termFilter).trim()) {
                    termMatches = true;
                } else {
                    // Fallback approach: if term ID extraction fails, 
                    // check if the selected term name appears in the cell
                    const selectedTermOption = filterTerm.options[filterTerm.selectedIndex];
                    if (selectedTermOption && selectedTermOption.text) {
                        const selectedTermName = selectedTermOption.text.toLowerCase();
                        if (termCellText.toLowerCase().includes(selectedTermName)) {
                            termMatches = true;
                        }
                    }
                }
                
                if (!termMatches) {
                    showRow = false;
                }
            }
            
            // Apply status filter
            if (statusFilter && statusBadge !== statusFilter) {
                showRow = false;
            }
            
            // Apply search filter (search across multiple fields)
            if (searchText) {
                const searchableText = [
                    enrollmentId,
                    studentCell,
                    termCell,
                    statusBadge,
                    approvedByCell,
                    enrollmentDateText.toLowerCase()
                ].join(' ');
                
                if (!searchableText.includes(searchText)) {
                    showRow = false;
                }
            }
            
            // Show/hide row
            if (showRow) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });        
        // Update active filters display
        updateActiveFilters();
        
        // Update the count display
        updateResultsCount(visibleCount);        
        // Show/hide "no results" message
        showNoResultsMessage(visibleCount === 0 && (searchText || termFilter || statusFilter));
    }
    
    // Function to update results count
    function updateResultsCount(count) {
        const countElement = document.getElementById('enrollmentCount') || document.querySelector('.card-footer small');
        if (countElement) {
            countElement.textContent = `Showing ${count} enrollments`;
        }
    }
    
    // Function to show/hide no results message
    function showNoResultsMessage(show) {
        const tbody = document.querySelector('tbody');
        let noResultsRow = tbody.querySelector('.no-results-row');
        
        if (show) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="9" class="text-center py-4">
                        <div class="d-flex flex-column align-items-center">
                            <i class="bi bi-search fs-1 text-muted mb-2"></i>
                            <p class="mb-0">No enrollments match your search criteria.</p>
                            <p class="text-muted small">Try adjusting your filters or search terms.</p>
                        </div>
                    </td>
                `;
                tbody.appendChild(noResultsRow);
            }
            noResultsRow.style.display = '';
        } else {
            if (noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }
    }    // Function to clear all filters
    function clearAllFilters() {
        filterTerm.value = '';
        filterStatus.value = '';
        searchInput.value = '';
        applyFiltersAndSearch();
    }
      // Event listeners for filters and search
    if (filterTerm) {
        filterTerm.addEventListener('change', applyFiltersAndSearch);
    }
    
    if (filterStatus) {
        filterStatus.addEventListener('change', applyFiltersAndSearch);
    }
    
    if (searchInput) {
        // Search on button click
        if (searchBtn) {
            searchBtn.addEventListener('click', applyFiltersAndSearch);
        }
        
        // Search on Enter key press
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFiltersAndSearch();
            }
        });
        
        // Optional: Real-time search (search as you type)
        searchInput.addEventListener('input', function() {
            // Debounce the search to avoid too many calls
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(applyFiltersAndSearch, 300);        });
    }
    
    // Refresh functionality
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            clearAllFilters();
            location.reload();
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
        
        // Escape to clear search and filters
        if (e.key === 'Escape') {
            if (document.activeElement === searchInput) {
                searchInput.blur();
            } else {
                clearAllFilters();
            }
        }    });
    
    // Initialize filters on page load
    // Run immediately and also with a timeout to ensure DOM is ready
    applyFiltersAndSearch();
    setTimeout(() => {
        applyFiltersAndSearch();
    }, 100);
    
    function fetchEnrollmentData(enrollmentId, mode) {
        fetch(`../../../app/main_proc.php?action=get_enrollment&id=${enrollmentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (mode === 'view') {
                        populateViewModal(data.enrollment);
                    } else if (mode === 'edit') {
                        populateEditModal(data.enrollment);
                    }
                } else {
                    showAlert('danger', data.message || 'Failed to fetch enrollment data');
                }
            })
            .catch(error => {
                console.error('Error fetching enrollment data:', error);
                showAlert('danger', 'An error occurred while fetching enrollment data');
            });
    }      // Populate view modal with data
    function populateViewModal(enrollment) {
        document.getElementById('view_enrollment_id').textContent = enrollment.enrollment_id;
        document.getElementById('view_enrollment_date').textContent = formatDate(enrollment.enrollment_date);
        
        const statusBadge = `<span class="badge ${getStatusBadgeClass(enrollment.status)}">${capitalize(enrollment.status)}</span>`;
        document.getElementById('view_status').innerHTML = statusBadge;
        
        document.getElementById('view_approved_by').textContent = enrollment.approved_by ? 
            `${enrollment.approved_by}${enrollment.staff_name ? ` (${enrollment.staff_name})` : ''}` : '—';
        document.getElementById('view_approved_date').textContent = enrollment.approved_date ? 
            formatDate(enrollment.approved_date) : '—';
        
        document.getElementById('view_student_id').textContent = enrollment.student_id;
        document.getElementById('view_student_name').textContent = enrollment.student_name || '—';
        
        document.getElementById('view_term_id').textContent = enrollment.term_id;
        document.getElementById('view_term_name').textContent = enrollment.term_name || '—';
        document.getElementById('view_term_period').textContent = enrollment.term_period || '—';
        
        document.getElementById('view_notes').textContent = enrollment.notes || 'No notes available';
          // Handle enrolled classes display
        const enrolledClassesSection = document.getElementById('viewEnrolledClassesSection');
        const enrolledClassesList = document.getElementById('viewEnrolledClassesList');
        
        // Show enrolled classes for any enrollment that has enrollment_details, regardless of status
        if (enrollment.enrollment_details && enrollment.enrollment_details.length > 0) {
            enrolledClassesSection.style.display = 'block';
            
            let classesHtml = '';
            enrollment.enrollment_details.forEach(detail => {
                const classData = classesData.find(cls => cls.class_id === detail.class_id);
                if (classData) {
                    const timeInfo = classData.start_time && classData.end_time ? 
                        `${classData.start_time} - ${classData.end_time}` : 'Time TBD';
                    const daysInfo = classData.days_of_week || 'Days TBD';
                    const instructorInfo = classData.instructor_name || 'TBD';
                    
                    classesHtml += `
                        <div class="mb-2 p-2 border rounded">
                            <strong>${classData.course_code} - ${classData.section}</strong><br>
                            <small class="text-muted">
                                ${classData.course_name}<br>
                                ${daysInfo}, ${timeInfo}<br>
                                Room: ${classData.room || 'TBD'} | Instructor: ${instructorInfo}
                            </small>
                        </div>
                    `;
                }
            });
            
            enrolledClassesList.innerHTML = classesHtml || '<small class="text-muted">No class details available</small>';
        } else {
            enrolledClassesSection.style.display = 'none';
        }
    }
    
    // Populate edit modal with data  
    function populateEditModal(enrollment) {
        document.getElementById('edit_enrollment_id').value = enrollment.enrollment_id;
        document.getElementById('edit_student_id').value = enrollment.student_id;
        document.getElementById('edit_term_id').value = enrollment.term_id;
        document.getElementById('edit_enrollment_date').value = formatDateForInput(enrollment.enrollment_date);
        document.getElementById('edit_status').value = enrollment.status;
        
        if (enrollment.approved_by) {
            document.getElementById('edit_approved_by').value = enrollment.approved_by;
        }
        
        if (enrollment.approved_date) {
            document.getElementById('edit_approved_date').value = formatDateForInput(enrollment.approved_date);
        }
        
        document.getElementById('edit_notes').value = enrollment.notes || '';
        
        // Handle classes section and approved fields based on status
        const editClassesSection = document.getElementById('editClassesSection');
        const editApprovedFields = document.querySelector('.edit-approved-fields');
        
        if (enrollment.status === 'approved') {
            editApprovedFields.style.display = 'flex';
            document.getElementById('edit_approved_by').setAttribute('required', '');
            document.getElementById('edit_approved_date').setAttribute('required', '');
            editClassesSection.style.display = 'block';
            
            // Load currently enrolled classes
            editSelectedClasses = [];
            if (enrollment.enrollment_details && enrollment.enrollment_details.length > 0) {
                editSelectedClasses = enrollment.enrollment_details.map(detail => detail.class_id);
            }
            
            // Update classes for the term
            updateEditClassesForTerm(enrollment.term_id);
        } else {
            editApprovedFields.style.display = 'none';
            document.getElementById('edit_approved_by').removeAttribute('required');
            document.getElementById('edit_approved_date').removeAttribute('required');
            editClassesSection.style.display = 'none';
            editSelectedClasses = [];
        }
    }
    
    // Helper functions
    function formatDate(dateString) {
        if (!dateString) return '—';
        const date = new Date(dateString);
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
    
    function formatDateForInput(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toISOString().split('T')[0];
    }
    
    function capitalize(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    
    function getStatusBadgeClass(status) {
        switch(status) {
            case 'approved': return 'bg-success';
            case 'pending': return 'bg-warning text-dark';
            case 'rejected': return 'bg-danger';
            case 'cancelled': return 'bg-secondary';
            default: return 'bg-light text-dark';
        }
    }
    
    // Show alert message
    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        const alertElement = document.createElement('div');
        alertElement.className = `alert alert-${type} alert-dismissible fade show`;
        alertElement.role = 'alert';
        alertElement.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.appendChild(alertElement);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }, 5000);
    }
});
</script>