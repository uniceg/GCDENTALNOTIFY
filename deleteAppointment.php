<?php
include 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['studentID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['studentID'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = isset($_POST['appointmentID']) ? intval($_POST['appointmentID']) : null;

    if ($appointment_id) {
        $conn->begin_transaction();

        try {
            // Fetch the SlotID associated with the appointment
            $slotQuery = "SELECT SlotID FROM Appointments WHERE AppointmentID = ? AND StudentID = ?";
            $stmt = $conn->prepare($slotQuery);
            $stmt->bind_param("ii", $appointment_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $slot = $result->fetch_assoc();
            $slotID = $slot['SlotID'] ?? null; // Default to null if no SlotID
            $stmt->close();

            if (!$slot) {
                throw new Exception('Appointment not found.');
            }

            // Delete the appointment
            $deleteQuery = "DELETE FROM Appointments WHERE AppointmentID = ? AND StudentID = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("ii", $appointment_id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception('Error deleting the appointment.');
            }
            $stmt->close();

            // Update slot availability if SlotID exists
            if ($slotID) {
                $updateQuery = "UPDATE TimeSlots SET IsAvailable = 1 WHERE SlotID = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("i", $slotID);
                if (!$stmt->execute()) {
                    throw new Exception('Error updating time slot availability.');
                }
                $stmt->close();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Appointment successfully deleted.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment ID.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
