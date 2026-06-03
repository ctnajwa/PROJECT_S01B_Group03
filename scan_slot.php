<?php
$conn = mysqli_connect("localhost", "root", "", "mypetakom");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$slot_id = $_GET['slot_id'] ?? "";
$message = "";

if (empty($slot_id)) {
    die("Invalid attendance slot.");
}

$stmt = $conn->prepare("
    SELECT 
        a.attendanceslot_id,
        a.event_id,
        a.slot_time,
        ST_X(a.slot_geolocation) AS latitude,
        ST_Y(a.slot_geolocation) AS longitude,
        e.event_title
    FROM attendance_slot a
    JOIN event e ON a.event_id = e.event_id
    WHERE a.attendanceslot_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $slot_id);
$stmt->execute();
$result = $stmt->get_result();
$slot = $result->fetch_assoc();

if (!$slot) {
    die("Attendance slot not found.");
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;

    $lat1 = deg2rad((float)$lat1);
    $lon1 = deg2rad((float)$lon1);
    $lat2 = deg2rad((float)$lat2);
    $lon2 = deg2rad((float)$lon2);

    $latDiff = $lat2 - $lat1;
    $lonDiff = $lon2 - $lon1;

    $a = sin($latDiff / 2) * sin($latDiff / 2) +
         cos($lat1) * cos($lat2) *
         sin($lonDiff / 2) * sin($lonDiff / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

function generateAttendanceId($conn) {
    $result = $conn->query("
        SELECT MAX(CAST(SUBSTRING(attendance_id, 2) AS UNSIGNED)) AS max_id 
        FROM attendance
    ");

    $row = $result->fetch_assoc();
    $newNum = ($row['max_id'] ?? 0) + 1;

    return "A" . str_pad($newNum, 3, "0", STR_PAD_LEFT);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'] ?? '';
    $student_latitude = $_POST['student_latitude'] ?? '';
    $student_longitude = $_POST['student_longitude'] ?? '';

    $checkStudent = $conn->prepare("
        SELECT student_id 
        FROM student 
        WHERE student_id = ?
    ");
    $checkStudent->bind_param("s", $student_id);
    $checkStudent->execute();
    $studentResult = $checkStudent->get_result();

    if ($studentResult->num_rows == 0) {
        $message = "Invalid Student ID.";
    } elseif (empty($student_latitude) || empty($student_longitude)) {
        $message = "Location not detected. Please allow location permission.";
    } else {
        $checkDuplicate = $conn->prepare("
            SELECT attendance_id 
            FROM attendance 
            WHERE attendanceslot_id = ? AND student_id = ?
        ");
        $checkDuplicate->bind_param("ss", $slot_id, $student_id);
        $checkDuplicate->execute();
        $duplicateResult = $checkDuplicate->get_result();

        if ($duplicateResult->num_rows > 0) {
            $message = "You have already submitted attendance for this slot.";
        } else {
            $distance = calculateDistance(
                $slot['latitude'],
                $slot['longitude'],
                $student_latitude,
                $student_longitude
            );

            $allowedDistance = 500;
            $location_verification = ($distance <= $allowedDistance) ? "Verified" : "Not Verified";

            $attendance_id = generateAttendanceId($conn);

            $insert = $conn->prepare("
                INSERT INTO attendance
                (attendance_id, attendanceslot_id, student_id, checkin_time, location_verification, student_latitude, student_longitude)
                VALUES (?, ?, ?, NOW(), ?, ?, ?)
            ");

            $insert->bind_param(
                "ssssdd",
                $attendance_id,
                $slot_id,
                $student_id,
                $location_verification,
                $student_latitude,
                $student_longitude
            );

            if ($insert->execute()) {
                $message = "Attendance submitted successfully. Status: " . $location_verification;
            } else {
                $message = "Failed to submit attendance: " . $insert->error;
            }

            $insert->close();
        }

        $checkDuplicate->close();
    }

    $checkStudent->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Verification - MyPetakom</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('image/fkom.png') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        h1 {
            font-size: 26px;
            color: #2b4d71;
            margin-bottom: 25px;
            text-align: center;
        }

        .slot-item, .section-box {
            background: #ffffff;
            border-radius: 10px;
            padding: 25px;
            width: 420px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .section-box h3 {
            color: #2b4d71;
            margin-bottom: 10px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #aaa;
            box-sizing: border-box;
        }

        .verify-button {
            margin-top: 20px;
            background-color: #2b4d71;
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        .verify-button:hover {
            background-color: #1d3550;
        }

        .success-message {
            color: green;
            font-weight: bold;
        }

        .error-message {
            color: red;
            font-weight: bold;
        }

        .note {
            font-size: 0.9rem;
            color: #555;
        }

        @media screen and (max-width: 500px) {
            .slot-item, .section-box {
                width: 90%;
            }
        }
    </style>
</head>

<body>

<h1>Attendance Verification - MyPetakom</h1>

<div class="slot-item">
    <p><strong>Slot ID:</strong> <?= htmlspecialchars($slot['attendanceslot_id']) ?></p>
    <p><strong>Event ID:</strong> <?= htmlspecialchars($slot['event_id']) ?></p>
    <p><strong>Event Title:</strong> <?= htmlspecialchars($slot['event_title']) ?></p>
    <p><strong>Event Time:</strong> <?= htmlspecialchars($slot['slot_time']) ?></p>
    <p><strong>Current Malaysia Time:</strong> <span id="malaysiaTime">Loading...</span></p>
</div>

<div class="section-box">
    <h3>Verify your attendance:</h3>

    <?php if (!empty($message)): ?>
        <p class="<?= str_contains($message, 'successfully') ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <p class="note">
        Please enter your Student ID. Make sure this page is opened using HTTPS ngrok link.
    </p>

    <form method="post" id="attendanceForm">
        <label>Student ID:</label>
        <input type="text" name="student_id" required>

        <input type="hidden" name="student_latitude" id="student_latitude">
        <input type="hidden" name="student_longitude" id="student_longitude">

        <button type="button" class="verify-button" onclick="getLocationBeforeSubmit()">
            VERIFY
        </button>
    </form>
</div>

<script>
function updateMalaysiaTime() {
    const now = new Date();

    const malaysiaTime = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Kuala_Lumpur',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour12: false
    }).format(now);

    document.getElementById("malaysiaTime").innerHTML = malaysiaTime;
}

function getLocationBeforeSubmit() {
    if (!window.isSecureContext) {
        alert("This page must use HTTPS for location access. Please open using ngrok HTTPS link.");
        return;
    }

    if (!navigator.geolocation) {
        alert("Geolocation is not supported by this browser.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {
            document.getElementById("student_latitude").value = position.coords.latitude;
            document.getElementById("student_longitude").value = position.coords.longitude;
            document.getElementById("attendanceForm").submit();
        },
        function(error) {
            alert("Location error: " + error.message);
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}

updateMalaysiaTime();
setInterval(updateMalaysiaTime, 1000);
</script>

</body>
</html>

<?php $conn->close(); ?>
