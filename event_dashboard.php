<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in admin staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff (Administrator)') {
    header("Location: login.php");
    exit();
}

// Get user data from database
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM user WHERE user_id = ?";
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

// Get all distinct event titles for dropdown
$eventTitles = [];
$eventQuery = "SELECT DISTINCT event_title FROM event ORDER BY event_title";
$eventResult = $conn->query($eventQuery);
if ($eventResult) {
    while ($row = $eventResult->fetch_assoc()) {
        $eventTitles[] = $row['event_title'];
    }
}

// Get selected event from GET or POST (prefer GET for filter)
$selectedEvent = isset($_GET['event_title']) ? $_GET['event_title'] : '';

// Prepare summary query filtered by event_title if selected
if ($selectedEvent && in_array($selectedEvent, $eventTitles)) {
    $summaryQuery = "
        SELECT e.event_title,
               SUM(CASE WHEN a.location_verification = 'Verified' THEN 1 ELSE 0 END) AS verified_count,
               SUM(CASE WHEN a.location_verification = 'Not Verified' THEN 1 ELSE 0 END) AS unverified_count
        FROM attendance a
        JOIN attendance_slot slot ON a.attendanceslot_id = slot.attendanceslot_id
        JOIN event e ON slot.event_id = e.event_id
        WHERE e.event_title = ?
        GROUP BY e.event_title
        ORDER BY e.event_title
    ";
    $stmt2 = $conn->prepare($summaryQuery);
    $stmt2->bind_param("s", $selectedEvent);
    $stmt2->execute();
    $summaryResult = $stmt2->get_result();
} else {
    $summaryQuery = "
        SELECT e.event_title,
               SUM(CASE WHEN a.location_verification = 'Verified' THEN 1 ELSE 0 END) AS verified_count,
               SUM(CASE WHEN a.location_verification = 'Not Verified' THEN 1 ELSE 0 END) AS unverified_count
        FROM attendance a
        JOIN attendance_slot slot ON a.attendanceslot_id = slot.attendanceslot_id
        JOIN event e ON slot.event_id = e.event_id
        GROUP BY e.event_title
        ORDER BY e.event_title
    ";
    $summaryResult = $conn->query($summaryQuery);
}

$eventSummary = [];
$totalVerified = 0;
$totalUnverified = 0;

if ($summaryResult) {
    while ($row = $summaryResult->fetch_assoc()) {
        $eventSummary[] = $row;
        $totalVerified += (int)$row['verified_count'];
        $totalUnverified += (int)$row['unverified_count'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MyPetakom Dashboard</title>
  <link rel="stylesheet" href="STYLE1/administrator_style.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- Sidebar and Topbar -->
  <div class="sidebar">
  <img src="IMAGES/LogoPetakom.png" alt="PETAKOM Logo" />

  <div class="search-box">
    <input type="text" placeholder="SEARCH" />
    <button>🔍</button>
  </div>

  <div class="menu">
    <a class="menu-title" href="admin_graph.php">HOME</a>
    <div class="menu-title" onclick="toggleMenu('membership')">MEMBERSHIP</div>
    <div class="dropdown-content" id="membership">
      <a href="member_verification.php">Verification Status</a>
      <a href="view_member.php">View Member</a>
    </div>
    <div class="menu-title" onclick="toggleMenu('event')">EVENT</div>
    <div class="dropdown-content" id="event">
      <a href="event_dashboard.php">Attendance Records</a>
    </div>
    <div class="menu-title" onclick="toggleMenu('merit')">MERIT</div>
    <div class="dropdown-content" id="merit">
      <a href="merit_application_admin.php">Merit Application</a>
    </div>
  </div>
</div>
 <!-- Topbar -->
  <div class="topbar">
    <div class="dropdown">
      <div class="profile-wrapper">
        <div class="profile-circle">N.</div>
        <span class="dropdown-icon">▼</span>
      </div>
      <div class="dropdown-content-top">
        <a href="administrator_profile.php">Profile</a>
        <a href="#">Calendar</a>
        <a href="#">Report</a>
        <a href="logout.php">Log Out</a>
      </div>
    </div>
  </div>
  <!-- Example: include your existing sidebar and topbar -->
  <?php
    // include('sidebar.php');
    // include('topbar.php');
  ?>

  <!-- Main Content -->
  <div class="content">

    <!-- Event filter form -->
    <form method="get" action="" style="margin-bottom: 20px;">
      <label for="event_title">Select Event:</label>
      <select name="event_title" id="event_title" onchange="this.form.submit()">
        <option value="">-- All Events --</option>
        <?php foreach ($eventTitles as $title): ?>
          <option value="<?php echo htmlspecialchars($title); ?>" <?php echo ($selectedEvent === $title) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($title); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit">Filter</button></noscript>
    </form>

    <h2>Event Attendance Verification Summary</h2>
    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
      <thead>
        <tr>
          <th>Event Title</th>
          <th>Verified Students</th>
          <th>Unverified Students</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($eventSummary)): ?>
          <?php foreach ($eventSummary as $event): ?>
            <tr>
              <td><?php echo htmlspecialchars($event['event_title']); ?></td>
              <td><?php echo (int)$event['verified_count']; ?></td>
              <td><?php echo (int)$event['unverified_count']; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="3" style="text-align:center;">No event attendance data found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="chart-container" style="display: flex; gap: 40px; align-items: flex-start; margin-top: 40px; margin-bottom: 50px;">
      <div style="flex: 1;">
        <h2>Verified vs Unverified Students Per Event</h2>
        <canvas id="barChart" style="max-width: 100%;"></canvas>
      </div>
      <div style="width: 400px;">
        <h2>Total Verified vs Unverified Students</h2>
        <canvas id="pieChart" style="max-width: 100%;"></canvas>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    @MyPetakom 2024/2025
  </div>

<script>
  const eventLabels = <?php echo json_encode(array_column($eventSummary, 'event_title')); ?>;
  const verifiedCounts = <?php echo json_encode(array_map('intval', array_column($eventSummary, 'verified_count'))); ?>;
  const unverifiedCounts = <?php echo json_encode(array_map('intval', array_column($eventSummary, 'unverified_count'))); ?>;
  const totalVerified = <?php echo $totalVerified; ?>;
  const totalUnverified = <?php echo $totalUnverified; ?>;

  const barCtx = document.getElementById('barChart').getContext('2d');
  const barChart = new Chart(barCtx, {
    type: 'bar',
    data: {
      labels: eventLabels,
      datasets: [
        {
          label: 'Verified',
          data: verifiedCounts,
          backgroundColor: 'rgba(54, 162, 235, 0.7)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 1
        },
        {
          label: 'Unverified',
          data: unverifiedCounts,
          backgroundColor: 'rgba(255, 99, 132, 0.7)',
          borderColor: 'rgba(255, 99, 132, 1)',
          borderWidth: 1
        }
      ]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  const pieCtx = document.getElementById('pieChart').getContext('2d');
  const pieChart = new Chart(pieCtx, {
    type: 'pie',
    data: {
      labels: ['Verified', 'Unverified'],
      datasets: [{
        label: 'Attendance Verification',
        data: [totalVerified, totalUnverified],
        backgroundColor: [
          'rgba(54, 162, 235, 0.7)',
          'rgba(255, 99, 132, 0.7)'
        ],
        borderColor: [
          'rgba(54, 162, 235, 1)',
          'rgba(255, 99, 132, 1)'
        ],
        borderWidth: 1
      }]
    },
    options: { responsive: true }
  });

  function toggleMenu(id) {
    var content = document.getElementById(id);
    content.style.display = content.style.display === "block" ? "none" : "block";
  }
</script>

</body>
</html>