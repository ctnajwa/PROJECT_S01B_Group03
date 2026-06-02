<?php
session_start();

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

if (isset($_POST['apply_membership']) && isset($_FILES['student_card'])) {
    $file = $_FILES['student_card'];
    
    // Check if file uploaded successfully
    if ($file['error'] === 0) {
        // Read file content
        $imageData = file_get_contents($file['tmp_name']);
        
        // Escape binary data for database
        $imageDataEscaped = addslashes($imageData);
        
        // Check if student already has an application
        $checkQuery = "SELECT member_id FROM member WHERE student_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing application - use empty string for rejection_reason
            $updateQuery = "UPDATE member SET student_card = ?, member_approval = 'pending', member_status = 'pending', rejection_reason = '' WHERE student_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ss", $imageDataEscaped, $user_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['message'] = "Membership application updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update application: " . $conn->error;
            }
            $updateStmt->close();
        } else {
            // Insert new application - use empty string for rejection_reason
            $insertQuery = "INSERT INTO member (student_id, member_status, member_approval, student_card, rejection_reason) VALUES (?, 'pending', 'pending', ?, '')";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("ss", $user_id, $imageDataEscaped);
            
            if ($insertStmt->execute()) {
                $_SESSION['message'] = "Membership application submitted successfully!";
            } else {
                $_SESSION['error'] = "Failed to submit application: " . $conn->error;
            }
            $insertStmt->close();
        }
        
        $checkStmt->close();
    } else {
        $_SESSION['error'] = "Please select a valid student card image.";
    }
}

header("Location: student_membership.php");
exit();
?>