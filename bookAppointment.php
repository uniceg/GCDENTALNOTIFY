<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

$studentID = $_SESSION['studentID'];

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $doctorID = $data['doctorID'];
    $slotID = $data['slotID'];
    $appointmentDate = $data['appointmentDate'];
    $reason = $data['appointmentReason'];

    mysqli_begin_transaction($conn);

    try {
        // Insert appointment data
        $query = "INSERT INTO Appointments (StudentID, DoctorID, SlotID, AppointmentDate, Reason, statusID)
                  VALUES ('$studentID', '$doctorID', '$slotID', '$appointmentDate', '$reason', 1)";
        if (!mysqli_query($conn, $query)) {
            throw new Exception('Error booking appointment: ' . mysqli_error($conn));
        }

        // Update timeslot availability
        $updateQuery = "UPDATE timeslots SET IsAvailable = 0 WHERE SlotID = '$slotID'";
        if (!mysqli_query($conn, $updateQuery)) {
            throw new Exception('Error updating time slot: ' . mysqli_error($conn));
        }

        mysqli_commit($conn);

        // Fetch student info
        $studentInfoQuery = "SELECT FirstName, LastName, email FROM students WHERE StudentID = ?";
        $studentInfoStmt = $conn->prepare($studentInfoQuery);
        $studentInfoStmt->bind_param("s", $studentID);
        $studentInfoStmt->execute();
        $studentInfoResult = $studentInfoStmt->get_result();
        $studentInfo = $studentInfoResult->fetch_assoc();
        $to = $studentInfo['email'];
        $toName = $studentInfo['FirstName'] . ' ' . $studentInfo['LastName'];

        // Fetch doctor info
        $doctorInfoQuery = "SELECT FirstName, LastName FROM doctors WHERE DoctorID = ?";
        $doctorInfoStmt = $conn->prepare($doctorInfoQuery);
        $doctorInfoStmt->bind_param("s", $doctorID);
        $doctorInfoStmt->execute();
        $doctorInfoResult = $doctorInfoStmt->get_result();
        $doctorInfo = $doctorInfoResult->fetch_assoc();
        $doctorName = $doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName'];

        // Fetch time slot info
        $slotInfoQuery = "SELECT StartTime, EndTime FROM timeslots WHERE SlotID = ?";
        $slotInfoStmt = $conn->prepare($slotInfoQuery);
        $slotInfoStmt->bind_param("s", $slotID);
        $slotInfoStmt->execute();
        $slotInfoResult = $slotInfoStmt->get_result();
        $slotInfo = $slotInfoResult->fetch_assoc();
        $appointmentTime = date('g:i A', strtotime($slotInfo['StartTime'])) . ' - ' . date('g:i A', strtotime($slotInfo['EndTime']));

        echo json_encode(['status' => 'success', 'message' => 'Appointment booked successfully']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>
