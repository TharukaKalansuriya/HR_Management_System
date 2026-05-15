<?php
$host = "localhost";
$username = "root";
$password = "1234";
$dbname = "hrms";

try {
    // 1. Connect to MySQL engine
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    
    // 3. Switch to the database
    $pdo->exec("USE `$dbname`");

    // 4. Create the employees table if it doesn't exist (base schema)
    $sql = "CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20),
        position VARCHAR(100) NOT NULL DEFAULT '',
        department VARCHAR(100) NOT NULL DEFAULT '',
        status ENUM('Active', 'Inactive', 'On Leave') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Helper: add a column only if it does not yet exist (safe, cross-version migration)
    function addColIfMissing(PDO $pdo, string $table, string $col, string $definition): void {
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = '$col'"
        )->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
        }
    }

    // 4b. Migrate employees table — add all new profile columns (idempotent)
    // ── Personal Information ──────────────────────────────────────────────────
    addColIfMissing($pdo, 'employees', 'full_name',       "VARCHAR(200) DEFAULT NULL AFTER last_name");
    addColIfMissing($pdo, 'employees', 'nic_no',          "VARCHAR(30) DEFAULT NULL AFTER full_name");
    addColIfMissing($pdo, 'employees', 'date_of_birth',   "DATE DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'gender',          "ENUM('Male','Female','Other') DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'marital_status',  "ENUM('Single','Married','Divorced','Widowed') DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'address',         "TEXT DEFAULT NULL");
    // ── Contact Details ───────────────────────────────────────────────────────
    addColIfMissing($pdo, 'employees', 'phone_secondary', "VARCHAR(20) DEFAULT NULL");
    // ── Emergency Contact ─────────────────────────────────────────────────────
    addColIfMissing($pdo, 'employees', 'emergency_name',         "VARCHAR(150) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'emergency_relationship', "VARCHAR(80) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'emergency_phone',        "VARCHAR(20) DEFAULT NULL");
    // ── Statutory Numbers ─────────────────────────────────────────────────────
    addColIfMissing($pdo, 'employees', 'epf_number', "VARCHAR(50) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'etf_number', "VARCHAR(50) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'tin_number', "VARCHAR(50) DEFAULT NULL");
    // ── Bank Account Details ──────────────────────────────────────────────────
    addColIfMissing($pdo, 'employees', 'bank_acc_no',    "VARCHAR(50) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'bank_name',      "VARCHAR(100) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'bank_branch',    "VARCHAR(100) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'bank_acc_owner', "VARCHAR(150) DEFAULT NULL");
    // ── Employment Details ────────────────────────────────────────────────────
    addColIfMissing($pdo, 'employees', 'employment_type',   "ENUM('Permanent','Contract','Casual','Part-time','Trainee') DEFAULT 'Permanent'");
    addColIfMissing($pdo, 'employees', 'designation',       "VARCHAR(150) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'grade',             "VARCHAR(50) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'date_joined',       "DATE DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'probation_period',  "VARCHAR(50) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'confirmation_date', "DATE DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'branch_location',   "VARCHAR(150) DEFAULT NULL");
    addColIfMissing($pdo, 'employees', 'assigned_shift_id', "INT DEFAULT NULL");

    // ── Authentication (Employee Portal) ──────────────────────────────────────
    addColIfMissing($pdo, 'employees', 'password_hash',     "VARCHAR(255) DEFAULT NULL");

    // 5. Create work_shifts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS work_shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift_name VARCHAR(100) NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 5b. Create public_holidays table for handling holidays and custom weekend flags per date
    $pdo->exec("CREATE TABLE IF NOT EXISTS public_holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_date DATE NOT NULL UNIQUE,
        holiday_name VARCHAR(150) NOT NULL,
        day_type ENUM('Public Holiday', 'Weekend', 'Regular Weekday') DEFAULT 'Public Holiday',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default shifts if empty
    $shiftCount = $pdo->query("SELECT COUNT(*) FROM work_shifts")->fetchColumn();
    if ($shiftCount == 0) {
        $pdo->exec("INSERT INTO work_shifts (shift_name, start_time, end_time) VALUES
            ('Morning Shift', '08:00:00', '16:00:00'),
            ('General Shift', '09:00:00', '17:00:00'),
            ('Evening Shift', '13:00:00', '21:00:00'),
            ('Night Shift', '21:00:00', '05:00:00')");
    }

    // 6. Create attendance table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        check_in TIME DEFAULT NULL,
        check_out TIME DEFAULT NULL,
        status ENUM('Present','Absent','Late','Half Day','On Leave','Holiday','Weekend') DEFAULT 'Present',
        shift_id INT DEFAULT NULL,
        work_hours DECIMAL(4,2) DEFAULT NULL,
        overtime_hours DECIMAL(4,2) DEFAULT 0.00,
        notes TEXT DEFAULT NULL,
        marked_by VARCHAR(100) DEFAULT 'Admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance (employee_id, attendance_date),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )");

    // 7. Create system_users table (HR system accounts)
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        contact_no VARCHAR(20) DEFAULT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('super_admin', 'hr_manager', 'hr_officer') NOT NULL DEFAULT 'hr_officer',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 8. Create page_permissions table (per-user page access control)
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_name VARCHAR(100) NOT NULL,
        is_allowed TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_permission (user_id, page_name),
        FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE
    )");

    // 9. Create job_postings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_postings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        department VARCHAR(100) NOT NULL,
        location VARCHAR(150) NOT NULL,
        job_type ENUM('Full-time','Part-time','Contract','Remote','Internship') DEFAULT 'Full-time',
        description TEXT DEFAULT NULL,
        requirements TEXT DEFAULT NULL,
        status ENUM('Active','Draft','Closed') DEFAULT 'Draft',
        posted_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (posted_by) REFERENCES system_users(id) ON DELETE SET NULL
    )");

    // 10. Create applicants table
    $pdo->exec("CREATE TABLE IF NOT EXISTS applicants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        full_name VARCHAR(200) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        stage ENUM('New','Screening','Interviewing','Offered','Hired','Rejected') DEFAULT 'New',
        rating TINYINT(1) DEFAULT 0,
        notes TEXT DEFAULT NULL,
        applied_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE
    )");

    // 11. Create interviews table
    $pdo->exec("CREATE TABLE IF NOT EXISTS interviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        applicant_id INT NOT NULL,
        job_id INT NOT NULL,
        interview_type ENUM('Initial Screening','Technical','Cultural Fit','HR Round','Final Round') DEFAULT 'Initial Screening',
        interview_mode ENUM('Video','In-Person','Phone') DEFAULT 'Video',
        scheduled_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        interviewer_name VARCHAR(200) DEFAULT NULL,
        location_or_link VARCHAR(300) DEFAULT NULL,
        status ENUM('Scheduled','Completed','Cancelled','Rescheduled') DEFAULT 'Scheduled',
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
        FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES system_users(id) ON DELETE SET NULL
    )");

    // 11.4. Create leaves table
    $pdo->exec("CREATE TABLE IF NOT EXISTS leaves (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        leave_type VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        days INT(11) NOT NULL,
        reason TEXT NOT NULL,
        document_path VARCHAR(255) DEFAULT NULL,
        status ENUM('Pending','HR_Approved','Approved','Rejected') DEFAULT 'Pending',
        admin_remark TEXT DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // 11.5. Create employee_documents table
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        document_type ENUM('Appointment Letter', 'Contract', 'Salary Increment Letter', 'Warning Letter', 'Service Letter', 'Resignation Letter', 'Other') NOT NULL,
        title VARCHAR(200) NOT NULL,
        file_path VARCHAR(300) NOT NULL,
        file_name VARCHAR(200) NOT NULL,
        uploaded_by INT DEFAULT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES system_users(id) ON DELETE SET NULL
    )");

    // 12. Seed default Super Admin if no users exist
    $userCount = $pdo->query("SELECT COUNT(*) FROM system_users")->fetchColumn();
    if ($userCount == 0) {
        $defaultPasswordHash = password_hash('Admin@1234', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO system_users (first_name, last_name, email, contact_no, password_hash, role, is_active)
            VALUES ('Super', 'Admin', 'admin@hrms.com', '0000000000', '$defaultPasswordHash', 'super_admin', 1)");
    }

} catch(PDOException $e) {
    die("<div style='background:#fee2e2; border:1px solid #ef4444; color:#991b1b; padding:15px; margin:20px; border-radius:8px; font-family:sans-serif;'><strong>Database Connection Failed:</strong><br>" . htmlspecialchars($e->getMessage()) . "<br><br><em>Please ensure XAMPP MySQL is running and the credentials are correct.</em></div>");
}
?>
