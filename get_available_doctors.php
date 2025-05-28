<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
session_start();

// Debug information
$debug = [];

try {
    if (!isset($_SESSION['studentID'])) {
        throw new Exception('Not authenticated');
    }

    if (!isset($_POST['date'])) {
        throw new Exception('No date provided');
    }

    $date = $_POST['date'];
    $debug['date'] = $date;

    // Get the day of the week (0 = Sunday, 6 = Saturday)
    $dayOfWeek = date('l', strtotime($date)); // 'Monday', 'Tuesday', etc.
    $debug['dayOfWeek'] = $dayOfWeek;

    // First, let's check if we can connect to the database
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }

    // Check if the tables exist
    $tables = ['doctors', 'timeslots', 'appointments'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            throw new Exception("Table '$table' does not exist");
        }
    }

    // Only show slots that are not already booked for this date
    $query = "SELECT d.DoctorID, d.FirstName, d.LastName, d.Specialization, d.ProfilePhoto, t.SlotID, t.StartTime, t.EndTime
              FROM doctors d
              JOIN timeslots t ON d.DoctorID = t.DoctorID
              WHERE t.AvailableDay = ? 
                AND t.IsAvailable = 1
                AND t.SlotID NOT IN (
                    SELECT SlotID FROM appointments 
                    WHERE AppointmentDate = ? AND statusID IN (1,2,3)
                )";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    $stmt->bind_param("ss", $dayOfWeek, $date);
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $doctors = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['ScheduleTime'] = $row['StartTime'] . ' - ' . $row['EndTime'];
        $doctors[] = [
            'DoctorID' => $row['DoctorID'],
            'FirstName' => $row['FirstName'],
            'LastName' => $row['LastName'],
            'Specialization' => $row['Specialization'],
            'ProfilePhoto' => $row['ProfilePhoto'],
            'SlotID' => $row['SlotID'],
            'ScheduleTime' => $row['StartTime'] . '-' . $row['EndTime'],
        ];
    }

    $debug['doctors_count'] = count($doctors);
    $debug['query'] = $query;
    $debug['parameters'] = ['dayOfWeek' => $dayOfWeek, 'date' => $date];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'doctors' => $doctors,
        'debug' => $debug
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug
    ]);
}
?> 