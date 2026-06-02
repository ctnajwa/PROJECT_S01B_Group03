<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$link = mysqli_connect("localhost", "root", "", "mypetakom");

// Check connection
if (!$link) {
    die("Connection failed: " . mysqli_connect_error());
}

// Query to get member data
$query = "
    SELECT u.user_id, u.user_name, m.member_id, m.member_approval, m.student_card, m.rejection_reason
    FROM user u
    JOIN member m ON u.user_id = m.student_id
    WHERE LOWER(u.user_role) = 'student'
";

// Execute query
$result = mysqli_query($link, $query);

// Check if query failed
if (!$result) {
    die("Query failed: " . mysqli_error($link));
}

// Check if有任何数据
$rowCount = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Membership Approval - MyPetakom Dashboard</title>
<link rel="stylesheet" href="STYLE1/admin.css" />

<style>
.reject-modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 999;
  align-items: center;
  justify-content: center;
}
.reject-modal-content {
  background: #fff;
  padding: 28px;
  border-radius: 8px;
  width: 420px;
  max-width: 90%;
  box-shadow: 0 4px 20px rgba(0,0,0,.25);
}
.reject-modal-content h3 {
  margin: 0 0 8px;
  color: #c0392b;
}
.reject-modal-content p {
  color: #555;
  font-size: .9em;
  margin-bottom: 10px;
}
.reject-modal-content textarea {
  width: 100%;
  padding: 10px;
  border: 1.5px solid #ccc;
  border-radius: 5px;
  resize: vertical;
  min-height: 90px;
  font-family: inherit;
  font-size: .93em;
  box-sizing: border-box;
  transition: border-color .2s;
}
.reject-modal-content textarea:focus { border-color: #c0392b; outline: none; }
.reject-modal-content textarea.input-err { border-color: #e74c3c; }
.char-counter { text-align: right; font-size: .78em; color: #aaa; margin-top: 3px; }
.err-msg { color: #e74c3c; font-size: .83em; margin-top: 5px; display: none; }
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
.btn-cancel-rej {
  padding: 8px 18px; border: none; border-radius: 5px;
  background: #eee; color: #444; cursor: pointer; font-size: .93em;
}
.btn-cancel-rej:hover { background: #d5d5d5; }
.btn-confirm-rej {
  padding: 8px 18px; border: none; border-radius: 5px;
  background: #e74c3c; color: #fff; font-weight: 700;
  cursor: pointer; font-size: .93em;
}
.btn-confirm-rej:hover { background: #c0392b; }
.remark-text { font-size: .83em; color: #888; font-style: italic; }
.remark-text.has-remark { color: #a71c2e; }
</style>
</head>
<body>

<div class="sidebar">
  <img src="IMAGES/LogoPetakom.png" alt="PETAKOM Logo" />
  <div class="menu">
    <div class="menu-title" onclick="window.location.href='admin_graph.php'">HOME</div>
    <div class="menu-title" onclick="toggleMenu('membership')">MEMBERSHIP</div>
    <div class="dropdown-content" id="membership">
      <a href="member_verification.php">Verification Status</a>
      <a href="view_member.php">View Member</a>
    </div>
    <div class="menu-title" onclick="toggleMenu('event')">EVENT</div>
    <div class="dropdown-content" id="event">
      <a href="#">Attendance Records</a>
    </div>
    <div class="menu-title" onclick="toggleMenu('merit')">MERIT</div>
    <div class="dropdown-content" id="merit">
      <a href="#">Merit Claim</a>
      <a href="#">Merit Application</a>
    </div>
  </div>
</div>

<div class="topbar">
  <div class="dropdown">
    <div class="profile-wrapper">
      <div class="profile-circle">N.</div>
      <span class="dropdown-icon">▼</span>
    </div>
    <div class="dropdown-content-top">
      <a href="administrator_profile.php">Profile</a>
      <a href="calendar.php">Calendar</a>
      <a href="#">Report</a>
      <a href="logout.php">Log Out</a>
    </div>
  </div>
</div>

<div class="content">
  <div class="h1text">
    <h1>MEMBERSHIP MANAGEMENT</h1>
  </div>

  <div class="container2">
    <input type="text" id="userSearch" placeholder="Search by name">

    <table id="membershipTable">
      <thead>
        <tr>
          <th>STUDENT ID</th>
          <th>NAME</th>
          <th>STATUS</th>
          <th>REMARK</th>
          <th>ACTIONS</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rowCount > 0): ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <?php
              $remarkSafe = htmlspecialchars($row["rejection_reason"] ?? "");
              $studentID = htmlspecialchars($row["user_id"]);
              $status = $row["member_approval"];
              $statusDisplay = ($status === null) ? "Pending" : ucfirst(htmlspecialchars($status));
            ?>
            <tr id="row-<?= $studentID ?>">
              <td><?= $studentID ?></td>
              <td><?= htmlspecialchars($row["user_name"]) ?></td>
              <td id="status-<?= $studentID ?>"><?= $statusDisplay ?></td>
              <td>
                <span id="remark-<?= $studentID ?>" class="remark-text <?= $remarkSafe ? 'has-remark' : '' ?>">
                  <?= $remarkSafe ?: "—" ?>
                </span>
              </td>
              <td>
                <button class="action-btn accept" onclick="updateStatus('<?= $studentID ?>', 'accept')">&#10004;</button>
                <button class="action-btn reject" onclick="updateStatus('<?= $studentID ?>', 'reject')">&#10008;</button>
                <button class="action-btn view-card" onclick="showCard('<?= base64_encode($row['student_card']) ?>')">🪪 View Card</button>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="text-align: center;">No records found</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="footer">@MyPetakom 2024/2025</div>

<div id="cardModal" class="card-modal">
  <div class="card-modal-content">
    <span class="close-modal" onclick="document.getElementById('cardModal').style.display='none'">✖️</span>
    <h3>Student Card Preview</h3>
    <img id="cardImage" src="" alt="Student Card">
  </div>
</div>

<div id="rejectModal" class="reject-modal">
  <div class="reject-modal-content">
    <h3>⚠️ Rejection Reason</h3>
    <p>Sila nyatakan sebab penolakan permohonan ini. Ia adalah wajib diisi.</p>
    <textarea
      id="rejectReason"
      placeholder="Cth: Gambar kad matrik tidak jelas, ID pelajar tidak sepadan..."
      maxlength="300"
      oninput="document.getElementById('rCount').textContent=this.value.length;
               this.classList.remove('input-err');
               document.getElementById('reasonErr').style.display='none';"
    ></textarea>
    <div class="char-counter"><span id="rCount">0</span>/300</div>
    <p id="reasonErr" class="err-msg">⚠ Sebab penolakan wajib diisi.</p>
    <div class="modal-btns">
      <button class="btn-cancel-rej" onclick="closeRejectModal()">Cancel</button>
      <button class="btn-confirm-rej" onclick="confirmReject()">Confirm Reject</button>
    </div>
  </div>
</div>

<script>
let pendingMemberID = null;

function toggleMenu(id) {
  const content = document.getElementById(id);
  if (content) {
    content.style.display = content.style.display === "block" ? "none" : "block";
  }
}

function updateStatus(memberID, action) {
  if (action === 'reject') {
    pendingMemberID = memberID;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectReason').classList.remove('input-err');
    document.getElementById('reasonErr').style.display = 'none';
    document.getElementById('rCount').textContent = '0';
    document.getElementById('rejectModal').style.display = 'flex';
    return;
  }
  sendStatusUpdate(memberID, 'accept', '');
}

function closeRejectModal() {
  document.getElementById('rejectModal').style.display = 'none';
  pendingMemberID = null;
}

function confirmReject() {
  const ta = document.getElementById('rejectReason');
  const reason = ta.value.trim();
  if (!reason) {
    ta.classList.add('input-err');
    document.getElementById('reasonErr').style.display = 'block';
    return;
  }
  const memberID = pendingMemberID;
  closeRejectModal();
  sendStatusUpdate(memberID, 'reject', reason);
}

function sendStatusUpdate(memberID, action, reason) {
  const xhr = new XMLHttpRequest();
  xhr.open("POST", "update_status_membership.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

  xhr.onload = function() {
    if (xhr.status === 200) {
      const response = xhr.responseText.trim();
      const statusCell = document.getElementById("status-" + memberID);
      const remarkSpan = document.getElementById("remark-" + memberID);

      if (response === "approved" || response === "rejected") {
        if (statusCell) {
          statusCell.innerText = response.charAt(0).toUpperCase() + response.slice(1);
        }
        if (remarkSpan) {
          if (response === "rejected") {
            remarkSpan.innerText = reason;
            remarkSpan.className = "remark-text has-remark";
          } else {
            remarkSpan.innerText = "—";
            remarkSpan.className = "remark-text";
          }
        }
        // Refresh page to show updated data
        setTimeout(function() { location.reload(); }, 500);
      } else {
        alert("Unexpected response: " + response);
      }
    } else {
      alert("Failed to update status: " + xhr.status + " - " + xhr.responseText);
    }
  };

  xhr.onerror = function() {
    alert("Request failed. Please check your connection.");
  };

  xhr.send("memberID=" + encodeURIComponent(memberID) + "&action=" + encodeURIComponent(action) + "&reason=" + encodeURIComponent(reason));
}

function showCard(base64Image) {
  const modal = document.getElementById("cardModal");
  const img = document.getElementById("cardImage");
  if (modal && img) {
    img.src = "data:image/jpeg;base64," + base64Image;
    modal.style.display = "flex";
  }
}

// Search functionality
const searchInput = document.getElementById("userSearch");
if (searchInput) {
  searchInput.addEventListener("keyup", function() {
    const search = this.value.toLowerCase();
    const rows = document.querySelectorAll("#membershipTable tbody tr");
    rows.forEach(row => {
      const text = row.innerText.toLowerCase();
      row.style.display = text.includes(search) ? "" : "none";
    });
  });
}

// Close modals when clicking outside
window.addEventListener("click", function(e) {
  const rejectModal = document.getElementById("rejectModal");
  const cardModal = document.getElementById("cardModal");
  if (e.target === rejectModal) closeRejectModal();
  if (e.target === cardModal && cardModal) cardModal.style.display = "none";
});
</script>

<?php mysqli_close($link); ?>
</body>
</html>