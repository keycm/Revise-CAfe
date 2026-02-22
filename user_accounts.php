<?php
include 'session_check.php';
include 'db_connect.php';

// --- DATA FOR HEADER ICONS ---
$inquiry_count_result = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
$unread_inquiries = $inquiry_count_result ? $inquiry_count_result->fetch_assoc()['count'] : 0;

$recent_inquiries_result = $conn->query("SELECT * FROM inquiries WHERE status = 'new' ORDER BY received_at DESC LIMIT 5");
$recent_messages = [];
if ($recent_inquiries_result) {
    while ($row = $recent_inquiries_result->fetch_assoc()) {
        $recent_messages[] = $row;
    }
}

// --- FETCH ADMIN USERS ---
// We query the 'users' table and filter for 'admin' or 'super_admin' roles
$sql = "SELECT id, fullname, email, role, created_at FROM users WHERE role IN ('admin', 'super_admin') ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Accounts - Cafe Emmanuel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #E03A3E;
            --secondary-dark: #222222;
            --text-muted: #777777;
            --bg-light: #f8f9fa;
            --card-bg: #FFFFFF;
            --border-color: #eeeeee;
            --font-main: 'Poppins', sans-serif;
            --font-heading: 'Montserrat', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-light);
            color: var(--secondary-dark);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content { 
            flex-grow: 1;
            margin-left: 260px;
            width: calc(100% - 260px); 
            height: 100vh;
            overflow-y: auto;
            padding: 40px;
        }

        .main-header { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        
        .main-header h1 { 
            font-family: var(--font-heading);
            font-size: 28px; 
            font-weight: 700; 
        }

        /* Header Icons */
        .header-icons { display: flex; align-items: center; gap: 20px; }
        .icon-container {
            position: relative;
            cursor: pointer;
            width: 40px; height: 40px;
            background: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--border-color);
            transition: 0.3s;
        }
        .icon-container:hover { background: var(--primary-red); color: white; border-color: var(--primary-red); }
        .header-badge {
            position: absolute;
            top: -5px; right: -5px;
            background-color: var(--primary-red); color: white;
            border-radius: 50%;
            width: 18px; height: 18px;
            font-size: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; border: 2px solid #fff;
        }
        .header-icons img { width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border-color); }

        /* Card & Table */
        .card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .card-header h2 { font-family: var(--font-heading); font-size: 20px; font-weight: 700; }

        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th {
            text-align: left;
            padding: 15px;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 2px solid var(--bg-light);
            letter-spacing: 0.5px;
        }
        .user-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            vertical-align: middle;
        }
        .user-table tr:hover { background-color: #fcfcfc; }

        /* Badges & Buttons */
        .role-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .role-super_admin { background: #e3f2fd; color: #1565c0; }
        .role-admin { background: #f3e5f5; color: #7b1fa2; }

        .action-icons { display: flex; gap: 8px; }
        .btn-icon { 
            width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; 
            border: 1px solid var(--border-color); background: white; cursor: pointer; transition: 0.2s; color: var(--text-muted);
            border: 1px solid #ddd;
        }
        .btn-icon:hover { background: var(--primary-red); color: white; border-color: var(--primary-red); }

        .btn-add {
            background: var(--primary-red);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-add:hover { background: #c02d31; }

        /* Dropdown Menu */
        .dropdown-menu {
            display: none; position: absolute; right: 0; top: 50px;
            background: white; width: 300px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-radius: 8px; z-index: 1001;
            border: 1px solid var(--border-color);
        }
        .dropdown-menu.show { display: block; }
        .dropdown-item {
            padding: 12px 15px; display: block; text-decoration: none;
            color: var(--secondary-dark); font-size: 13px; border-bottom: 1px solid var(--border-color);
        }
        .dropdown-item:hover { background: var(--bg-light); color: var(--primary-red); }

        /* --- MODAL STYLES --- */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 2000; 
            left: 0; top: 0; 
            width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.5); 
            align-items: center; 
            justify-content: center;
        }
        .modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            width: 400px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
        }
        .close-modal {
            position: absolute; top: 15px; right: 20px;
            font-size: 24px; cursor: pointer; color: #aaa;
        }
        .close-modal:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
        .form-group select { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; 
        }
        .btn-save {
            width: 100%; background: var(--primary-red); color: white; border: none; padding: 12px;
            border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;
        }
        .btn-save:hover { background: #c02d31; }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>User Accounts</h1>
            <div class="header-icons">
                <div class="icon-container" id="messageIcon">
                    <i class="fas fa-envelope"></i>
                    <?php if($unread_inquiries > 0): ?>
                        <span class="header-badge"><?php echo $unread_inquiries; ?></span>
                    <?php endif; ?>
                    <div class="dropdown-menu" id="messageDropdown">
                        <?php foreach ($recent_messages as $msg): ?>
                            <a href="admin_inquiries.php" class="dropdown-item">
                                <strong><?php echo htmlspecialchars($msg['first_name']); ?></strong>: 
                                <?php echo htmlspecialchars(substr($msg['message'], 0, 30)); ?>...
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="icon-container" id="notificationBell"><i class="fas fa-bell"></i></div>

                <div class="icon-container" id="profileIcon" style="border:none;">
                    <img src="logo.png" alt="Admin">
                    <div class="dropdown-menu" id="profileDropdown" style="width:150px;">
                        <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <?php if(isset($_GET['success'])): ?>
            <div style="padding:15px; background:#d4edda; color:#155724; border-radius:8px; margin-bottom:20px;">
                Action completed successfully!
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div style="padding:15px; background:#f8d7da; color:#721c24; border-radius:8px; margin-bottom:20px;">
                Error: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>Admin Users</h2>
                <a href="create_admin_account.php" class="btn-add"><i class="fas fa-plus"></i> Create New</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['fullname']); ?></td>
                                    <td style="color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $row['role']; ?>">
                                            <?php echo str_replace('_', ' ', $row['role']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-muted);">
                                        <?php echo date("M d, Y", strtotime($row['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-icons">
                                            <button 
                                                class="btn-icon" 
                                                title="Edit Role"
                                                onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo $row['role']; ?>', '<?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?>')"
                                                <?php if($row['id'] == $_SESSION['user_id']) echo 'disabled style="opacity:0.5; cursor:not-allowed;"'; ?> 
                                            >
                                                <i class="fas fa-user-tag"></i>
                                            </button>

                                            <form action="user_actions.php?action=delete&id=<?php echo $row['id']; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to move this user to the recycle bin?');">
                                                <button type="submit" class="btn-icon" title="Move to Recycle Bin" 
                                                <?php if($row['id'] == $_SESSION['user_id']) echo 'disabled style="opacity:0.5; cursor:not-allowed;"'; ?>>
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">No admin accounts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="editRoleModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-bottom:20px; font-family:var(--font-heading);">Edit User Role</h2>
            <p id="editUserName" style="margin-bottom:20px; color:#555;"></p>
            
            <form action="update_role.php" method="POST">
                <input type="hidden" name="user_id" id="modalUserId">
                
                <div class="form-group">
                    <label for="new_role">Select New Role:</label>
                    <select name="new_role" id="modalRoleSelect" required>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="user">User (Demote)</option>
                    </select>
                </div>

                <button type="submit" class="btn-save">Update Role</button>
            </form>
        </div>
    </div>

    <script>
        // Modal Functions
        function openEditModal(id, role, name) {
            document.getElementById('modalUserId').value = id;
            document.getElementById('modalRoleSelect').value = role;
            document.getElementById('editUserName').innerText = "Editing user: " + name;
            document.getElementById('editRoleModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editRoleModal').style.display = 'none';
        }

        // Close modal if clicked outside content
        window.onclick = function(event) {
            var modal = document.getElementById('editRoleModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = [
                { id: 'messageIcon', menu: 'messageDropdown' },
                { id: 'notificationBell', menu: 'notificationDropdown' },
                { id: 'profileIcon', menu: 'profileDropdown' }
            ];

            toggles.forEach(t => {
                const btn = document.getElementById(t.id);
                if(btn) {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
                        document.getElementById(t.menu).classList.add('show');
                    });
                }
            });

            document.addEventListener('click', () => {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
            });
        });
    </script>
</body>
</html>