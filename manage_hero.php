<?php
// --- 1. ENABLE ERROR REPORTING FOR DEBUGGING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'session_check.php';
require_once 'audit_log.php'; 
include 'db_connect.php';

// --- 2. CHECK CONNECTION & CREATE TABLE IF MISSING ---
if (!isset($conn) || $conn->connect_error) {
    die("❌ Connection failed: " . ($conn->connect_error ?? "Database variable missing"));
}

// Auto-fix table structure
$tableCheck = $conn->query("SHOW TABLES LIKE 'hero_slides'");
if ($tableCheck->num_rows == 0) {
    $sql = "CREATE TABLE hero_slides (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        file_path VARCHAR(255) NOT NULL,
        type ENUM('image', 'video') NOT NULL DEFAULT 'image',
        heading VARCHAR(255),
        subtext TEXT,
        button_text VARCHAR(50) DEFAULT 'View Menu',
        button_link VARCHAR(255) DEFAULT 'product.php',
        sort_order INT(11) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1
    )";
    if(!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// --- 3. HANDLE ACTIONS ---
$successMessage = "";
$errorMessage = "";

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optional: Delete file from server
    $fileQ = $conn->query("SELECT file_path FROM hero_slides WHERE id=$id");
    if($fileQ && $row=$fileQ->fetch_assoc()){
        if(file_exists($row['file_path'])) unlink($row['file_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM hero_slides WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $successMessage = "✅ Slide deleted successfully.";
        if(function_exists('logAdminAction')) {
            logAdminAction($conn, $_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Admin', 'delete_slide', "Deleted ID: $id", 'hero_slides', $id);
        }
    }
    $stmt->close();
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_slide'])) {
    $slide_id = $_POST['slide_id'] ?? null;
    $heading = $_POST['heading'] ?? '';
    $subtext = $_POST['subtext'] ?? '';
    $btn_text = $_POST['button_text'] ?? '';
    $btn_link = $_POST['button_link'] ?? '';
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    
    $file_path = $_POST['current_file'] ?? '';
    $type = $_POST['current_type'] ?? 'image'; 

    // File Upload Logic
    if (isset($_FILES['slide_file']) && $_FILES['slide_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Check for upload errors
        if ($_FILES['slide_file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['slide_file']['error'];
            $errorMessage = "❌ Upload Error Code: $errCode. (File might be too large for server settings)";
        } else {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES["slide_file"]["name"]));
            $target_file = $target_dir . $file_name;
            $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
            $allowed_imgs = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_vids = ['mp4', 'webm', 'ogg'];
            
            if (in_array($ext, $allowed_imgs)) { $type = 'image'; }
            elseif (in_array($ext, $allowed_vids)) { $type = 'video'; }
            else { $errorMessage = "❌ Invalid file type. Only JPG, PNG, MP4 allowed."; }

            if (empty($errorMessage)) {
                if (move_uploaded_file($_FILES["slide_file"]["tmp_name"], $target_file)) { 
                    $file_path = $target_file; 
                } else { 
                    $errorMessage = "❌ Failed to move file. Check folder permissions."; 
                }
            }
        }
    }

    if (empty($errorMessage)) {
        if ($slide_id) {
            // Update
            $stmt = $conn->prepare("UPDATE hero_slides SET heading=?, subtext=?, button_text=?, button_link=?, sort_order=?, file_path=?, type=? WHERE id=?");
            $stmt->bind_param("ssssissi", $heading, $subtext, $btn_text, $btn_link, $sort_order, $file_path, $type, $slide_id);
            $action = "Updated";
        } else {
            // Insert
            if (empty($file_path)) { 
                $errorMessage = "❌ Please select a file to upload."; 
            } else {
                $stmt = $conn->prepare("INSERT INTO hero_slides (heading, subtext, button_text, button_link, sort_order, file_path, type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiss", $heading, $subtext, $btn_text, $btn_link, $sort_order, $file_path, $type);
                $action = "Added";
            }
        }

        if (empty($errorMessage) && isset($stmt)) {
            if ($stmt->execute()) {
                $successMessage = "✅ Slide $action successfully!";
            } else {
                $errorMessage = "❌ Database Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// --- 4. FETCH SLIDES (Video First, Then Ordered Images) ---
$slides = [];
$result = $conn->query("SELECT * FROM hero_slides ORDER BY type DESC, sort_order ASC");
if ($result) { $slides = $result->fetch_all(MYSQLI_ASSOC); }

// --- 5. HEADER DATA ---
$unread_inquiries = 0;
$inq = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status='new'");
if($inq) $unread_inquiries = $inq->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Hero - Cafe Emmanuel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    :root { --primary-red: #E03A3E; --bg-light: #f8f9fa; --text-muted: #777; --card-bg: #fff; --border-color: #eee; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Poppins', sans-serif; background: var(--bg-light); display: flex; min-height: 100vh; }
    
    .main-content { flex-grow: 1; margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
    .main-header { display: flex; justify-content: space-between; margin-bottom: 30px; align-items: center; }
    .main-header h1 { font-family: 'Montserrat', sans-serif; font-weight: 700; color: #222; }

    /* Admin Grid Layout */
    .admin-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start; }
    
    /* Card Styles */
    .card { background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid var(--border-color); }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted); }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; outline: none; }
    .form-control:focus { border-color: var(--primary-red); }
    
    .btn-submit { width: 100%; padding: 12px; background: var(--primary-red); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-submit:hover { background: #c02d31; }

    /* Slide List Styles */
    .slide-item { background: white; border: 1px solid #eee; border-radius: 10px; padding: 15px; display: flex; gap: 15px; align-items: center; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .slide-preview { width: 120px; height: 80px; background: #f0f0f0; border-radius: 6px; overflow: hidden; position: relative; flex-shrink: 0; }
    .slide-preview img, .slide-preview video { width: 100%; height: 100%; object-fit: cover; }
    .badge { position: absolute; top: 5px; left: 5px; padding: 3px 6px; background: rgba(0,0,0,0.6); color: white; font-size: 10px; border-radius: 4px; text-transform: uppercase; }
    
    .slide-info h4 { font-size: 14px; margin-bottom: 5px; color: #222; }
    .slide-info p { font-size: 12px; color: #777; margin-bottom: 5px; }
    .action-btn { padding: 6px 10px; border: 1px solid #ddd; background: white; border-radius: 4px; color: #555; cursor: pointer; text-decoration: none; font-size: 12px; }
    .action-btn:hover { border-color: var(--primary-red); color: var(--primary-red); }

    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } .admin-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>Hero Slides</h1>
            <div style="display:flex; align-items:center; gap:15px;">
                <i class="fas fa-bell" style="color:#555; cursor:pointer;"></i>
                <img src="logo.png" style="width:35px; height:35px; border-radius:50%; border:1px solid #eee;">
            </div>
        </header>

        <?php if($successMessage): ?><div class="alert alert-success"><?php echo $successMessage; ?></div><?php endif; ?>
        <?php if($errorMessage): ?><div class="alert alert-error"><?php echo $errorMessage; ?></div><?php endif; ?>

        <div class="admin-grid">
            <div class="card">
                <h3 id="formTitle" style="margin-bottom:20px; color:var(--primary-red);">Add New Slide</h3>
                
                <form method="POST" enctype="multipart/form-data" id="slideForm">
                    <input type="hidden" name="slide_id" id="slide_id">
                    <input type="hidden" name="current_file" id="current_file">
                    <input type="hidden" name="current_type" id="current_type">

                    <div class="form-group">
                        <label>Media File (Max 30s Video)</label>
                        <input type="file" name="slide_file" id="fileInput" class="form-control" accept="image/*,video/mp4,video/webm">
                        <small id="fileHelp" style="color:#888; font-size:11px;">Current: <span id="fileNameDisplay">None</span></small>
                    </div>

                    <div class="form-group">
                        <label>Heading</label>
                        <input type="text" name="heading" id="heading" class="form-control" placeholder="e.g. Welcome to Cafe Emmanuel">
                    </div>

                    <div class="form-group">
                        <label>Subtext</label>
                        <textarea name="subtext" id="subtext" class="form-control" rows="3" placeholder="Short description..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Button Text</label>
                        <input type="text" name="button_text" id="button_text" class="form-control" value="View Menu">
                    </div>

                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                    </div>

                    <button type="submit" name="save_slide" class="btn-submit">Save Slide</button>
                    <button type="button" onclick="resetForm()" style="width:100%; margin-top:10px; background:transparent; border:1px solid #ddd; padding:8px; border-radius:6px; cursor:pointer; color:#666;">Cancel / Clear</button>
                </form>
            </div>

            <div class="slides-list">
                <?php if(empty($slides)): ?>
                    <div style="text-align:center; padding:40px; color:#999; border:2px dashed #ddd; border-radius:12px;">
                        <i class="fas fa-cloud-upload-alt fa-2x"></i><br>No slides yet. Add one!
                    </div>
                <?php else: ?>
                    <?php foreach($slides as $slide): ?>
                    <div class="slide-item">
                        <div class="slide-preview">
                            <?php if($slide['type'] == 'video'): ?>
                                <video src="<?php echo htmlspecialchars($slide['file_path']); ?>" muted></video>
                                <span class="badge" style="background:#E03A3E;"><i class="fas fa-video"></i> Video</span>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($slide['file_path']); ?>">
                                <span class="badge" style="background:#444;"><i class="fas fa-image"></i> Image</span>
                            <?php endif; ?>
                        </div>
                        <div class="slide-info" style="flex:1;">
                            <h4><?php echo htmlspecialchars($slide['heading'] ?: 'No Heading'); ?></h4>
                            <p><?php echo htmlspecialchars(substr($slide['subtext'], 0, 50)) . '...'; ?></p>
                            <small style="color:#aaa;">Order: <?php echo $slide['sort_order']; ?></small>
                        </div>
                        <div>
                            <button type="button" class="action-btn" onclick='editSlide(<?php echo json_encode($slide); ?>)'><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?php echo $slide['id']; ?>" class="action-btn" onclick="return confirm('Delete this slide?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // --- Populates the form for editing ---
        function editSlide(data) {
            document.getElementById('formTitle').innerText = 'Edit Slide';
            document.getElementById('slide_id').value = data.id;
            document.getElementById('heading').value = data.heading;
            document.getElementById('subtext').value = data.subtext;
            document.getElementById('button_text').value = data.button_text;
            document.getElementById('sort_order').value = data.sort_order;
            
            // Keep track of existing file so we don't lose it if user saves without uploading new one
            document.getElementById('current_file').value = data.file_path;
            document.getElementById('current_type').value = data.type;
            document.getElementById('fileNameDisplay').innerText = data.file_path.split('/').pop();
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('formTitle').innerText = 'Add New Slide';
            document.getElementById('slideForm').reset();
            document.getElementById('slide_id').value = '';
            document.getElementById('current_file').value = '';
            document.getElementById('current_type').value = '';
            document.getElementById('fileNameDisplay').innerText = 'None';
        }

        // --- Video Duration Check ---
        document.getElementById('fileInput').addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file && file.type.startsWith('video/')) {
                var vid = document.createElement('video');
                vid.preload = 'metadata';
                vid.onloadedmetadata = function() {
                    window.URL.revokeObjectURL(vid.src);
                    if (vid.duration > 30) {
                        alert("⚠️ Video is too long (" + vid.duration.toFixed(1) + "s). Max allowed is 30s.");
                        e.target.value = ""; // Clear input
                    }
                }
                vid.src = URL.createObjectURL(file);
            }
        });
    </script>
</body>
</html>