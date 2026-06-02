<?php
$conn = new mysqli("localhost", "root", "", "mypetakom");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $studentID = $_POST["memberID"];
    $action = $_POST["action"];
    $reason = isset($_POST["reason"]) ? $_POST["reason"] : null;

    $statusMap = [
        "accept" => "approved",
        "reject" => "rejected"
    ];

    if (array_key_exists($action, $statusMap)) {
        $newStatus = $statusMap[$action];

        // Cari menggunakan student_id
        $checkStmt = $conn->prepare("SELECT member_id FROM member WHERE student_id = ?");
        $checkStmt->bind_param("s", $studentID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows === 0) {
            echo "Error: Member not found for student ID: " . $studentID;
            $checkStmt->close();
            $conn->close();
            exit();
        }
        
        $row = $checkResult->fetch_assoc();
        $actualMemberID = $row['member_id'];
        $checkStmt->close();

        // Update
        if ($action === "reject") {
            $stmt = $conn->prepare("UPDATE member SET member_approval = ?, member_status = ?, rejection_reason = ? WHERE member_id = ?");
            $stmt->bind_param("sssi", $newStatus, $newStatus, $reason, $actualMemberID);
        } else {
            $emptyReason = null;
            $stmt = $conn->prepare("UPDATE member SET member_approval = ?, member_status = ?, rejection_reason = ? WHERE member_id = ?");
            $stmt->bind_param("sssi", $newStatus, $newStatus, $emptyReason, $actualMemberID);
        }

        if ($stmt->execute()) {
            echo $newStatus;
        } else {
            echo "Database error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "invalid action";
    }
}

$conn->close();
?>