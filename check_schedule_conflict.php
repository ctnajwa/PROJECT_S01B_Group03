<?php
session_start();

// Ensure only authorized staff can trigger validation
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Staff (Petakom Advisor)') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id']) && isset($_POST['event_id'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $event_id = $conn->real_escape_string($_POST['event_id']);

    // 1. Fetch the date of the current target event
    $target_event_query = $conn->query("SELECT event_date FROM event WHERE event_id = '$event_id'");
    if ($target_event_query->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Target event not found']);
        exit();
    }
    $target_event = $target_event_query->fetch_assoc();
    $target_date = $target_event['event_date'];

    // 2. Map the student_id to their member_id since committee uses member_id
    $member_query = $conn->query("SELECT member_id FROM member WHERE student_id = '$student_id'");
    if ($member_query->num_rows === 0) {
        echo json_encode(['success' => true, 'conflict' => false, 'message' => 'Not a registered member yet']);
        exit();
    }
    $member = $member_query->fetch_assoc();
    $member_id = $member['member_id'];

    // 3. Query if this member is already in a committee for an event on the SAME date
    // Exclude the current event_id to allow updates/edits without self-conflict
    $conflict_query = $conn->query("
        SELECT e.event_title 
        FROM committee c
        JOIN event e ON c.event_id = e.event_id
        WHERE c.member_id = '$member_id' 
          AND e.event_date = '$target_date'
          AND e.event_id != '$event_id'
    ");

    if ($conflict_query->num_rows > 0) {
        $conflicted_event = $conflict_query->fetch_assoc();
        echo json_encode([
            'success' => true,
            'conflict' => true,
            'event_title' => $conflicted_event['event_title']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'conflict' => false
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
}
?>
