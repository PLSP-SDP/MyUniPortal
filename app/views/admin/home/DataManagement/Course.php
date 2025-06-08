<?php
// Debug: Log all GET parameters
error_log("Course.php - GET parameters: " . print_r($_GET, true));

// Process any course requests (add, edit, delete)
$course_response = process_Course_Request($pdo);
$staffId = getStaffIdByUserId($userID);

// Debug search parameters specifically and directly test the search function
if (isset($_GET['search']) && !empty($_GET['search'])) {
    error_log("Course.php - Search parameter received: '" . $_GET['search'] . "'");
    
    // Test direct SQL search to isolate the issue
    try {
        $searchTerm = '%' . $_GET['search'] . '%';
        $testSql = "SELECT COUNT(*) FROM courses WHERE course_code LIKE ? OR course_name LIKE ?";
        $testStmt = $pdo->prepare($testSql);
        $testStmt->execute([$searchTerm, $searchTerm]);
        $testCount = $testStmt->fetchColumn();
        
        error_log("Direct SQL search test - found " . $testCount . " courses matching '" . $_GET['search'] . "'");
    } catch (PDOException $e) {
        error_log("Error in direct SQL search test: " . $e->getMessage());
    }
}

// Get course data with optional filtering
$courseData = get_CourseData($pdo);

// Debug: Log number of results returned
error_log("Course.php - Found " . count($courseData) . " courses");

// Get programs for dropdown
try {
    $stmt = $pdo->prepare("SELECT * FROM programs ORDER BY program_name");
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching programs: " . $e->getMessage());
    $programs = [];
}
?>

<div>
        <div class="d-flex justify-content-between flex-wrap align-items-center navbg p-3 rounded-3 shadow-sm">
            <h5 class="mb-0">Course Management</h5>
            <div>
                <button type="button" class="btn btn-outline-secondary me-2" id="refreshCourseBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="bi bi-plus-lg"></i> Add Course
                </button>
            </div>
        </div>
    </div>
    
    <!-- Alert Container for system messages -->
    <div id="alertContainer" class="mt-3">
        <?php if (!empty($course_response['message'])): ?>
            <div class="alert <?= $course_response['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= $course_response['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>
      <!-- Search and Filter Controls -->    <div class="card shadow-sm mb-3 mt-3">
        <div class="card-body">
            <!-- Main search and filter form -->
            <form id="courseSearchForm" class="row g-3" method="GET" action="">
                <input type="hidden" name="page" value="Manage">
                <input type="hidden" name="subpage" value="Course">
                <div class="col-md-3">
                    <label for="programFilter" class="form-label">Program</label>
                    <select class="form-select form-select-sm" id="programFilter" name="program">
                        <option value="">All Programs</option>
                        <?php foreach($programs as $program): ?>
                            <option value="<?= $program['program_id'] ?>" <?= isset($_GET['program']) && $_GET['program'] == $program['program_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($program['program_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                  <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="statusFilter" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= isset($_GET['status']) && $_GET['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="labFilter" class="form-label">Lab Component</label>
                    <select class="form-select form-select-sm" id="labFilter" name="has_lab">
                        <option value="">All Courses</option>
                        <option value="1" <?= isset($_GET['has_lab']) && $_GET['has_lab'] == '1' ? 'selected' : '' ?>>With Lab</option>
                        <option value="0" <?= isset($_GET['has_lab']) && $_GET['has_lab'] == '0' ? 'selected' : '' ?>>Without Lab</option>
                    </select>
                </div>                <div class="col-md-4">
                    <label for="searchInput" class="form-label">Search</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm" id="searchInput" name="search" placeholder="Course code or name..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        <button type="submit" id="searchButton" class="btn btn-outline-success">
                            <i class="bi bi-search"></i> Search
                        </button> 
                    </div>
                    <div class="form-text">
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <span>Searching for: <strong><?= htmlspecialchars($_GET['search']) ?></strong></span>
                            <a href="?page=Manage&subpage=Course" class="text-decoration-none ms-2">(Clear search)</a>
                        <?php endif; ?>
                    </div>
                </div>
                
            </form>
        </div>
    </div>
    
    <!-- Course Data Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Program</th>
                            <th>Units</th>
                            <th>Lab</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($courseData)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-search fs-1 text-muted mb-2"></i>
                                        <p class="mb-0">No courses found.</p>
                                        <p class="text-muted small">Try adjusting your search criteria or add a new course.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($courseData as $course): ?>                                <tr>
                                    <td>
                                        <a href="?page=Manage&subpage=Course&search=<?= urlencode($course['course_code'] ?? '') ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($course['course_code'] ?? '') ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($course['course_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($course['program_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($course['units'] ?? '') ?></td>
                                    <td>
                                        <?php if (($course['has_lab'] ?? false)): ?>
                                            <span class="badge bg-info">With Lab</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">No Lab</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($course['status'] ?? '') === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">                                            <button type="button" class="btn btn-sm btn-outline-primary view-course" 
                                                data-course-id="<?= htmlspecialchars($course['course_id'] ?? '') ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewCourseModal">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary edit-course" 
                                                data-course-id="<?= $course['course_id'] ?? '' ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCourseModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>                                            <button type="button" class="btn btn-sm btn-outline-danger delete-course" 
                                                data-course-id="<?= $course['course_id'] ?? '' ?>"
                                                data-course-code="<?= htmlspecialchars($course['course_code'] ?? '') ?>"
                                                data-course-name="<?= htmlspecialchars($course['course_code'] ?? '') ?> - <?= htmlspecialchars($course['course_name'] ?? '') ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteCourseModal">
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
            
            <!-- Pagination (If needed) -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Showing <?= count($courseData) ?> courses
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm">
                        <!-- Implement pagination if necessary -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- View Course Modal -->
<div class="modal fade" id="viewCourseModal" tabindex="-1" aria-labelledby="viewCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="viewCourseModalLabel">Course Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <h5 id="view_course_name" class="fw-bold"></h5>
                        <p class="text-muted small">Course ID: <span id="view_course_id" class="fw-bold"></span></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span id="view_status_badge" class="badge"></span>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Course Code:</div>
                            <div class="col-md-8 fw-bold" id="view_course_code"></div>
                        </div>                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Program:</div>
                            <div class="col-md-8" id="view_program"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Units:</div>
                            <div class="col-md-8" id="view_units"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Lab Component:</div>
                            <div class="col-md-8" id="view_has_lab"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Description:</div>
                            <div class="col-md-8" id="view_description"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Prerequisites:</div>
                            <div class="col-md-8" id="view_prerequisites"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary edit-from-view" data-bs-toggle="modal" data-bs-target="#editCourseModal">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="addCourseModalLabel">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCourseForm" method="post" action="?page=Manage&subpage=Course&action=add" class="needs-validation" novalidate>
                    <!-- Hidden staff_id input -->
                    <input type="hidden" name="staff_id" value="<?php echo $staffId ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required>
                            <div class="invalid-feedback">Please provide a course code.</div>
                        </div>                        <div class="col-md-6">
                            <label for="units" class="form-label">Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="units" name="units" min="1" max="6" required>
                            <div class="invalid-feedback">Please provide valid units (1-6).</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_name" class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="course_name" name="course_name" required>
                        <div class="invalid-feedback">Please provide a course name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program_id" class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="program_id" name="program_id" required>
                            <option value="">Select Program</option>
                            <?php foreach($programs as $program): ?>
                                <option value="<?= $program['program_id'] ?>">
                                    <?= htmlspecialchars($program['program_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a program.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="has_lab" name="has_lab" value="1">
                            <label class="form-check-label" for="has_lab">Has Laboratory Component</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="prerequisites" class="form-label">Prerequisites</label>
                        <input type="text" class="form-control" id="prerequisites" name="prerequisites">
                        <div class="form-text">Comma-separated list of prerequisite courses, if any.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <div class="invalid-feedback">Please select a status.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addCourseForm" class="btn btn-success">Add Course</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="editCourseModalLabel">Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editCourseForm" method="post" action="?page=Manage&subpage=Course&action=update" class="needs-validation" novalidate>
                    <input type="hidden" id="edit_course_id" name="course_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_course_code" name="course_code" required>
                            <div class="invalid-feedback">Please provide a course code.</div>
                        </div>                        <div class="col-md-6">
                            <label for="edit_units" class="form-label">Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_units" name="units" min="1" max="6" required>
                            <div class="invalid-feedback">Please provide valid units (1-6).</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_name" class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_course_name" name="course_name" required>
                        <div class="invalid-feedback">Please provide a course name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_program_id" class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_program_id" name="program_id" required>
                            <option value="">Select Program</option>
                            <?php foreach($programs as $program): ?>
                                <option value="<?= $program['program_id'] ?>">
                                    <?= htmlspecialchars($program['program_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a program.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit_has_lab" name="has_lab" value="1">
                            <label class="form-check-label" for="edit_has_lab">Has Laboratory Component</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_prerequisites" class="form-label">Prerequisites</label>
                        <input type="text" class="form-control" id="edit_prerequisites" name="prerequisites">
                        <div class="form-text">Comma-separated list of prerequisite courses, if any.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <div class="invalid-feedback">Please select a status.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editCourseForm" class="btn btn-primary">Update Course</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Course Modal -->
<div class="modal fade" id="deleteCourseModal" tabindex="-1" aria-labelledby="deleteCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-secondary-subtle text-dark">
                <h5 class="modal-title" id="deleteCourseModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the course: <span id="delete_course_name" class="fw-bold"></span>?</p>
                <p class="text-danger small"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone.</p>
                <form id="deleteCourseForm" method="get">
                    <input type="hidden" name="page" value="Manage">
                    <input type="hidden" name="subpage" value="Course">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="delete_course_id" name="course_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteCourseForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Course Management -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize any tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        
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
        });        // Initialize filter elements
        const programFilter = document.getElementById('programFilter');
        const statusFilter = document.getElementById('statusFilter');
        const labFilter = document.getElementById('labFilter');
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const searchForm = document.getElementById('courseSearchForm');
        const refreshBtn = document.getElementById('refreshCourseBtn');        // Set up search functionality
        if (searchButton && searchInput && searchForm) {
            // Direct submit on Enter key press
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    console.log('Enter key pressed in search, submitting form with search:', searchInput.value);
                    
                    // Use direct submission
                    searchForm.submit();
                }
            });
            
            // Direct submit on button click
            searchButton.addEventListener('click', function(e) {
                // Don't prevent default - let the form submit naturally
                console.log('Search button clicked, with search term:', searchInput.value);
            });
        }
        
        // Make filter dropdowns auto-submit when changed
        if (programFilter) {
            programFilter.addEventListener('change', function() {
                searchForm.submit();
            });
        }
        
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                searchForm.submit();
            });
        }
        
        if (labFilter) {
            labFilter.addEventListener('change', function() {
                searchForm.submit();
            });
        }
          
        // Set up refresh button functionality
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                // Clear search form fields
                if (programFilter) programFilter.value = '';
                if (statusFilter) statusFilter.value = '';
                if (labFilter) labFilter.value = '';
                const searchInput = document.getElementById('searchInput');
                if (searchInput) searchInput.value = '';
                
                // Submit the form to reload with no filters but maintain the page parameters
                document.getElementById('courseSearchForm').submit();
            });
        }
        
        /**
         * Function to extract JSON from response that may include PHP warnings
         */
        function extractJsonFromResponse(text) {
            console.log('Raw response:', text);
            // Find the JSON part - look for first '{'
            const jsonStart = text.indexOf('{');
            if (jsonStart >= 0) {
                const jsonText = text.substring(jsonStart);
                console.log('Extracted JSON:', jsonText);
                return JSON.parse(jsonText);
            } else {
                throw new Error('No JSON data found in response');
            }
        }

        // Handle view course modal
        const viewCourseModal = document.getElementById('viewCourseModal');
        if (viewCourseModal) {
            const viewButtons = document.querySelectorAll('.view-course');
            console.log('Found view buttons:', viewButtons.length);
            
            Array.from(viewButtons).forEach(button => {
                button.addEventListener('click', function() {
                    console.log('View button clicked!');
                    const courseId = this.getAttribute('data-course-id');
                    
                    if (!courseId) {
                        console.error('No course ID provided for view');
                        return;
                    }
                    
                    console.log('Fetching course ID:', courseId);
                    // Make AJAX call to get course details
                    fetch(`?page=Manage&subpage=Course&action=get&id=${courseId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.text();
                        })
                        .then(text => extractJsonFromResponse(text))
                        .then(course => {
                            console.log('Course data received:', course);
                            
                            // Update modal content with course data
                            document.getElementById('view_course_id').textContent = course.course_id;
                            document.getElementById('view_course_name').textContent = course.course_name;
                            document.getElementById('view_course_code').textContent = course.course_code;
                            document.getElementById('view_program').textContent = course.program_name;
                            document.getElementById('view_units').textContent = course.units;
                            document.getElementById('view_has_lab').textContent = course.has_lab == 1 ? 'Yes' : 'No';
                            document.getElementById('view_description').textContent = course.description || 'Not specified';
                            
                            // Handle prerequisites
                            const prereqs = document.getElementById('view_prerequisites');
                            if (course.prerequisites && course.prerequisites.trim() !== '') {
                                prereqs.textContent = course.prerequisites;
                            } else {
                                prereqs.textContent = 'None';
                            }
                            
                            // Set status badge
                            const statusBadge = document.getElementById('view_status_badge');
                            if (course.status === 'active') {
                                statusBadge.textContent = 'Active';
                                statusBadge.className = 'badge bg-success';
                            } else {
                                statusBadge.textContent = 'Inactive';
                                statusBadge.className = 'badge bg-secondary';
                            }
                            
                            // Configure the Edit from View button
                            const editFromViewBtn = viewCourseModal.querySelector('.edit-from-view');
                            if (editFromViewBtn) {
                                editFromViewBtn.setAttribute('data-course-id', course.course_id);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching course details:', error);
                            // Show error message in modal
                            document.getElementById('view_course_name').textContent = 'Error loading course data';
                            document.getElementById('view_course_code').textContent = '';
                            document.getElementById('view_program').textContent = '';
                            document.getElementById('view_units').textContent = '';
                            document.getElementById('view_has_lab').textContent = '';
                            document.getElementById('view_description').textContent = 'Could not load course data. Please try again.';
                            document.getElementById('view_prerequisites').textContent = '';
                            
                            // Set error status badge
                            const statusBadge = document.getElementById('view_status_badge');
                            statusBadge.textContent = 'Error';
                            statusBadge.className = 'badge bg-danger';
                        });
                });
            });
        }
        
        // Handle edit course modal
        const editCourseModal = document.getElementById('editCourseModal');
        console.log('Edit Course Modal:', editCourseModal);
        
        if (editCourseModal) {
            // Function to load course data for editing
            function loadCourseDataForEdit(courseId) {
                console.log('Loading course for edit, ID:', courseId);
                
                // Make AJAX request to get course data
                fetch(`?page=Manage&subpage=Course&action=get&id=${courseId}`)
                    .then(response => {
                        console.log('Edit response received:', response);
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.text();
                    })
                    .then(text => extractJsonFromResponse(text))
                    .then(course => {
                        console.log('Course data for edit:', course);
                        
                        // Populate form fields
                        document.getElementById('edit_course_id').value = course.course_id;
                        document.getElementById('edit_course_code').value = course.course_code;
                        document.getElementById('edit_course_name').value = course.course_name;
                        document.getElementById('edit_units').value = course.units;
                        document.getElementById('edit_program_id').value = course.program_id;
                        document.getElementById('edit_description').value = course.description || '';
                        document.getElementById('edit_prerequisites').value = course.prerequisites || '';
                        document.getElementById('edit_has_lab').checked = course.has_lab == 1;
                        document.getElementById('edit_status').value = course.status;
                    })
                    .catch(error => {
                        console.error('Error fetching course data for edit:', error);
                        // Clear form fields to prevent partial updates
                        document.getElementById('edit_course_id').value = '';
                        document.getElementById('edit_course_code').value = '';
                        document.getElementById('edit_course_name').value = '';
                        document.getElementById('edit_units').value = '';
                        document.getElementById('edit_program_id').value = '';
                        document.getElementById('edit_description').value = '';
                        document.getElementById('edit_prerequisites').value = '';
                        document.getElementById('edit_has_lab').checked = false;
                        
                        // Display error message
                        alert('Failed to load course data. Please try again.');
                        
                        // Close the modal
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editCourseModal'));
                        if (editModal) editModal.hide();
                    });
            }

            // Handle direct edit button clicks
            const editButtons = document.querySelectorAll('.edit-course');
            console.log('Found edit buttons:', editButtons.length);
            
            Array.from(editButtons).forEach(button => {
                button.addEventListener('click', function() {
                    console.log('Edit button clicked!');
                    const courseId = this.getAttribute('data-course-id');
                    console.log('Edit course ID:', courseId);
                    loadCourseDataForEdit(courseId);
                });
            });
            
            // Handle edit from view button
            const editFromViewBtn = document.querySelector('.edit-from-view');
            if (editFromViewBtn) {
                editFromViewBtn.addEventListener('click', function() {
                    console.log('Edit from view button clicked!');
                    const viewCourseId = document.getElementById('view_course_id').textContent;
                    
                    if (viewCourseId) {
                        console.log('Edit from view course ID:', viewCourseId);
                        loadCourseDataForEdit(viewCourseId);
                        
                        // Close the view modal
                        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewCourseModal'));
                        if (viewModal) viewModal.hide();
                    } else {
                        console.error('Could not find course ID for editing');
                    }
                });
            }
        }
          // Handle delete course modal
        const deleteCourseModal = document.getElementById('deleteCourseModal');
        if (deleteCourseModal) {
            const deleteButtons = document.querySelectorAll('.delete-course');
            console.log('Found delete buttons:', deleteButtons.length);
            
            Array.from(deleteButtons).forEach(button => {
                button.addEventListener('click', function() {
                    console.log('Delete button clicked!');
                    const courseId = this.getAttribute('data-course-id');
                    const courseCode = this.getAttribute('data-course-code') || '';
                    const courseName = this.getAttribute('data-course-name');
                    
                    console.log('Delete course ID:', courseId);
                    document.getElementById('delete_course_id').value = courseId;
                    document.getElementById('delete_course_name').textContent = courseName;
                      // Add a submit handler to the form for debugging
                    const deleteForm = document.getElementById('deleteCourseForm');                    deleteForm.addEventListener('submit', function(event) {
                        // Prevent default to perform validation first
                        event.preventDefault();
                        
                        console.log('Delete form submitted with course_id:', courseId);
                        
                        // Validate course ID
                        if (!courseId || courseId.trim() === '') {
                            console.error('Invalid course ID. Cannot submit delete form.');
                            alert('Error: Cannot delete course. Missing course ID.');
                            return false;
                        }
                        
                        // Use a more direct URL approach
                        const baseUrl = window.location.pathname;
                        const deleteUrl = `${baseUrl}?page=Manage&subpage=Course&action=delete&course_id=${courseId}`;
                        console.log('Redirecting to:', deleteUrl);
                        
                        // Redirect to the delete URL
                        window.location.href = deleteUrl;
                    });
                });
            });
        }
    });
</script>