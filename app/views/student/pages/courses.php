<?php
/**
 * Student Courses Page
 * Displays current courses, course history, and available courses for enrollment
 */

// Include the courses backend
include_once "../../Controllers/student-page/courses.backend.php";

// Get student ID from session
$user_id = $_SESSION['user_id'] ?? '';

if (empty($user_id)) {
    echo '<div class="alert alert-danger">User ID not found in session</div>';
    return;
}

// Get the actual student_id from the students table using user_id
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student_data) {
    echo '<div class="alert alert-danger">Student record not found</div>';
    return;
}

$student_id = $student_data['student_id'];

// Fetch course data
$currentCourses = getStudentCurrentCourses($pdo, $student_id);
$courseHistory = getStudentCourseHistory($pdo, $student_id);
$availableCourses = getAvailableCoursesForEnrollment($pdo, $student_id);
$academicSummary = getStudentAcademicSummary($pdo, $student_id);

// Get notification from session
$notification = $_SESSION['course_response'] ?? null;
unset($_SESSION['course_response']);
?>

<div class="container-fluid">
    <?php if ($notification): ?>
        <div class="alert alert-<?php echo $notification['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($notification['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="bi bi-book-open text-primary me-2"></i>
                    My Courses
                </h2>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary fs-6 me-3">
                        <i class="bi bi-clock me-1"></i>
                        Current Units: <?php echo $academicSummary['current_units']; ?>
                    </span>
                    <span class="badge bg-success fs-6">
                        <i class="bi bi-graduation-cap me-1"></i>
                        Completed Units: <?php echo $academicSummary['completed_units']; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-book text-primary fs-4"></i>
                    </div>
                    <h4 class="mt-3 mb-1"><?php echo $academicSummary['current_courses']; ?></h4>
                    <p class="text-muted mb-0">Current Courses</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-check-circle text-success fs-4"></i>
                    </div>
                    <h4 class="mt-3 mb-1"><?php echo $academicSummary['completed_courses']; ?></h4>
                    <p class="text-muted mb-0">Completed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-calculator text-info fs-4"></i>
                    </div>
                    <h4 class="mt-3 mb-1"><?php echo number_format($academicSummary['gpa'], 2); ?></h4>
                    <p class="text-muted mb-0">Current GPA</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-graph-up-arrow text-warning fs-4"></i>
                    </div>
                    <h4 class="mt-3 mb-1"><?php echo $academicSummary['progress_percentage']; ?>%</h4>
                    <p class="text-muted mb-0">Progress</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-justified mb-4" id="courseTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#current" type="button" role="tab">
                <i class="bi bi-calendar-check me-2"></i>
                Current Courses (<?php echo count($currentCourses); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">
                <i class="bi bi-plus-circle me-2"></i>
                Available Courses (<?php echo count($availableCourses); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                <i class="bi bi-clock-history me-2"></i>
                Course History (<?php echo count($courseHistory); ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="courseTabContent">
        <!-- Current Courses Tab -->
        <div class="tab-pane fade show active" id="current" role="tabpanel">
            <?php if (empty($currentCourses)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-book-open text-muted fs-1 mb-3"></i>
                        <h5 class="text-muted">No Current Courses</h5>
                        <p class="text-muted">You are not currently enrolled in any courses for this term.</p>
                        <button class="btn btn-primary" onclick="document.getElementById('available-tab').click()">
                            <i class="bi bi-plus me-2"></i>Browse Available Courses
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($currentCourses as $course): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary text-white">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                                            <small class="opacity-75">Section <?php echo htmlspecialchars($course['section']); ?></small>
                                        </div>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $course['units']; ?> Units
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                    <p class="card-text text-muted small mb-3">
                                        <?php echo htmlspecialchars(substr($course['course_description'], 0, 100)) . (strlen($course['course_description']) > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Instructor</small>
                                            <strong class="small"><?php echo htmlspecialchars($course['instructor_name']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Room</small>
                                            <strong class="small"><?php echo htmlspecialchars($course['room']); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Schedule</small>
                                        <strong class="small"><?php echo htmlspecialchars($course['schedule']); ?></strong>
                                    </div>

                                    <?php if (!empty($course['grade'])): ?>
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Current Grade</small>
                                            <span class="badge <?php echo getGradeBadgeClass($course['grade'], $course['numeric_grade']); ?>">
                                                <?php echo htmlspecialchars($course['grade']); ?>
                                                <?php if ($course['numeric_grade']): ?>
                                                    (<?php echo $course['numeric_grade']; ?>%)
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <small class="text-muted d-block">Enrollment</small>
                                        <div class="progress" style="height: 6px;">
                                            <?php $percentage = ($course['enrolled_count'] / $course['max_students']) * 100; ?>
                                            <div class="progress-bar bg-info" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> students</small>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo htmlspecialchars($course['term_name']); ?>
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewCourseDetails('<?php echo $course['course_id']; ?>')">
                                                <i class="bi bi-info-circle"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="dropCourse('<?php echo $course['class_id']; ?>', '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                                <i class="bi bi-dash-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Courses Tab -->
        <div class="tab-pane fade" id="available" role="tabpanel">
            <?php if (empty($availableCourses)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-graduation-cap text-muted fs-1 mb-3"></i>
                        <h5 class="text-muted">No Available Courses</h5>
                        <p class="text-muted">There are no courses available for enrollment at this time.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($availableCourses as $course): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-success text-white">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                                            <small class="opacity-75">Section <?php echo htmlspecialchars($course['section']); ?></small>
                                        </div>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $course['units']; ?> Units
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                    <p class="card-text text-muted small mb-3">
                                        <?php echo htmlspecialchars(substr($course['course_description'], 0, 100)) . (strlen($course['course_description']) > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Instructor</small>
                                            <strong class="small"><?php echo htmlspecialchars($course['instructor_name']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Room</small>
                                            <strong class="small"><?php echo htmlspecialchars($course['room']); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Schedule</small>
                                        <strong class="small"><?php echo htmlspecialchars($course['schedule']); ?></strong>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted d-block">Available Slots</small>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                <?php $percentage = ($course['enrolled_count'] / $course['max_students']) * 100; ?>
                                                <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo $course['available_slots']; ?> left</small>
                                        </div>
                                        <small class="text-muted"><?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> enrolled</small>
                                    </div>

                                    <?php if ($course['has_lab']): ?>
                                        <div class="mb-3">
                                            <span class="badge bg-info">
                                            Has Laboratory
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo htmlspecialchars($course['term_name']); ?>
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewCourseDetails('<?php echo $course['course_id']; ?>')">
                                                <i class="bi bi-info-circle"></i>
                                            </button>
                                            <button class="btn btn-success" onclick="enrollCourse('<?php echo $course['class_id']; ?>', '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                                <i class="bi bi-plus-circle me-1"></i>Enroll
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Course History Tab -->
        <div class="tab-pane fade" id="history" role="tabpanel">
            <?php if (empty($courseHistory)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-clock-history text-muted fs-1 mb-3"></i>
                        <h5 class="text-muted">No Course History</h5>
                        <p class="text-muted">You haven't completed any courses yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course</th>
                                        <th>Term</th>
                                        <th>Instructor</th>
                                        <th>Units</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $currentYear = '';
                                    foreach ($courseHistory as $course): 
                                        if ($currentYear !== $course['academic_year']):
                                            $currentYear = $course['academic_year'];
                                    ?>
                                        <tr class="table-secondary">
                                            <td colspan="6" class="fw-bold">
                                                <i class="bi bi-calendar-alt me-2"></i>
                                                Academic Year <?php echo htmlspecialchars($currentYear); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($course['course_code']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($course['course_name']); ?></small>
                                                    <br>
                                                    <small class="badge bg-light text-dark">Section <?php echo htmlspecialchars($course['section']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($course['term_name']); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($course['instructor_name']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $course['units']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getGradeBadgeClass($course['grade'], $course['numeric_grade']); ?>">
                                                    <?php echo htmlspecialchars($course['grade']); ?>
                                                    <?php if ($course['numeric_grade']): ?>
                                                        (<?php echo $course['numeric_grade']; ?>%)
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getEnrollmentStatusBadgeClass($course['enrollment_status']); ?>">
                                                    <?php echo ucfirst($course['enrollment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Course Details Modal -->
<div class="modal fade" id="courseDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Course Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="courseDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modals -->
<div class="modal fade" id="enrollConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to enroll in <strong id="enrollCourseName"></strong>?</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Your enrollment will be submitted for approval.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmEnrollBtn">
                    <i class="bi bi-plus-circle me-2"></i>Enroll
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dropConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Drop Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to drop <strong id="dropCourseName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    This action cannot be undone. You may need to re-enroll if you change your mind.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDropBtn">
                    <i class="bi bi-minus-circle me-2"></i>Drop Course
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Course enrollment function
function enrollCourse(classId, courseName) {
    document.getElementById('enrollCourseName').textContent = courseName;
    document.getElementById('confirmEnrollBtn').onclick = function() {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'dashboard.php?page=courses&action=enroll';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'enroll';
        form.appendChild(actionInput);
        
        const classIdInput = document.createElement('input');
        classIdInput.type = 'hidden';
        classIdInput.name = 'class_id';
        classIdInput.value = classId;
        form.appendChild(classIdInput);
        
        document.body.appendChild(form);
        form.submit();
    };
    
    const modal = new bootstrap.Modal(document.getElementById('enrollConfirmModal'));
    modal.show();
}

// Course drop function
function dropCourse(classId, courseName) {
    document.getElementById('dropCourseName').textContent = courseName;
    document.getElementById('confirmDropBtn').onclick = function() {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'dashboard.php?page=courses&action=drop';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'drop';
        form.appendChild(actionInput);
        
        const classIdInput = document.createElement('input');
        classIdInput.type = 'hidden';
        classIdInput.name = 'class_id';
        classIdInput.value = classId;
        form.appendChild(classIdInput);
        
        document.body.appendChild(form);
        form.submit();
    };
    
    const modal = new bootstrap.Modal(document.getElementById('dropConfirmModal'));
    modal.show();
}

// View course details function
function viewCourseDetails(courseId) {
    const modal = new bootstrap.Modal(document.getElementById('courseDetailsModal'));
    const content = document.getElementById('courseDetailsContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Fetch course details
    fetch(`dashboard.php?page=courses&action=view_details&course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        ${data.error}
                    </div>
                `;
                return;
            }
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <h5>${data.course_code} - ${data.course_name}</h5>
                        <p class="text-muted">${data.description || 'No description available.'}</p>
                        
                        <div class="row g-3 mt-3">
                            <div class="col-6">
                                <strong>Units:</strong> ${data.units}
                            </div>
                            <div class="col-6">
                                <strong>Status:</strong> 
                                <span class="badge bg-${data.status === 'active' ? 'success' : 'secondary'}">${data.status}</span>
                            </div>
                            ${data.has_lab ? '<div class="col-12"><span class="badge bg-info"><i class="bi bi-flask me-1"></i>Has Laboratory Component</span></div>' : ''}
                            ${data.program_name ? `<div class="col-12"><strong>Program:</strong> ${data.program_name}</div>` : ''}
                            ${data.prerequisites ? `<div class="col-12"><strong>Prerequisites:</strong><br><small class="text-muted">${data.prerequisites}</small></div>` : ''}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Course Information</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Course ID:</strong> ${data.course_id}</li>
                                    <li><strong>Created:</strong> ${data.created_at ? new Date(data.created_at).toLocaleDateString() : 'N/A'}</li>
                                    <li><strong>Updated:</strong> ${data.updated_at ? new Date(data.updated_at).toLocaleDateString() : 'N/A'}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    Error loading course details. Please try again.
                </div>
            `;
        });
}

// Tab persistence
document.addEventListener('DOMContentLoaded', function() {
    // Check for saved tab or default to first tab
    const savedTab = localStorage.getItem('activeCoursesTab') || 'current';
    const tabElement = document.getElementById(`${savedTab}-tab`);
    if (tabElement) {
        const tab = new bootstrap.Tab(tabElement);
        tab.show();
    }
    
    // Save tab selection
    document.querySelectorAll('#courseTabs button[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (event) {
            const tabId = event.target.getAttribute('data-bs-target').substring(1);
            localStorage.setItem('activeCoursesTab', tabId);
        });
    });
});
</script>
