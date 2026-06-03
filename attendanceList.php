<?php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Staff (Petakom Advisor)') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$slot_id = $_GET['slot_id'] ?? $_GET['id'] ?? "";

if (empty($slot_id)) {
    die("Invalid attendance slot ID. Please open this page from the selected attendance slot.");
}

$query = "
    SELECT 
        a.attendance_id, 
        a.attendanceslot_id,
        s.student_id,
        e.event_id,
        e.event_title,
        a.checkin_time,
        a.location_verification
    FROM attendance a
    JOIN student s ON a.student_id = s.student_id
    JOIN attendance_slot slot ON a.attendanceslot_id = slot.attendanceslot_id
    JOIN event e ON slot.event_id = e.event_id
    WHERE a.attendanceslot_id = ?
    ORDER BY a.attendance_id DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $slot_id);
$stmt->execute();
$result = $stmt->get_result();

$event_title = "";
$event_id = "";
$records = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $event_title = $row['event_title'];
        $event_id = $row['event_id'];
        $records[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Verification List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f7f9fc;
        }

        h2 {
            color: #2b4d71;
        }

        .back-btn {
            margin-bottom: 20px;
            padding: 8px 15px;
            background-color: #0074D9;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .back-btn:hover {
            background-color: #005fa3;
        }

        .event-box {
            background: white;
            padding: 15px;
            border-left: 5px solid #0074D9;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        table {
            border-collapse: collapse;
            margin-bottom: 25px;
            width: 100%;
            background: white;
        }

        table, th, td {
            border: 1px solid #ccc;
        }

        th {
            background-color: #0074D9;
            color: white;
        }

        td, th {
            padding: 10px;
            text-align: center;
        }

        .verified {
            background-color: #d9ffd9;
        }

        .not-verified {
            background-color: #ffd9d9;
        }
    </style>
</head>
<body>

<button class="back-btn" onclick="window.location.href='attendaceSlot.php'">Back</button>

<h2>Attendance Location Verification Status</h2>

<div class="event-box">
    <p><strong>Slot ID:</strong> <?= htmlspecialchars($slot_id) ?></p>
    <p><strong>Event ID:</strong> <?= htmlspecialchars($event_id ?: '-') ?></p>
    <p><strong>Event Title:</strong> <?= htmlspecialchars($event_title ?: '-') ?></p>
</div>

<?php if (empty($records)): ?>

    <p>No attendance records found for this attendance slot.</p>

<?php else: ?>

    <table>
        <thead>
            <tr>
                <th>Attendance ID</th>
                <th>Student ID</th>
                <th>Slot ID</th>
                <th>Event Title</th>
                <th>Check-in Time</th>
                <th>Location Verification</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($records as $row): 
                $class = $row['location_verification'] === 'Verified' ? 'verified' : 'not-verified';
            ?>
                <tr class="<?= $class ?>">
                    <td><?= htmlspecialchars($row['attendance_id']) ?></td>
                    <td><?= htmlspecialchars($row['student_id']) ?></td>
                    <td><?= htmlspecialchars($row['attendanceslot_id']) ?></td>
                    <td><?= htmlspecialchars($row['event_title']) ?></td>
                    <td><?= htmlspecialchars($row['checkin_time']) ?></td>
                    <td><?= htmlspecialchars($row['location_verification']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

</body>
</html>

<?php 
$stmt->close();
$conn->close(); 
?>
