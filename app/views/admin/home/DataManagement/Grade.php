<?php   
// Get all grades with comprehensive data
try {
    $stmt = $pdo->prepare("        SELECT 
            ed.detail_id, ed.grade, ed.numeric_grade, ed.remarks, ed.status,
            ed.date_added, ed.date_modified,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            s.student_id,
            c.class_id, c.section, c.days_of_week, c.start_time, c.end_time, c.room,
            co.course_code, co.course_name,
            t.term_name, t.term_id,
            CONCAT(st.first_name, ' ', st.last_name) AS instructor_name,
            st.staff_id AS instructor_id
        FROM 
            enrollment_details ed
        JOIN 
            enrollments e ON ed.enrollment_id = e.enrollment_id
        JOIN 
            students s ON e.student_id = s.student_id
        JOIN 
            classes c ON ed.class_id = c.class_id
        JOIN 
            courses co ON c.course_id = co.course_id
        JOIN 
            terms t ON c.term_id = t.term_id
        LEFT JOIN 
            staff st ON c.instructor_id = st.staff_id
        WHERE ed.grade IS NOT NULL AND ed.grade != ''
        ORDER BY
            t.start_date DESC, co.course_code, c.section, s.last_name, s.first_name
    ");
    $stmt->execute();
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching grades: " . $e->getMessage());
    $grades = [];
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

// Fetch staff for dropdowns
try {
    $stmt = $pdo->prepare("SELECT staff_id, first_name, last_name FROM staff ORDER BY last_name, first_name");
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching staff: " . $e->getMessage());
    $staff = [];
}

// Fetch all classes for grade entry
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
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
}

// Get the current staff ID for the logged-in user (if applicable)
$currentStaffId = getStaffIdByUserId($_SESSION['user_id'] ?? null);

// Grade options
$gradeOptions = [
    'A' => 'A (Excellent)',
    'A-' => 'A- (Very Good)',
    'B+' => 'B+ (Good)',
    'B' => 'B (Good)',
    'B-' => 'B- (Satisfactory)',
    'C+' => 'C+ (Fair)',
    'C' => 'C (Fair)',
    'C-' => 'C- (Below Average)',
    'D+' => 'D+ (Poor)',
    'D' => 'D (Poor)',
    'F' => 'F (Fail)',
    'INC' => 'INC (Incomplete)',
    'DRP' => 'DRP (Dropped)',
    'WDN' => 'WDN (Withdrawn)'
];
?>

<!-- Grade Page Content -->
<div>
    <!-- Header -->
    <div class="navbg p-3 rounded rounded-3 shadow-sm">
        <div class="d-flex justify-content-between flex-wrap align-items-center">
            <h5 class="mb-0">Grade Management</h5>
            <div>
                <button type="button" class="btn btn-outline-secondary me-2" id="refreshGradeBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                    <i class="bi bi-plus-lg"></i> Add Grade
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Container for system messages -->
    <div id="alertContainer" class="mt-3">
        <?php if (isset($_SESSION['grade_response']['message'])): ?>
            <div class="alert <?= $_SESSION['grade_response']['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['grade_response']['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['grade_response']); ?>
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
    </div>

    <!-- Search and Filter Controls -->
    <div class="card shadow-sm mb-3 mt-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="filterTerm" class="form-label">Filter by Term</label>
                    <select class="form-select form-select-sm" id="filterTerm">
                        <option value="">All Terms</option>
                        <?php foreach ($terms as $term): ?>
                        <option value="<?= $term['term_id'] ?>">
                            <?= htmlspecialchars($term['term_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterGrade" class="form-label">Filter by Grade</label>
                    <select class="form-select form-select-sm" id="filterGrade">
                        <option value="">All Grades</option>
                        <?php foreach ($gradeOptions as $value => $label): ?>
                        <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="searchGrade" class="form-label">Search</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="searchGrade" 
                               placeholder="Search by student name, course, or class...">
                        <button class="btn btn-outline-success" type="button" id="searchGradeBtn">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <h5 class="card-title text-primary"><?= count($grades) ?></h5>
                    <p class="card-text">Total Grades</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h5 class="card-title text-success">
                        <?= count(array_filter($grades, fn($g) => in_array($g['grade'], ['A', 'A-', 'B+', 'B']))) ?>
                    </h5>
                    <p class="card-text">High Grades (A-B)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning">
                        <?= count(array_filter($grades, fn($g) => in_array($g['grade'], ['C+', 'C', 'C-']))) ?>
                    </h5>
                    <p class="card-text">Average Grades (C)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">
                        <?= count(array_filter($grades, fn($g) => in_array($g['grade'], ['D+', 'D', 'F']))) ?>
                    </h5>
                    <p class="card-text">Low Grades (D-F)</p>
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
                            <th>Student</th>
                            <th>Course</th>
                            <th>Class Details</th>
                            <th>Term</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Numeric Grade</th>
                            <th>Instructor</th>
                            <th class="text-center">Last Modified</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="gradesTableBody">
                        <?php if(empty($grades)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-mortarboard fs-1 text-muted mb-2"></i>
                                        <p class="mb-0">No grades found.</p>
                                        <p class="text-muted small">Try adjusting your search criteria or add a new grade.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($grades as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['student_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($row['student_id']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['course_code']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($row['course_name']) ?></small>
                                    </td>
                                    <td>
                                        Section <?= htmlspecialchars($row['section']) ?>
                                        <br><small class="text-muted">
                                            <?= htmlspecialchars($row['days_of_week']) ?> 
                                            <?= date('g:i A', strtotime($row['start_time'])) ?>-<?= date('g:i A', strtotime($row['end_time'])) ?>
                                            <br>Room: <?= htmlspecialchars($row['room']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['term_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= getGradeColor($row['grade']) ?> fs-6">
                                            <?= htmlspecialchars($row['grade']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?= $row['numeric_grade'] ? number_format($row['numeric_grade'], 1) : '—' ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['instructor_name'] ?: 'No Instructor') ?>
                                    </td>
                                    <td class="text-center">
                                        <?= $row['date_modified'] ? date('M d, Y', strtotime($row['date_modified'])) : date('M d, Y', strtotime($row['date_added'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary viewGradeBtn" 
                                                    data-id="<?= htmlspecialchars($row['detail_id']) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewGradeModal">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-outline-secondary editGradeBtn" 
                                                    data-id="<?= htmlspecialchars($row['detail_id']) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editGradeModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-outline-danger deleteGradeBtn" 
                                                    data-id="<?= htmlspecialchars($row['detail_id']) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteGradeModal">
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
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Showing <?= count($grades) ?> grades</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Grade Modal -->
<div class="modal fade" id="addGradeModal" tabindex="-1" aria-labelledby="addGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="addGradeModalLabel">Add New Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>            <div class="modal-body">
                <form id="addGradeForm" action="?page=Manage&subpage=Grade&action=add_grade" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_grade">
                    <input type="hidden" name="staff_id" value="<?= htmlspecialchars($currentStaffId ?: '') ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_student_id" class="form-label">Student <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_student_id" name="student_id" required>
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
                            <label for="add_term_id" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_term_id" name="term_id" required>
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
                        <div class="col-md-12">
                            <label for="add_class_id" class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_class_id" name="class_id" required>
                                <option value="" selected disabled>-- Select Term and Student First --</option>
                            </select>
                            <div class="invalid-feedback">Please select a class.</div>
                            <div class="form-text">Only classes where the student is enrolled but doesn't have a grade yet will be shown.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_grade" class="form-label">Letter Grade <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_grade" name="grade" required>
                                <option value="" selected disabled>-- Select Grade --</option>
                                <?php foreach ($gradeOptions as $value => $label): ?>
                                <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a grade.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="add_numeric_grade" class="form-label">Numeric Grade</label>
                            <input type="number" class="form-control" id="add_numeric_grade" name="numeric_grade" 
                                   min="0" max="100" step="0.1" placeholder="e.g., 95.5">
                            <div class="form-text">Optional: Enter numeric equivalent (0-100)</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="add_remarks" name="remarks" rows="3" maxlength="500"></textarea>
                        <div class="form-text">Optional: Add any relevant comments about this grade.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addGradeForm" class="btn btn-success">Save Grade</button>
            </div>
        </div>
    </div>
</div>

<!-- View Grade Modal -->
<div class="modal fade" id="viewGradeModal" tabindex="-1" aria-labelledby="viewGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="viewGradeModalLabel">Grade Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Student</h6>
                        <div id="view_student_info" class="p-2 bg-light rounded"></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Course</h6>
                        <div id="view_course_info" class="p-2 bg-light rounded"></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Class Details</h6>
                        <div id="view_class_info" class="p-2 bg-light rounded"></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Term</h6>
                        <div id="view_term_info" class="p-2 bg-light rounded"></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <h6 class="fw-bold">Letter Grade</h6>
                        <div id="view_grade" class="p-2 bg-light rounded text-center"></div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold">Numeric Grade</h6>
                        <div id="view_numeric_grade" class="p-2 bg-light rounded text-center"></div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold">Status</h6>
                        <div id="view_status" class="p-2 bg-light rounded text-center"></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Date Created</h6>
                        <div id="view_date_added" class="p-2 bg-light rounded"></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Last Modified</h6>
                        <div id="view_date_modified" class="p-2 bg-light rounded"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6 class="fw-bold">Remarks</h6>
                    <div id="view_remarks" class="p-2 bg-light rounded"></div>
                </div>
                
                <div class="mb-3">
                    <h6 class="fw-bold">Grade History</h6>
                    <div id="view_grade_history" class="p-2 bg-light rounded">
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="ms-2">Loading grade history...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary editFromViewBtn">
                    <i class="bi bi-pencil"></i> Edit Grade
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Grade Modal -->
<div class="modal fade" id="editGradeModal" tabindex="-1" aria-labelledby="editGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="editGradeModalLabel">Edit Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editGradeForm" action="?page=Manage&subpage=Grade&action=edit_grade" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="edit_grade">
                    <input type="hidden" name="detail_id" id="edit_detail_id">
                    <input type="hidden" name="staff_id" value="<?= htmlspecialchars($currentStaffId ?: '') ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h6 class="fw-bold">Student & Class Information</h6>
                            <div id="edit_student_class_info" class="p-2 bg-light rounded mb-3"></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_grade" class="form-label">Letter Grade <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_grade" name="grade" required>
                                <option value="" disabled>-- Select Grade --</option>
                                <?php foreach ($gradeOptions as $value => $label): ?>
                                <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a grade.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_numeric_grade" class="form-label">Numeric Grade</label>
                            <input type="number" class="form-control" id="edit_numeric_grade" name="numeric_grade" 
                                   min="0" max="100" step="0.1" placeholder="e.g., 95.5">
                            <div class="form-text">Optional: Enter numeric equivalent (0-100)</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="edit_remarks" name="remarks" rows="3" maxlength="500"></textarea>
                        <div class="form-text">Optional: Add any relevant comments about this grade.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_reason" class="form-label">Reason for Change <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_reason" name="reason" required>
                            <option value="" disabled selected>-- Select Reason --</option>
                            <option value="Grade correction">Grade correction</option>
                            <option value="Recomputation">Recomputation</option>
                            <option value="Late submission accepted">Late submission accepted</option>
                            <option value="Extra credit applied">Extra credit applied</option>
                            <option value="Administrative adjustment">Administrative adjustment</option>
                            <option value="Error in original entry">Error in original entry</option>
                            <option value="Other">Other</option>
                        </select>
                        <div class="invalid-feedback">Please select a reason for the grade change.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editGradeForm" class="btn btn-primary">Update Grade</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Grade Modal -->
<div class="modal fade" id="deleteGradeModal" tabindex="-1" aria-labelledby="deleteGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="deleteGradeModalLabel">Delete Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning!</strong> This action cannot be undone.
                </div>
                
                <p>Are you sure you want to delete this grade?</p>
                
                <div id="delete_grade_info" class="p-3 bg-light rounded"></div>
                
                <form id="deleteGradeForm" action="?page=Manage&subpage=Grade&action=delete_grade" method="POST">
                    <input type="hidden" name="action" value="delete_grade">
                    <input type="hidden" name="detail_id" id="delete_detail_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteGradeForm" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Delete Grade
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Global variables to store data
    let classesData = <?= json_encode($classes) ?>;
    let studentsData = <?= json_encode($students) ?>;
    let currentGradeData = null;
    
    // Form validation
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
    
    // Filter classes based on term selection
    function updateClassesForTerm(termId, studentId, selectElement) {
        const filteredClasses = classesData.filter(cls => cls.term_id === termId);
        
        selectElement.innerHTML = '<option value="" selected disabled>-- Select Class --</option>';
        
        if (filteredClasses.length === 0) {
            selectElement.innerHTML = '<option value="" disabled>No classes available for this term</option>';
            return;
        }
        
        filteredClasses.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.class_id;
            option.textContent = `${cls.course_code} - Section ${cls.section} (${cls.instructor_name || 'No Instructor'})`;
            selectElement.appendChild(option);
        });
    }
    
    // Handle term selection change for add form
    const addTermSelect = document.getElementById('add_term_id');
    const addStudentSelect = document.getElementById('add_student_id');
    const addClassSelect = document.getElementById('add_class_id');
      function updateAddClassOptions() {
        const termId = addTermSelect.value;
        const studentId = addStudentSelect.value;
        
        console.log('updateAddClassOptions called with termId:', termId, 'studentId:', studentId);
        
        if (termId && studentId) {
            // Show loading state
            addClassSelect.innerHTML = '<option value="" disabled>Loading classes...</option>';
              // Fetch available classes for this student in this term
            const url = `?page=Manage&subpage=Grade&action=get_available_classes&student_id=${studentId}&term_id=${termId}`;
            console.log('Fetching from URL:', url);
            
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    addClassSelect.innerHTML = '<option value="" selected disabled>-- Select Class --</option>';
                    
                    if (data.error) {
                        console.error('Server error:', data.error);
                        addClassSelect.innerHTML = '<option value="" disabled>Error loading classes</option>';
                        showAlert('danger', 'Error loading classes: ' + data.error);
                    } else if (data.classes && data.classes.length > 0) {
                        data.classes.forEach(cls => {
                            const option = document.createElement('option');
                            option.value = cls.class_id;
                            option.textContent = `${cls.course_code} - Section ${cls.section}`;
                            addClassSelect.appendChild(option);
                        });
                        console.log('Added', data.classes.length, 'classes to dropdown');
                    } else {
                        addClassSelect.innerHTML = '<option value="" disabled>No eligible classes found</option>';
                        console.log('No classes found for this student/term combination');
                    }
                })
                .catch(error => {
                    console.error('Error fetching classes:', error);
                    addClassSelect.innerHTML = '<option value="" disabled>Error loading classes</option>';
                    showAlert('danger', 'Error loading classes: ' + error.message);
                });
        } else {
            addClassSelect.innerHTML = '<option value="" selected disabled>-- Select Term and Student First --</option>';
        }
    }
    
    addTermSelect.addEventListener('change', updateAddClassOptions);
    addStudentSelect.addEventListener('change', updateAddClassOptions);
    
    // Handle view button click
    const viewButtons = document.querySelectorAll('.viewGradeBtn');
    Array.from(viewButtons).forEach(button => {
        button.addEventListener('click', function() {
            const detailId = this.getAttribute('data-id');
            fetchGradeDetails(detailId, 'view');
        });
    });
    
    // Handle edit button click
    const editButtons = document.querySelectorAll('.editGradeBtn');
    Array.from(editButtons).forEach(button => {
        button.addEventListener('click', function() {
            const detailId = this.getAttribute('data-id');
            fetchGradeDetails(detailId, 'edit');
        });
    });
    
    // Handle delete button click
    const deleteButtons = document.querySelectorAll('.deleteGradeBtn');
    Array.from(deleteButtons).forEach(button => {
        button.addEventListener('click', function() {
            const detailId = this.getAttribute('data-id');
            fetchGradeDetails(detailId, 'delete');
        });
    });
      // Fetch grade details
    function fetchGradeDetails(detailId, action) {
        fetch(`?page=Manage&subpage=Grade&action=get_grade_details&detail_id=${detailId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentGradeData = data.grade;
                    
                    if (action === 'view') {
                        populateViewModal(data.grade, data.history);
                    } else if (action === 'edit') {
                        populateEditModal(data.grade);
                    } else if (action === 'delete') {
                        populateDeleteModal(data.grade);
                    }
                } else {
                    showAlert('danger', 'Failed to load grade details: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching grade details:', error);
                showAlert('danger', 'An error occurred while loading grade details');
            });
    }
    
    // Populate view modal
    function populateViewModal(grade, history) {
        document.getElementById('view_student_info').innerHTML = `
            <strong>${grade.student_name}</strong><br>
            <small class="text-muted">ID: ${grade.student_id}</small>
        `;
        
        document.getElementById('view_course_info').innerHTML = `
            <strong>${grade.course_code}</strong><br>
            <small class="text-muted">${grade.course_name}</small>
        `;
        
        document.getElementById('view_class_info').innerHTML = `
            Section ${grade.section}<br>
            <small class="text-muted">
                ${grade.days_of_week} ${grade.start_time}-${grade.end_time}<br>
                Room: ${grade.room}
            </small>
        `;
        
        document.getElementById('view_term_info').innerHTML = grade.term_name;
        
        document.getElementById('view_grade').innerHTML = `
            <span class="badge ${getGradeColorClass(grade.grade)} fs-5">${grade.grade}</span>
        `;
        
        document.getElementById('view_numeric_grade').innerHTML = 
            grade.numeric_grade ? parseFloat(grade.numeric_grade).toFixed(1) : '—';
        
        document.getElementById('view_status').innerHTML = `
            <span class="badge bg-${getStatusColor(grade.status)}">${ucfirst(grade.status)}</span>
        `;
          document.getElementById('view_date_added').innerHTML = 
            grade.date_added ? new Date(grade.date_added).toLocaleDateString() : '—';
        
        document.getElementById('view_date_modified').innerHTML = 
            grade.date_modified ? new Date(grade.date_modified).toLocaleDateString() : '—';
        
        document.getElementById('view_remarks').innerHTML = 
            grade.remarks || '<em class="text-muted">No remarks</em>';
          // Load grade history
        if (history && history.length > 0) {
            let historyHtml = '<div class="timeline">';
            history.forEach(h => {
                const changeDate = h.change_date ? new Date(h.change_date).toLocaleDateString() : 'Unknown date';
                const changedBy = h.changed_by_name || 'Unknown user';
                const reason = h.reason || 'No reason provided';
                const previousGrade = h.previous_grade || 'New';
                const newGrade = h.new_grade || 'Unknown';
                
                historyHtml += `
                    <div class="timeline-item mb-2">
                        <div class="d-flex justify-content-between">
                            <strong>${previousGrade} → ${newGrade}</strong>
                            <small class="text-muted">${changeDate}</small>
                        </div>
                        <div><small class="text-muted">By: ${changedBy}</small></div>
                        <div><small>${reason}</small></div>
                    </div>
                `;
            });
            historyHtml += '</div>';
            document.getElementById('view_grade_history').innerHTML = historyHtml;
        } else {
            document.getElementById('view_grade_history').innerHTML = 
                '<em class="text-muted">No grade history available</em>';
        }
    }
    
    // Populate edit modal
    function populateEditModal(grade) {
        document.getElementById('edit_detail_id').value = grade.detail_id;
        
        document.getElementById('edit_student_class_info').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <strong>Student:</strong> ${grade.student_name} (${grade.student_id})
                </div>
                <div class="col-md-6">
                    <strong>Class:</strong> ${grade.course_code} - Section ${grade.section}
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6">
                    <strong>Course:</strong> ${grade.course_name}
                </div>
                <div class="col-md-6">
                    <strong>Term:</strong> ${grade.term_name}
                </div>
            </div>
        `;
        
        document.getElementById('edit_grade').value = grade.grade;
        document.getElementById('edit_numeric_grade').value = grade.numeric_grade || '';
        document.getElementById('edit_remarks').value = grade.remarks || '';
        document.getElementById('edit_reason').value = '';
    }
    
    // Populate delete modal
    function populateDeleteModal(grade) {
        document.getElementById('delete_detail_id').value = grade.detail_id;
        
        document.getElementById('delete_grade_info').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <strong>Student:</strong><br>
                    ${grade.student_name} (${grade.student_id})
                </div>
                <div class="col-md-6">
                    <strong>Grade:</strong><br>
                    <span class="badge ${getGradeColorClass(grade.grade)} fs-6">${grade.grade}</span>
                    ${grade.numeric_grade ? ` (${parseFloat(grade.numeric_grade).toFixed(1)})` : ''}
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <strong>Course:</strong> ${grade.course_code} - ${grade.course_name}<br>
                    <strong>Section:</strong> ${grade.section} | <strong>Term:</strong> ${grade.term_name}
                </div>
            </div>
        `;
    }
    
    // Handle edit from view button
    const editFromViewBtn = document.querySelector('.editFromViewBtn');
    if (editFromViewBtn) {
        editFromViewBtn.addEventListener('click', function() {
            if (currentGradeData) {
                // Close view modal
                const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewGradeModal'));
                viewModal.hide();
                
                // Open edit modal
                setTimeout(() => {
                    populateEditModal(currentGradeData);
                    const editModal = new bootstrap.Modal(document.getElementById('editGradeModal'));
                    editModal.show();
                }, 300);
            }
        });
    }
    
    // Handle refresh button
    const refreshBtn = document.getElementById('refreshGradeBtn');
    refreshBtn.addEventListener('click', function() {
        window.location.reload();
    });
    
    // Search and filter functionality
    const searchInput = document.getElementById('searchGrade');
    const termFilter = document.getElementById('filterTerm');
    const gradeFilter = document.getElementById('filterGrade');
    const tableBody = document.getElementById('gradesTableBody');
    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedTerm = termFilter.value;
        const selectedGrade = gradeFilter.value;
        
        allRows.forEach(row => {
            if (row.querySelector('td[colspan]')) return; // Skip "no data" row
            
            const cells = row.querySelectorAll('td');
            const studentName = cells[0].textContent.toLowerCase();
            const courseInfo = cells[1].textContent.toLowerCase();
            const classInfo = cells[2].textContent.toLowerCase();
            const termName = cells[3].textContent.trim();
            const grade = cells[4].querySelector('.badge').textContent.trim();
            
            const matchesSearch = !searchTerm || 
                studentName.includes(searchTerm) || 
                courseInfo.includes(searchTerm) || 
                classInfo.includes(searchTerm);
            
            const matchesTerm = !selectedTerm || termName.includes(getTermNameById(selectedTerm));
            const matchesGrade = !selectedGrade || grade === selectedGrade;
            
            if (matchesSearch && matchesTerm && matchesGrade) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    searchInput.addEventListener('input', filterTable);
    termFilter.addEventListener('change', filterTable);
    gradeFilter.addEventListener('change', filterTable);
    
    document.getElementById('searchGradeBtn').addEventListener('click', filterTable);
    
    // Helper functions
    function getGradeColorClass(grade) {
        const colorMap = {
            'A': 'bg-success', 'A-': 'bg-success',
            'B+': 'bg-primary', 'B': 'bg-primary', 'B-': 'bg-primary',
            'C+': 'bg-warning', 'C': 'bg-warning', 'C-': 'bg-warning',
            'D+': 'bg-danger', 'D': 'bg-danger',
            'F': 'bg-danger',
            'INC': 'bg-warning',
            'DRP': 'bg-secondary', 'WDN': 'bg-secondary'
        };
        return colorMap[grade] || 'bg-secondary';
    }
    
    function getStatusColor(status) {
        const colorMap = {
            'enrolled': 'primary',
            'completed': 'success',
            'dropped': 'secondary'
        };
        return colorMap[status] || 'secondary';
    }
    
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    function getTermNameById(termId) {
        const terms = <?= json_encode($terms) ?>;
        const term = terms.find(t => t.term_id === termId);
        return term ? term.term_name : '';
    }
    
    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertContainer.innerHTML = alertHtml;
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }
});
</script>
