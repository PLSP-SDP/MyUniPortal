<?php
// First check if the requested page exists
include "app/main_proc.php";
// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    redirectBasedOnRole(getUserRole());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLSP - MyUniPortal</title>
    <link rel="stylesheet" href="./src/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg">

    <nav class="navbar navbar-expand-lg navbg shadow p-3 mx-3 mt-2 rounded-2 fixed-top">
        <div class="container-fluid shadows d-flex justify-content-between">

            <a href="#Home" class="navbar-brand">
                <div class="d-flex align-items-center">
                    <img src="./src/img/PLSP.png" alt="PLSP Enrollment System" width="40">
                    <div class="ms-2">
                        <p class="title">PLSP</p>
                        <h6 class="mb-0">MyUniPortal</h6>
                    </div>
                </div>
            </a>
            <!--Branding--> <!--Toggler for navigation bar in mobile view-->
            <div class="text-end">
                <p class="text-muted mb-0">Philippine Standard Time</p>
                <h6 id="time" class="text-muted mb-0"></h6>
                <!--Time will be displayed here-->
            </div>

        </div>
    </nav>

    <div class="container-fluid d-flex justify-content-end align-items-center vh-100 pt-5">
        <div
            class="text-white d-none d-xl-flex flex-column align-items-start justify-content-center text-start w-50 me-auto ms-3">
            <h1>Welcome to <b><u>MyUniPortal</u></b></h1>
            <p class="fst-italic fs-5 mb-4">"Your Gateway to a Seamless Academic Journey"</p>
            <p>MyUniPortal is an all-in-one student information and enrollment system designed to simplify and enhance
                the academic experience. Whether you’re a student managing your courses, grades, and payments, or an
                administrator overseeing enrollment and academic records, MyUniPortal provides a secure, user-friendly
                platform tailored to your needs.
                <br><br>
                With MyUniPortal, students can easily enroll in courses, view class schedules, track their academic
                progress, manage personal profiles, and stay updated with important announcements — all in one place.
                For administrators and staff, the system offers powerful tools for student management, course offerings,
                grade entry, billing, reporting, and more.
            </p>
        </div>

        <div class="navbg p-5 rounded-4  mx-sm-auto shadow">

            <h2 class="text-start mt-5">Login</h2>
            <p class="mb-5">For information on how to login in visit this <a href="http://">guide</a></p>

            <?php
            // Display error message if any
            if (isset($_SESSION['login_error'])) {
                echo '<div class="alert alert-danger">' . $_SESSION['login_error'] . '</div>';
                unset($_SESSION['login_error']);
            }
            ?>

            <form class="my-5" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="floatingInput" name="login_id" placeholder="XX-00000"
                        required>
                    <label for="floatingInput">User ID</label>
                </div>
                <div class="form-floating">
                    <input type="password" class="form-control" id="floatingPassword" name="password"
                        placeholder="Password" required>
                    <label for="floatingPassword">Password</label>
                </div>                <div class="d-flex gap-2 mt-5">
                    <button type="submit" name="login_action" class="btn btn-success flex-fill">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                    <a href="#" class="btn btn-outline-dark flex-fill" data-bs-toggle="modal" data-bs-target="#enrollModal">
                        <i class="bi bi-person-plus me-2"></i>Enroll
                    </a>
                </div>
                <p class="mt-2 small text-muted">By signing in you agree to the T&C of this website and University.</p>
            </form>        </div>
    </div>

    <!-- Enrollment Modal -->
    <div class="modal fade" id="enrollModal" tabindex="-1" aria-labelledby="enrollModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="enrollModalLabel">
                        <i class="bi bi-person-plus me-2"></i>Student Enrollment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>New Student Registration</strong><br>
                        Please fill out the form below to create your student account and begin the enrollment process.
                    </div>
                    
                    <form id="enrollmentForm" method="POST" action="app/Controllers/enrollment.backend.php">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone_number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Street Address"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="state" name="state">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="program" class="form-label">Desired Program <span class="text-danger">*</span></label>
                                    <select class="form-select" id="program" name="program_id" required>
                                        <option value="">Select a Program</option>
                                        <!-- Options will be populated dynamically -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="year_level" class="form-label">Year Level <span class="text-danger">*</span></label>
                                    <select class="form-select" id="year_level" name="year_level" required>
                                        <option value="">Select Year Level</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                        <option value="5">5th Year</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#" target="_blank">Privacy Policy</a> <span class="text-danger">*</span>
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" form="enrollmentForm" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Submit Enrollment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq"
        crossorigin="anonymous"></script>
    <script src="./src/js/app.js"></script>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });        // Load available programs when modal opens
        document.getElementById('enrollModal').addEventListener('show.bs.modal', function() {
            fetch('app/Controllers/enrollment.backend.php?action=get_programs')
                .then(response => response.json())
                .then(data => {
                    const programSelect = document.getElementById('program');
                    programSelect.innerHTML = '<option value="">Select a Program</option>';
                    
                    if (data.success && data.programs) {
                        data.programs.forEach(program => {
                            const option = document.createElement('option');
                            option.value = program.program_id;
                            option.textContent = `${program.program_name} (${program.program_code})`;
                            programSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading programs:', error);
                });
        });
        
        // Handle form submission
        document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'submit_enrollment');
            
            // Show loading state
            const submitBtn = document.querySelector('button[form="enrollmentForm"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
            submitBtn.disabled = true;
            
            fetch('app/Controllers/enrollment.backend.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const modal = bootstrap.Modal.getInstance(document.getElementById('enrollModal'));
                    modal.hide();
                    
                    // Show success alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                    alertDiv.style.zIndex = '9999';
                    alertDiv.innerHTML = `
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Enrollment Successful!</strong> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alertDiv);
                    
                    // Remove alert after 5 seconds
                    setTimeout(() => {
                        if (alertDiv.parentNode) {
                            alertDiv.parentNode.removeChild(alertDiv);
                        }
                    }, 5000);
                    
                    // Reset form
                    this.reset();
                } else {
                    // Show error message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Enrollment Failed!</strong> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    
                    // Insert at top of modal body
                    const modalBody = document.querySelector('#enrollModal .modal-body');
                    modalBody.insertBefore(alertDiv, modalBody.firstChild);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show generic error message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Error!</strong> An unexpected error occurred. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const modalBody = document.querySelector('#enrollModal .modal-body');
                modalBody.insertBefore(alertDiv, modalBody.firstChild);
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    </script>
</body>

</html>