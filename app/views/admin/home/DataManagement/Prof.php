<?php
// Initialize data arrays
$professors = [];
$departments = [];
$accessLevels = ['regular', 'supervisor', 'administrator'];

// Get professors data
try {
    $search = $_GET['search'] ?? '';
    $department = $_GET['department'] ?? '';
    $page = $_GET['pageNum'] ?? 1;
    $perPage = 10;
    
    $professorsData = get_AllStaff($pdo, $page, $perPage, $search, $department);
    $professors = $professorsData['staff'] ?? [];
    $totalPages = $professorsData['pagination']['total_pages'] ?? 1;
    $currentPage = $professorsData['pagination']['current_page'] ?? 1;
    
    // Get departments for filter
    $departments = get_AllDepartments($pdo);
} catch (Exception $e) {
    error_log("Error loading professors data: " . $e->getMessage());
}
?>

<div>
    <div class="navbg p-3 rounded rounded-3 shadow-sm">
        <div class="d-flex justify-content-between flex-wrap align-items-center">
            <h5 class="mb-0">Professor Management</h5>
            <div>
                <button type="button" class="btn btn-outline-secondary me-2" id="refreshProfessorBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProfessorModal">
                    <i class="bi bi-plus-lg"></i> Add Professor
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Container for system messages -->
    <div id="alertContainer" class="mt-3">
        <?php if (isset($_SESSION['professor_response']['message'])): ?>
            <div class="alert <?= $_SESSION['professor_response']['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['professor_response']['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['professor_response']); ?>
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
            <form id="professorSearchForm" class="row g-3" method="GET" action="">
                <input type="hidden" name="page" value="Manage">
                <input type="hidden" name="subpage" value="Prof">
                
                <div class="col-md-4">
                    <label for="departmentFilter" class="form-label">Department</label>
                    <select class="form-select form-select-sm" id="departmentFilter" name="department">
                        <option value="">All Departments</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= isset($_GET['department']) && $_GET['department'] == $dept ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-8">
                    <label for="searchInput" class="form-label">Search</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm" id="searchInput" name="search" 
                               placeholder="Search by ID, name, email, or position..." 
                               value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        <button type="submit" id="searchButton" class="btn btn-outline-success">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                    <div class="form-text">
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <span>Searching for: <strong><?= htmlspecialchars($_GET['search']) ?></strong></span>
                            <a href="?page=Manage&subpage=Prof" class="text-decoration-none ms-2">(Clear search)</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Professor Data Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Access Level</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($professors)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-search fs-1 text-muted mb-2"></i>
                                        <p class="mb-0">No professors found.</p>
                                        <p class="text-muted small">Try adjusting your search criteria or add a new professor.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($professors as $professor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($professor['staff_id'] ?? '') ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars(($professor['first_name'] ?? '') . ' ' . ($professor['last_name'] ?? '')) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($professor['login_id'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($professor['department'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($professor['position'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($professor['email'] ?? '') ?></td>                                    <td>
                                        <span class="<?= $professor['access_level'] ?>">
                                            <?= ucfirst(htmlspecialchars($professor['access_level'] ?? 'regular')) ?>
                                        </span>
                                    </td><td>
                                        <span class="badge <?= ($professor['user_status'] ?? '') == 'active' ? 'bg-success' : (($professor['user_status'] ?? '') == 'suspended' ? 'bg-warning' : 'bg-danger') ?>">
                                            <?= ucfirst(htmlspecialchars($professor['user_status'] ?? '')) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary view-professor-btn" 
                                                    data-bs-toggle="modal" data-bs-target="#viewProfessorModal" 
                                                    data-id="<?= $professor['staff_id'] ?? '' ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary edit-professor-btn" 
                                                    data-bs-toggle="modal" data-bs-target="#editProfessorModal" 
                                                    data-id="<?= $professor['staff_id'] ?? '' ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-professor-btn" 
                                                    data-bs-toggle="modal" data-bs-target="#deleteProfessorModal" 
                                                    data-id="<?= $professor['staff_id'] ?? '' ?>"
                                                    data-name="<?= htmlspecialchars(($professor['first_name'] ?? '') . ' ' . ($professor['last_name'] ?? '')) ?>">
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
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Professor pagination" class="mt-3">
                    <ul class="pagination pagination-sm justify-content-center">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=Manage&subpage=Prof&pageNum=<?= $currentPage - 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&department=<?= urlencode($_GET['department'] ?? '') ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="?page=Manage&subpage=Prof&pageNum=<?= $i ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&department=<?= urlencode($_GET['department'] ?? '') ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=Manage&subpage=Prof&pageNum=<?= $currentPage + 1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&department=<?= urlencode($_GET['department'] ?? '') ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Professor Modal -->
<div class="modal fade" id="addProfessorModal" tabindex="-1" aria-labelledby="addProfessorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary-subtle text-dark">
                <h5 class="modal-title" id="addProfessorModalLabel">Add New Professor</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addProfessorForm" method="post" action="">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="loginId" class="form-label">Login ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="loginId" name="login_id" 
                                   placeholder="SF-00001" pattern="[A-Z0-9]{2}-\d{5}" required>
                            <div class="form-text">Format: AD-00001, SF-00001</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="department" name="department" required>
                        </div>
                        <div class="col-md-6">
                            <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="position" name="position" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phoneNumber" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phoneNumber" name="phone_number">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="accessLevel" class="form-label">Access Level</label>
                            <select class="form-select" id="accessLevel" name="access_level">
                                <option value="regular">Regular</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="administrator">Administrator</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Professor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Professor Modal -->
<div class="modal fade" id="editProfessorModal" tabindex="-1" aria-labelledby="editProfessorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary-subtle text-dark">
                <h5 class="modal-title" id="editProfessorModalLabel">Edit Professor</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editProfessorForm" method="post" action="">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="staff_id" id="editStaffId">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editLastName" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editLoginId" class="form-label">Login ID</label>
                            <input type="text" class="form-control" id="editLoginId" name="login_id" 
                                   pattern="[A-Z0-9]{2}-\d{5}" readonly>
                            <div class="form-text">Login ID cannot be changed</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="editPassword" name="password">
                            <div class="form-text">Leave blank to keep current password</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDepartment" class="form-label">Department <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editDepartment" name="department" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editPosition" class="form-label">Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editPosition" name="position" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editPhoneNumber" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="editPhoneNumber" name="phone_number">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editAccessLevel" class="form-label">Access Level</label>
                            <select class="form-select" id="editAccessLevel" name="access_level">
                                <option value="regular">Regular</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="administrator">Administrator</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Professor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Professor Modal -->
<div class="modal fade" id="viewProfessorModal" tabindex="-1" aria-labelledby="viewProfessorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary-subtle text-dark">
                <h5 class="modal-title" id="viewProfessorModalLabel">Professor Details</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="professorDetails">
                    <!-- Professor details will be loaded here via JavaScript -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Professor Modal -->
<div class="modal fade" id="deleteProfessorModal" tabindex="-1" aria-labelledby="deleteProfessorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary-subtle text-dark">
                <h5 class="modal-title" id="deleteProfessorModalLabel">Delete Professor</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this professor?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. The professor will be permanently removed from the system.
                </div>
                <p><strong>Professor:</strong> <span id="deleteProfessorName"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Professor</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh button functionality
    document.getElementById('refreshProfessorBtn').addEventListener('click', function() {
        window.location.reload();
    });    // Edit professor button functionality
    document.querySelectorAll('.edit-professor-btn').forEach(button => {
        button.addEventListener('click', function() {
            const staffId = this.getAttribute('data-id');
            
            // Fetch professor data via AJAX
            fetch(`dashboard.php?page=Manage&subpage=Prof&action=get&id=${staffId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.staff_id) {
                        document.getElementById('editStaffId').value = data.staff_id;
                        document.getElementById('editFirstName').value = data.first_name;
                        document.getElementById('editLastName').value = data.last_name;
                        document.getElementById('editLoginId').value = data.login_id;
                        document.getElementById('editDepartment').value = data.department;
                        document.getElementById('editPosition').value = data.position;
                        document.getElementById('editEmail').value = data.email;
                        document.getElementById('editPhoneNumber').value = data.phone_number || '';
                        document.getElementById('editAccessLevel').value = data.access_level;
                    } else {
                        console.error('Invalid response data:', data);
                        alert('Error loading professor data for editing.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching professor data:', error);
                    alert('Error loading professor data for editing.');
                });
        });
    });    // View professor button functionality
    document.querySelectorAll('.view-professor-btn').forEach(button => {
        button.addEventListener('click', function() {
            const staffId = this.getAttribute('data-id');
            
            // Fetch professor data via AJAX
            fetch(`dashboard.php?page=Manage&subpage=Prof&action=get&id=${staffId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.staff_id) {
                        const detailsHtml = `
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Staff ID:</strong> ${data.staff_id}<br>
                                    <strong>Name:</strong> ${data.first_name} ${data.last_name}<br>
                                    <strong>Login ID:</strong> ${data.login_id}<br>
                                    <strong>Department:</strong> ${data.department}<br>
                                </div>                                <div class="col-md-6">
                                    <strong>Position:</strong> ${data.position}<br>
                                    <strong>Email:</strong> ${data.email}<br>
                                    <strong>Phone:</strong> ${data.phone_number || 'N/A'}<br>
                                    <strong>Access Level:</strong> <span class="badge ${data.access_level === 'administrator' ? 'bg-danger' : (data.access_level === 'supervisor' ? 'bg-warning' : 'bg-info')}">${data.access_level}</span><br>
                                </div>
                            </div>
                        `;
                        document.getElementById('professorDetails').innerHTML = detailsHtml;
                    } else {
                        document.getElementById('professorDetails').innerHTML = '<div class="alert alert-danger">Professor not found.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching professor data:', error);
                    document.getElementById('professorDetails').innerHTML = '<div class="alert alert-danger">Error loading professor details.</div>';
                });
        });
    });
    
    // Delete professor button functionality
    document.querySelectorAll('.delete-professor-btn').forEach(button => {
        button.addEventListener('click', function() {
            const staffId = this.getAttribute('data-id');
            const professorName = this.getAttribute('data-name');
            
            document.getElementById('deleteProfessorName').textContent = professorName;
            document.getElementById('confirmDeleteBtn').href = `dashboard.php?page=Manage&subpage=Prof&action=delete&id=${staffId}`;
        });
    });
});
</script>