-- Database Schema for Student Information & Enrollment System

-- Users table (base user authentication)
CREATE TABLE users (
    user_id VARCHAR(10) PRIMARY KEY,  -- Format: US-00000
    login_id VARCHAR(8) NOT NULL UNIQUE,  -- Format: XX-00000
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);

-- Student profiles
CREATE TABLE students (
    student_id VARCHAR(10) PRIMARY KEY,  -- Format: ST-00000
    user_id VARCHAR(10) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    postal_code VARCHAR(20),
    country VARCHAR(50) DEFAULT 'Philippines',
    phone_number VARCHAR(20),
    email VARCHAR(100),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    program_id VARCHAR(10),
    year_level INT,
    enrollment_date DATE,
    academic_advisor_id VARCHAR(10) NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Admin/Staff profiles
CREATE TABLE staff (
    staff_id VARCHAR(10) PRIMARY KEY,  -- Format: SF-00000
    user_id VARCHAR(10) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    department VARCHAR(100),
    position VARCHAR(100),
    phone_number VARCHAR(20),
    email VARCHAR(100),
    access_level ENUM('regular', 'supervisor', 'administrator') DEFAULT 'regular',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Academic programs
CREATE TABLE programs (
    program_id VARCHAR(10) PRIMARY KEY,  -- Format: PG-00000
    program_code VARCHAR(20) NOT NULL UNIQUE,
    program_name VARCHAR(100) NOT NULL,
    description TEXT,
    department VARCHAR(100),
    total_units INT,
    duration_years DECIMAL(3,1),
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Courses/Subjects
CREATE TABLE courses (
    course_id VARCHAR(10) PRIMARY KEY,  -- Format: CR-00000
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    units INT NOT NULL,
    program_id VARCHAR(10),
    has_lab BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (program_id) REFERENCES programs(program_id)
);

-- Course prerequisites
CREATE TABLE course_prerequisites (
    prerequisite_id VARCHAR(10) PRIMARY KEY,  -- Format: PR-00000
    course_id VARCHAR(10) NOT NULL,
    prerequisite_course_id VARCHAR(10) NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (prerequisite_course_id) REFERENCES courses(course_id),
    UNIQUE (course_id, prerequisite_course_id)
);

-- Academic terms
CREATE TABLE terms (
    term_id VARCHAR(10) PRIMARY KEY,  -- Format: TM-00000
    term_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    enrollment_start DATE NOT NULL,
    enrollment_end DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    status ENUM('upcoming', 'ongoing', 'completed') NOT NULL
);

-- Classes (course offerings)
CREATE TABLE classes (
    class_id VARCHAR(10) PRIMARY KEY,  -- Format: CL-00000
    course_id VARCHAR(10) NOT NULL,
    term_id VARCHAR(10) NOT NULL,
    section VARCHAR(20) NOT NULL,
    instructor_id VARCHAR(10) NULL,
    room VARCHAR(50),
    days_of_week VARCHAR(20), -- e.g., "MWF" for Monday, Wednesday, Friday
    start_time TIME,
    end_time TIME,
    max_students INT DEFAULT 40,
    status ENUM('open', 'closed', 'cancelled') DEFAULT 'open',
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (term_id) REFERENCES terms(term_id),
    FOREIGN KEY (instructor_id) REFERENCES staff(staff_id),
    UNIQUE (course_id, term_id, section)
);

-- Enrollment Records
CREATE TABLE enrollments (
    enrollment_id VARCHAR(10) PRIMARY KEY,  -- Format: EN-00000
    student_id VARCHAR(10) NOT NULL,
    term_id VARCHAR(10) NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by VARCHAR(10) NULL,
    approved_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (term_id) REFERENCES terms(term_id),
    FOREIGN KEY (approved_by) REFERENCES staff(staff_id),
    UNIQUE (student_id, term_id)
);

-- Enrollment Details (classes enrolled in)
CREATE TABLE enrollment_details (
    detail_id VARCHAR(10) PRIMARY KEY,  -- Format: ED-00000
    enrollment_id VARCHAR(10) NOT NULL,
    class_id VARCHAR(10) NOT NULL,
    status ENUM('enrolled', 'dropped', 'waitlisted', 'completed') DEFAULT 'enrolled',
    grade VARCHAR(5) NULL, -- A, A-, B+, B, etc.
    numeric_grade DECIMAL(4,2) NULL, -- 4.0, 3.7, 3.3, etc.
    remarks TEXT,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modified TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id),
    FOREIGN KEY (class_id) REFERENCES classes(class_id),
    UNIQUE (enrollment_id, class_id)
);

-- Grade history (for tracking grade changes)
CREATE TABLE grade_history (
    history_id VARCHAR(10) PRIMARY KEY,  -- Format: GH-00000
    detail_id VARCHAR(10) NOT NULL,
    previous_grade VARCHAR(5),
    previous_numeric_grade DECIMAL(4,2),
    new_grade VARCHAR(5),
    new_numeric_grade DECIMAL(4,2),
    changed_by VARCHAR(10) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    FOREIGN KEY (detail_id) REFERENCES enrollment_details(detail_id),
    FOREIGN KEY (changed_by) REFERENCES staff(staff_id)
);

-- Student Schedules (derived from enrollment details and classes)
CREATE TABLE student_schedules (
    schedule_id VARCHAR(10) PRIMARY KEY,  -- Format: SC-00000
    student_id VARCHAR(10) NOT NULL,
    term_id VARCHAR(10) NOT NULL,
    class_id VARCHAR(10) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (term_id) REFERENCES terms(term_id),
    FOREIGN KEY (class_id) REFERENCES classes(class_id),
    UNIQUE (student_id, class_id)
);

-- Billing records
CREATE TABLE billings (
    billing_id VARCHAR(10) PRIMARY KEY,  -- Format: BL-00000
    student_id VARCHAR(10) NOT NULL,
    term_id VARCHAR(10) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(10) NOT NULL,
    status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (term_id) REFERENCES terms(term_id),
    FOREIGN KEY (created_by) REFERENCES staff(staff_id)
);

-- Payment records
CREATE TABLE payments (
    payment_id VARCHAR(10) PRIMARY KEY,  -- Format: PM-00000
    billing_id VARCHAR(10) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'online') NOT NULL,
    reference_number VARCHAR(50),
    notes TEXT,
    processed_by VARCHAR(10) NULL,
    FOREIGN KEY (billing_id) REFERENCES billings(billing_id),
    FOREIGN KEY (processed_by) REFERENCES staff(staff_id)
);

-- Announcements
CREATE TABLE announcements (
    announcement_id VARCHAR(10) PRIMARY KEY,  -- Format: AN-00000
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_by VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    start_date DATE,
    end_date DATE,
    is_public BOOLEAN DEFAULT TRUE,
    target_role ENUM('all', 'students', 'staff', 'admins') DEFAULT 'all',
    FOREIGN KEY (created_by) REFERENCES staff(staff_id)
);

-- Notifications
CREATE TABLE notifications (
    notification_id VARCHAR(10) PRIMARY KEY,  -- Format: NT-00000
    user_id VARCHAR(10) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    related_entity VARCHAR(50) NULL, -- e.g., "enrollment", "payment", etc.
    related_id VARCHAR(10) NULL, -- ID of the related entity
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Reports (for storing generated reports)
CREATE TABLE reports (
    report_id VARCHAR(10) PRIMARY KEY,  -- Format: RP-00000
    report_name VARCHAR(100) NOT NULL,
    report_type ENUM('enrollment', 'academic', 'billing', 'general') NOT NULL,
    generated_by VARCHAR(10) NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    parameters TEXT, -- JSON string of parameters used to generate the report
    file_path VARCHAR(255),
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    FOREIGN KEY (generated_by) REFERENCES staff(staff_id)
);

-- Academic advisors assignment history
CREATE TABLE advisor_assignments (
    assignment_id VARCHAR(10) PRIMARY KEY,  -- Format: AA-00000
    student_id VARCHAR(10) NOT NULL,
    advisor_id VARCHAR(10) NOT NULL,
    assigned_date DATE NOT NULL,
    end_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    assigned_by VARCHAR(10) NOT NULL,
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (advisor_id) REFERENCES staff(staff_id),
    FOREIGN KEY (assigned_by) REFERENCES staff(staff_id)
);

-- System activity logs
CREATE TABLE activity_logs (
    log_id VARCHAR(10) PRIMARY KEY,  -- Format: LG-00000
    user_id VARCHAR(10) NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert initial admin user for system setup
INSERT INTO users (user_id, login_id, password, role, status) 
VALUES ('US-00001', 'AD-00001', '$2y$10$8zUkhufzH5TT8RpgXGQDwuKYjlVrmvK4KV2Q5K4CnQ.5G5gVfX1VG', 'admin', 'active');
-- Default password is 'admin123' (hashed with bcrypt)

INSERT INTO staff (staff_id, user_id, first_name, last_name, department, position, access_level) 
VALUES ('SF-00001', 'US-00001', 'System', 'Administrator', 'IT Department', 'System Administrator', 'administrator');

-- Insert sample programs
INSERT INTO programs (program_id, program_code, program_name, description, department, total_units, duration_years, status) VALUES
('PG-00001', 'BSIT', 'Bachelor of Science in Information Technology', 'A four-year degree program that covers computer systems, software development, and information management.', 'College of Computer Studies', 144, 4.0, 'active'),
('PG-00002', 'BSCS', 'Bachelor of Science in Computer Science', 'A comprehensive program focusing on theoretical foundations of computing and practical problem-solving skills.', 'College of Computer Studies', 144, 4.0, 'active'),
('PG-00003', 'BSN', 'Bachelor of Science in Nursing', 'A professional degree program that prepares students for a career in nursing and healthcare.', 'College of Nursing', 140, 4.0, 'active'),
('PG-00004', 'BSBA', 'Bachelor of Science in Business Administration', 'A comprehensive business program covering management, marketing, finance, and operations.', 'College of Business', 132, 4.0, 'active'),
('PG-00005', 'BSED', 'Bachelor of Science in Education', 'A teacher education program that prepares students for careers in elementary and secondary education.', 'College of Education', 140, 4.0, 'active'),
('PG-00006', 'BSPsych', 'Bachelor of Science in Psychology', 'A program that studies human behavior, mental processes, and psychological principles.', 'College of Liberal Arts', 128, 4.0, 'active'),
('PG-00007', 'BSCE', 'Bachelor of Science in Civil Engineering', 'An engineering program focused on design, construction, and maintenance of infrastructure.', 'College of Engineering', 160, 5.0, 'active'),
('PG-00008', 'BSEE', 'Bachelor of Science in Electrical Engineering', 'An engineering program covering electrical systems, electronics, and power systems.', 'College of Engineering', 160, 5.0, 'active');