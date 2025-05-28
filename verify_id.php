<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['id_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }

    $student_id = $_SESSION['studentID'];
    $file = $_FILES['id_file'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a JPEG or PNG image.']);
        exit;
    }

    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size too large. Maximum size is 5MB.']);
        exit;
    }

    // Get student details from database
    $query = "SELECT FirstName, MiddleInitial, LastName FROM students WHERE studentID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // Create a temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'id_');
    move_uploaded_file($file['tmp_name'], $temp_file);

    // Initialize Tesseract OCR
    $tesseract = new TesseractOCR($temp_file);
    $tesseract->setLanguage('eng');
    $tesseract->setWhitelist(range('A', 'Z') + range('a', 'z') + [' ', '.', ',']);
    
    // Perform OCR with multiple configurations for better accuracy
    $text = $tesseract->run();
    
    // Clean up the extracted text
    $text = preg_replace('/[^a-zA-Z\s]/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = strtoupper($text);

    // Prepare student name for comparison
    $student_name = strtoupper($student['FirstName'] . ' ' . 
                              ($student['MiddleInitial'] ? $student['MiddleInitial'] . ' ' : '') . 
                              $student['LastName']);

    // Split names into parts for comparison
    $student_parts = explode(' ', $student_name);
    $text_parts = explode(' ', $text);

    // Calculate similarity score
    $similarity = 0;
    $matched_parts = 0;
    
    foreach ($student_parts as $part) {
        foreach ($text_parts as $text_part) {
            similar_text($part, $text_part, $percent);
            if ($percent > 80) { // 80% similarity threshold
                $similarity += $percent;
                $matched_parts++;
                break;
            }
        }
    }

    // Calculate final similarity score
    $final_score = $matched_parts > 0 ? ($similarity / $matched_parts) : 0;

    // Verification result
    if ($final_score >= 80) {
        // Update verification status in database
        $update_query = "UPDATE students SET id_verified = 1 WHERE studentID = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $student_id);
        $update_stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'ID verification successful',
            'match_score' => round($final_score, 2),
            'extracted_text' => $text,
            'student_name' => $student_name
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'ID verification failed. Names do not match.',
            'match_score' => round($final_score, 2),
            'extracted_text' => $text,
            'student_name' => $student_name
        ]);
    }

    // Clean up
    unlink($temp_file);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 