<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student profile info
$query = "
    SELECT u.user_name
    FROM user u
    JOIN student s ON u.user_id = s.student_id
    WHERE u.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();

// Get membership status
$memberQuery = "
    SELECT member_approval, rejection_reason 
    FROM member 
    WHERE student_id = ?
";
$memberStmt = $conn->prepare($memberQuery);
$memberStmt->bind_param("s", $user_id);
$memberStmt->execute();
$memberResult = $memberStmt->get_result();
$membership = $memberResult->fetch_assoc();

$memberStatus = $membership ? $membership['member_approval'] : null;
$rejectionReason = $membership ? $membership['rejection_reason'] : null;

// Handle success/error messages
$message = '';
$error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Membership - MyPetakom</title>
  <link rel="stylesheet" href="STYLE4/student_style.css" />
  <style>
    .membership-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    .membership-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 20px;
      padding: 30px;
      margin-bottom: 30px;
      color: white;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .membership-card h2 {
      margin: 0 0 15px 0;
      font-size: 1.8rem;
    }
    .status-badge {
      display: inline-block;
      padding: 10px 30px;
      border-radius: 30px;
      font-weight: bold;
      margin-top: 15px;
      font-size: 1rem;
    }
    .status-pending {
      background: #ffc107;
      color: #333;
    }
    .status-approved {
      background: #28a745;
      color: white;
    }
    .status-rejected {
      background: #dc3545;
      color: white;
    }
    .rejection-reason {
      background: rgba(255,255,255,0.2);
      padding: 12px;
      border-radius: 10px;
      margin-top: 15px;
      font-size: 0.95rem;
    }
    .apply-btn {
      background: white;
      color: #667eea;
      border: none;
      padding: 12px 30px;
      border-radius: 30px;
      cursor: pointer;
      font-weight: bold;
      margin-top: 15px;
      font-size: 1rem;
      transition: transform 0.2s;
    }
    .apply-btn:hover {
      transform: scale(1.05);
      cursor: pointer;
    }
    .membership-form {
      background: white;
      border-radius: 20px;
      padding: 30px;
      color: #333;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .membership-form h3 {
      margin-top: 0;
      color: #667eea;
      font-size: 1.5rem;
    }
    .membership-form p {
      margin: 10px 0;
    }
    .membership-form input[type="file"] {
      margin: 15px 0;
      padding: 12px;
      border: 2px dashed #ccc;
      border-radius: 10px;
      width: 100%;
      box-sizing: border-box;
      font-size: 0.95rem;
    }
    .submit-btn {
      background: #667eea;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 30px;
      cursor: pointer;
      font-weight: bold;
      font-size: 1rem;
      transition: background 0.2s;
    }
    .submit-btn:hover {
      background: #5a67d8;
    }
    .back-link {
      display: inline-block;
      margin-top: 20px;
      color: #667eea;
      text-decoration: none;
      font-weight: bold;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    .alert-success {
      background: #d4edda;
      color: #155724;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      border: 1px solid #c3e6cb;
    }
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="IMAGES/LogoPetakom.png" alt="PETAKOM Logo" />

    <div class="search-box">
      <input type="text" placeholder="SEARCH" />
      <button>🔍</button>
    </div>

    <div class="menu">
      <div class="menu-title" onclick="window.location.href='student_dashboard.php'">HOME</div>
      
      <div class="menu-title" onclick="window.location.href='student_membership.php'">MEMBERSHIP</div>

      <div class="menu-title" onclick="toggleMenu('event')">EVENT</div>
      <div class="dropdown-content" id="event">
        <a href="#">View Event</a>
      </div>

      <div class="menu-title" onclick="toggleMenu('attendance')">ATTENDANCE</div>
      <div class="dropdown-content" id="attendance">
        <a href="#">Key In Attendance</a>
        <a href="#">View Attendance</a>
      </div>

      <div class="menu-title" onclick="toggleMenu('merit')">MERIT</div>
      <div class="dropdown-content" id="merit">
        <a href="meritApplication.php">Merit Application</a>
        <a href="meritAwarded.php">Merit Summary</a>
      </div>
    </div>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <div class="dropdown">
      <div class="profile-wrapper">
        <div class="profile-circle">
          <?= strtoupper(substr($user['user_name'], 0, 1)) ?>.
        </div>
        <span class="dropdown-icon">▼</span>
      </div>
      <div class="dropdown-content-top">
        <a href="student_profile.php">Profile</a>
        <a href="#">Calendar</a>
        <a href="#">Report</a>
        <a href="logout.php">Log Out</a>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="membership-container">
      <h1>Membership Management</h1>

      <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Membership Status Card -->
      <div class="membership-card">
        <h2>Membership Status</h2>
        <?php if ($memberStatus === null): ?>
          <p>You haven't applied for membership yet.</p>
          <button class="apply-btn" onclick="showForm()">Apply Now</button>
        <?php elseif ($memberStatus === 'approved'): ?>
          <p>✅ Congratulations! Your membership has been <strong>APPROVED</strong>!</p>
          <div class="status-badge status-approved">✓ Active Member</div>
        <?php elseif ($memberStatus === 'rejected'): ?>
          <p>❌ Your membership application has been <strong>REJECTED</strong>.</p>
          <div class="status-badge status-rejected">✗ Rejected</div>
          <?php if ($rejectionReason): ?>
            <div class="rejection-reason">
              <strong>Reason for rejection:</strong><br>
              <?= htmlspecialchars($rejectionReason) ?>
            </div>
          <?php endif; ?>
          <button class="apply-btn" onclick="showForm()">Apply Again</button>
        <?php else: ?>
          <p>⏳ Your membership application is <strong>PENDING</strong> review.</p>
          <div class="status-badge status-pending">⌛ Waiting for Approval</div>
        <?php endif; ?>
      </div>

      <!-- Membership Application Form (Hidden by default) -->
      <div id="membershipForm" style="display: none;">
        <div class="membership-form">
          <h3>📝 Apply for Membership</h3>
          <p>Please upload a clear photo of your student card for verification purposes.</p>
          <form action="apply_membership.php" method="POST" enctype="multipart/form-data">
            <input type="file" name="student_card" accept="image/*" required>
            <button type="submit" name="apply_membership" class="submit-btn">Submit Application</button>
          </form>
        </div>
      </div>

      <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    @MyPetakom 2024/2025
  </div>

  <script>
    function toggleMenu(id) {
      var content = document.getElementById(id);
      if (content) {
        content.style.display = content.style.display === "block" ? "none" : "block";
      }
    }
    
    function showForm() {
      document.getElementById('membershipForm').style.display = 'block';
      // Scroll to form
      document.getElementById('membershipForm').scrollIntoView({ behavior: 'smooth' });
    }
  </script>
</body>
</html>