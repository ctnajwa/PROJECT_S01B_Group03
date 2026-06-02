<?php
$conn = new mysqli("localhost", "root", "", "mypetakom");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_POST['event_id'];
    $student_ids = $_POST['student_ids'];
    $positions = $_POST['positions'];

	// Fetch target event date first for backend date calculation verification
    $target_date_res = $conn->query("SELECT event_date FROM event WHERE event_id = '$event_id'");
    $target_date_data = $target_date_res->fetch_assoc();
    $target_date = $target_date_data['event_date'];

    // VALIDATION LOOP (Check everyone FIRST before modifying data)
    for ($i = 0; $i < count($student_ids); $i++) {
        $student_id = $conn->real_escape_string($student_ids[$i]);

        // Ensure student exists in user table
        $check_user = $conn->query("SELECT * FROM user WHERE user_id = '$student_id'");
        if ($check_user->num_rows === 0) {
            continue; // skip invalid user
        }

        // Get member_id from member table based on student_id
        $result_member = $conn->query("SELECT member_id FROM member WHERE student_id = '$student_id'");
        if ($result_member->num_rows === 0) {
            continue; // skip if no corresponding member entry
        }

        $member = $result_member->fetch_assoc();
        $member_id = $member['member_id'];

        // Verify backend conflict right before performing structural insertion loop
        $backend_conflict_check = $conn->query("
            SELECT e.event_title 
            FROM committee c
            JOIN event e ON c.event_id = e.event_id
            WHERE c.member_id = '$member_id' 
              AND e.event_date = '$target_date'
              AND e.event_id != '$event_id'
        ");

        if ($backend_conflict_check->num_rows > 0) {
            $conflicted_event_data = $backend_conflict_check->fetch_assoc();
            $conflicted_name = $conflicted_event_data['event_title'];
            
            // Abort completely BEFORE any database deletions happen
            echo "<script>
                    alert('Submission Rejected! Student (ID: $student_id) is already registered for another event (\'$conflicted_name\') on this exact calendar date.'); 
                    window.history.back();
                  </script>";
            exit(); 
        }
    }

	// Execution (Only runs if every student passed the check)
	// Clear old committee (this works for both new or existing events)
    $conn->query("DELETE FROM committee WHERE event_id = '$event_id'");

    for ($i = 0; $i < count($student_ids); $i++) {
        $student_id = $conn->real_escape_string($student_ids[$i]);
        $position = $conn->real_escape_string($positions[$i]);

        // Ensure student exists in user table
        $check_user = $conn->query("SELECT * FROM user WHERE user_id = '$student_id'");
        if ($check_user->num_rows === 0) {
            continue; // skip invalid user
        }

        // Get member_id from member table based on student_id
        $result_member = $conn->query("SELECT member_id FROM member WHERE student_id = '$student_id'");
        if ($result_member->num_rows === 0) {
            continue; // skip if no corresponding member entry
        }

        $member = $result_member->fetch_assoc();
        $member_id = $member['member_id'];

        // Insert into committee table
        $conn->query("INSERT INTO committee (member_id, event_id, committee_role)
                      VALUES ('$member_id', '$event_id', '$position')");
    }

    echo "<script>alert('Committee Added Successfully'); window.location.href='assign_event.php';</script>";
} 
else {
    echo "Invalid access.";
}
?>
