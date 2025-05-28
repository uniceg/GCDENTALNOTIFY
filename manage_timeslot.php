<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['adminID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    // Handle GET request - fetch timeslots
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['doctorID'])) {
        $doctorID = $_GET['doctorID'];
        
        $query = "SELECT SlotID, AvailableDay, StartTime, EndTime FROM timeslots WHERE DoctorID = ? ORDER BY 
                  CASE AvailableDay 
                    WHEN 'Monday' THEN 1
                    WHEN 'Tuesday' THEN 2
                    WHEN 'Wednesday' THEN 3
                    WHEN 'Thursday' THEN 4
                    WHEN 'Friday' THEN 5
                    WHEN 'Saturday' THEN 6
                    WHEN 'Sunday' THEN 7
                  END, StartTime";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $doctorID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $timeslots = [];
        while ($row = $result->fetch_assoc()) {
            $timeslots[] = $row;
        }
        
        echo json_encode(['success' => true, 'timeslots' => $timeslots]);
        exit;
    }
    
    // Handle POST request - add or delete timeslot
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Delete timeslot
        if (isset($_POST['delete']) && isset($_POST['SlotID'])) {
            $slotID = $_POST['SlotID'];
            
            $deleteQuery = "DELETE FROM timeslots WHERE SlotID = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $slotID);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Timeslot deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete timeslot']);
            }
            exit;
        }
        
        // Add new timeslot
        if (isset($_POST['doctorID']) && isset($_POST['AvailableDay']) && isset($_POST['StartTime']) && isset($_POST['EndTime'])) {
            $doctorID = $_POST['doctorID'];
            $availableDay = $_POST['AvailableDay'];
            $startTime = $_POST['StartTime'];
            $endTime = $_POST['EndTime'];
            
            // Validate time format and logic
            if (strtotime($startTime) >= strtotime($endTime)) {
                echo json_encode(['success' => false, 'error' => 'Start time must be before end time']);
                exit;
            }
            
            // Check for overlapping timeslots
            $checkQuery = "SELECT COUNT(*) as count FROM timeslots 
                          WHERE DoctorID = ? AND AvailableDay = ? 
                          AND ((StartTime <= ? AND EndTime > ?) OR (StartTime < ? AND EndTime >= ?))";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ssssss", $doctorID, $availableDay, $startTime, $startTime, $endTime, $endTime);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo json_encode(['success' => false, 'error' => 'Timeslot overlaps with existing schedule']);
                exit;
            }
            
            // Insert new timeslot
            $insertQuery = "INSERT INTO timeslots (DoctorID, AvailableDay, StartTime, EndTime) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("ssss", $doctorID, $availableDay, $startTime, $endTime);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Timeslot added successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to add timeslot: ' . $conn->error]);
            }
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
