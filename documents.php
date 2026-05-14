<?php
require_once 'auth_check.php';
$required_page = 'documents.php';
require_once 'db_config.php';

$page_title = "Document Management";

// Handle File Upload
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $employee_id = (int)$_POST['employee_id'];
    $document_type = $_POST['document_type'];
    $title = trim($_POST['title']);
    $file = $_FILES['document_file'];

    if (empty($employee_id) || empty($document_type) || empty($title) || empty($file['name'])) {
        $error = "All fields are required.";
    } else {
        $target_dir = "uploads/documents/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $file['name']);
        $target_file = $target_dir . $safe_file_name;

        // Simple validation: Allow PDF, DOC, DOCX, JPG, PNG
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            $error = "Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG are allowed.";
        } elseif ($file['size'] > 5000000) { // 5MB limit
            $error = "File is too large. Max size is 5MB.";
        } else {
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_documents (employee_id, document_type, title, file_path, file_name, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$employee_id, $document_type, $title, $target_file, $file['name'], $_SESSION['user_id']]);
                    $msg = "Document uploaded successfully.";
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Failed to upload file.";
            }
        }
    }
}

// Filters
$filter_emp = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;
$filter_type = $_GET['type'] ?? '';

// Fetch Documents
$sql = "SELECT d.*, e.first_name, e.last_name, e.department, u.first_name as u_first, u.last_name as u_last 
        FROM employee_documents d
        JOIN employees e ON d.employee_id = e.id
        LEFT JOIN system_users u ON d.uploaded_by = u.id
        WHERE 1=1";
$params = [];

if ($filter_emp > 0) {
    $sql .= " AND d.employee_id = ?";
    $params[] = $filter_emp;
}
if (!empty($filter_type)) {
    $sql .= " AND d.document_type = ?";
    $params[] = $filter_type;
}

$sql .= " ORDER BY d.uploaded_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Employees for dropdown
$employees = $pdo->query("SELECT id, first_name, last_name, department FROM employees WHERE status='Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

$doc_types = ['Appointment Letter', 'Contract', 'Salary Increment Letter', 'Warning Letter', 'Service Letter', 'Resignation Letter', 'Other'];

include 'header.php';
include 'sidebar.php';
?>

<main class="flex-1 flex flex-col h-full bg-gray-50/50 relative">
    <?php include 'topbar.php'; ?>

    <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 custom-scrollbar">
        
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Document Management</h1>
                <p class="text-sm text-gray-500 mt-1 text-premium">Centralized repository for all employee official documentation.</p>
            </div>
            <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" 
                    class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-6 rounded-xl shadow-lg shadow-brand-200 transition-all flex items-center gap-2">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload Document
            </button>
        </div>

        <?php if($msg): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded-r shadow-sm">
                <p class="text-sm font-medium"><?php echo $msg; ?></p>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r shadow-sm">
                <p class="text-sm font-medium"><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Search Employee</label>
                    <select name="emp_id" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                        <option value="0">All Employees</option>
                        <?php foreach($employees as $e): ?>
                            <option value="<?php echo $e['id']; ?>" <?php echo $filter_emp == $e['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' (' . $e['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Document Type</label>
                    <select name="type" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                        <option value="">All Types</option>
                        <?php foreach($doc_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $filter_type == $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 px-6 rounded-xl transition-all">
                    <i class="fa-solid fa-filter mr-1"></i> Filter
                </button>
                <?php if($filter_emp > 0 || !empty($filter_type)): ?>
                    <a href="documents.php" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-semibold py-2.5 px-6 rounded-xl transition-all">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Documents Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-400 uppercase bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Document Title</th>
                            <th class="px-6 py-4 font-semibold">Employee</th>
                            <th class="px-6 py-4 font-semibold text-center">Category</th>
                            <th class="px-6 py-4 font-semibold text-center">Uploaded By</th>
                            <th class="px-6 py-4 font-semibold text-center">Date</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(count($documents) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                    <div class="flex flex-col items-center">
                                        <i class="fa-regular fa-folder-open text-4xl mb-3 opacity-20"></i>
                                        <p>No documents found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach($documents as $doc): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <?php 
                                        $ext = pathinfo($doc['file_name'], PATHINFO_EXTENSION);
                                        $icon = 'fa-file-lines text-gray-400';
                                        if($ext == 'pdf') $icon = 'fa-file-pdf text-red-500';
                                        if(in_array($ext, ['doc','docx'])) $icon = 'fa-file-word text-blue-500';
                                        if(in_array($ext, ['jpg','jpeg','png'])) $icon = 'fa-file-image text-emerald-500';
                                        ?>
                                        <i class="fa-solid <?php echo $icon; ?> text-xl mr-3"></i>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($doc['title']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($doc['file_name']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($doc['department']); ?></p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php 
                                    $badge_color = "bg-gray-100 text-gray-600";
                                    if($doc['document_type'] == 'Warning Letter') $badge_color = "bg-red-100 text-red-600";
                                    if($doc['document_type'] == 'Appointment Letter') $badge_color = "bg-emerald-100 text-emerald-600";
                                    if($doc['document_type'] == 'Contract') $badge_color = "bg-blue-100 text-blue-600";
                                    if($doc['document_type'] == 'Salary Increment Letter') $badge_color = "bg-purple-100 text-purple-600";
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $badge_color; ?>">
                                        <?php echo $doc['document_type']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-500 text-xs">
                                    <?php echo htmlspecialchars($doc['u_first'] . ' ' . $doc['u_last']); ?>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-500 text-xs">
                                    <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?php echo $doc['file_path']; ?>" target="_blank" 
                                           class="p-2 text-brand-600 hover:bg-brand-50 rounded-lg transition-all" title="View Document">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="<?php echo $doc['file_path']; ?>" download="<?php echo $doc['file_name']; ?>" 
                                           class="p-2 text-brand-600 hover:bg-brand-50 rounded-lg transition-all" title="Download">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <a href="document_delete.php?id=<?php echo $doc['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this document?')"
                                           class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Upload Modal -->
<div id="uploadModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between bg-white">
            <h2 class="text-xl font-bold text-gray-800">Upload New Document</h2>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-8 space-y-6">
            <input type="hidden" name="action" value="upload">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Employee</label>
                <select name="employee_id" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                    <option value="">Select Employee</option>
                    <?php foreach($employees as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Document Category</label>
                <select name="document_type" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                    <?php foreach($doc_types as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Document Title</label>
                <input type="text" name="title" required placeholder="e.g. Appointment Letter - John Doe" 
                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select File</label>
                <div class="relative group">
                    <input type="file" name="document_file" required id="fileInput" class="hidden">
                    <label for="fileInput" class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-200 rounded-2xl hover:border-brand-500 hover:bg-brand-50/30 cursor-pointer transition-all">
                        <i class="fa-solid fa-file-arrow-up text-3xl text-gray-300 group-hover:text-brand-500 mb-2"></i>
                        <span class="text-sm text-gray-500 group-hover:text-brand-800" id="fileNameDisp">Choose file or drag & drop</span>
                        <span class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, JPG, PNG (Max 5MB)</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" 
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-xl transition-all">
                    Cancel
                </button>
                <button type="submit" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg shadow-brand-200 transition-all">
                    Start Upload
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const fileInput = document.getElementById('fileInput');
    const fileNameDisp = document.getElementById('fileNameDisp');
    
    fileInput.addEventListener('change', function() {
        if(this.files && this.files.length > 0) {
            fileNameDisp.innerText = this.files[0].name;
            fileNameDisp.classList.add('text-brand-600', 'font-bold');
        } else {
            fileNameDisp.innerText = "Choose file or drag & drop";
            fileNameDisp.classList.remove('text-brand-600', 'font-bold');
        }
    });
</script>

</body>
</html>
