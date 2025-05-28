<?php
include 'config.php';


$isAvailable = isset($_GET['isAvailable']) && $_GET['isAvailable'] == '1';

$query = $isAvailable
    ? "SELECT Doctors.DoctorID, FirstName, LastName, ContactNumber, Email, ImageFile, 
             TimeSlots.SlotID, TimeSlots.AvailableDay, TimeSlots.StartTime, TimeSlots.EndTime, TimeSlots.IsAvailable 
       FROM Doctors
       LEFT JOIN TimeSlots ON Doctors.DoctorID = TimeSlots.DoctorID
       WHERE TimeSlots.IsAvailable = 1"
    : "SELECT Doctors.DoctorID, FirstName, LastName, ContactNumber, Email, ImageFile, 
             TimeSlots.SlotID, TimeSlots.AvailableDay, TimeSlots.StartTime, TimeSlots.EndTime, TimeSlots.IsAvailable 
       FROM Doctors
       LEFT JOIN TimeSlots ON Doctors.DoctorID = TimeSlots.DoctorID";

$result = mysqli_query($conn, $query);

$doctors = [];
while ($row = mysqli_fetch_assoc($result)) {
    $doctorID = $row['DoctorID'];
    if (!isset($doctors[$doctorID])) {
        $doctors[$doctorID] = [
            'DoctorID' => $doctorID,
            'FirstName' => $row['FirstName'],
            'LastName' => $row['LastName'],
            'ContactNumber' => $row['ContactNumber'],
            'Email' => $row['Email'],
            'ImageFile' => $row['ImageFile'],
            'timeSlots' => []
        ];
    }
    if ($row['SlotID'] !== null) {
        $doctors[$doctorID]['timeSlots'][] = [
            'SlotID' => $row['SlotID'],
            'AvailableDay' => $row['AvailableDay'],
            'StartTime' => $row['StartTime'],
            'EndTime' => $row['EndTime'],
            'IsAvailable' => (bool) $row['IsAvailable']
        ];
    }
}

echo json_encode(array_values($doctors));
?>
