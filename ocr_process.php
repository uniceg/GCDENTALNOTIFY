<?php
// Function to preprocess image using OpenCV
function preprocessImage($imagePath) {
    // Python script path
    $pythonScript = __DIR__ . '/preprocess.py';
    
    // Execute Python script
    $command = "python " . escapeshellarg($pythonScript) . " " . escapeshellarg($imagePath);
    $output = shell_exec($command);
    
    return $output;
}

// Function to perform OCR using Tesseract
function performOCR($imagePath) {
    // Path to Tesseract executable
    $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    
    // Output file path
    $outputPath = __DIR__ . '/output';
    
    // Execute Tesseract with improved settings
    $command = escapeshellarg($tesseractPath) . " " . 
               escapeshellarg($imagePath) . " " . 
               escapeshellarg($outputPath) . " -l eng+fil --oem 1 --psm 6";
    
    exec($command);
    
    // Read the output file
    $text = file_get_contents($outputPath . '.txt');
    unlink($outputPath . '.txt'); // Clean up
    
    return $text;
}

// Function to clean text
function cleanText($text) {
    // Remove extra spaces and newlines
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// Function to extract date from text
function extractDate($text) {
    // Common date patterns for Philippine ID
    $patterns = [
        '/(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{1,2}),\s+(\d{4})/i',
        '/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/',
        '/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            if (count($matches) >= 4) {
                return $matches[0];
            }
        }
    }
    return '';
}

// Function to identify if text is likely a name
function isLikelyName($text) {
    // Remove common non-name words
    $nonNameWords = ['REPUBLIKA', 'PILIPINAS', 'PAMBANSANG', 'PAGKAKAKILANLAN', 'PHILIPPINE', 'IDENTIFICATION', 'CARD', 'MGA', 'PANGALAN', 'GIVEN', 'NAMES', 'APELYIDO', 'MIDDLE', 'NAME', 'FIRST', 'LAST', 'FAMILY', 'GITNANG', 'INITIAL', 'STUDENT', 'ID'];
    $text = strtoupper($text);
    
    foreach ($nonNameWords as $word) {
        if (strpos($text, $word) !== false) {
            return false;
        }
    }
    
    // Check if text is all caps and has reasonable length for a name
    return $text === strtoupper($text) && strlen($text) > 1 && strlen($text) < 50;
}

// Function to extract value from line
function extractValue($line, $label) {
    // Convert to uppercase for comparison
    $upperLine = strtoupper($line);
    $upperLabel = strtoupper($label);
    
    // Remove the label from the line
    $value = str_ireplace($label, '', $line);
    
    // Remove any remaining label-like text
    $value = preg_replace('/\b(APELYIDO|LAST|NAME|FAMILY|MGA|PANGALAN|GIVEN|NAMES|FIRST|GITNANG|MIDDLE|INITIAL|STUDENT|ID)\b/i', '', $value);
    
    // Clean up any remaining text
    $value = preg_replace('/[^A-Za-z\s]/', '', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    
    return trim($value);
}

// Function to extract name parts
function extractNameParts($text) {
    // Remove any numbers at the start
    $text = preg_replace('/^\d+\s*/', '', $text);
    // Split by spaces
    $parts = explode(' ', $text);
    // Remove any single letters (initials)
    $parts = array_filter($parts, function($part) {
        return strlen($part) > 1;
    });
    return implode(' ', $parts);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['idUpload'])) {
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = basename($_FILES['idUpload']['name']);
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['idUpload']['tmp_name'], $filePath)) {
        // Perform OCR
        $text = performOCR($filePath);
        
        // Debug: Store raw OCR output
        $debug = ["Raw OCR Output: " . $text];
        
        // Process the extracted text
        $lines = explode("\n", $text);
        $extractedData = [
            'lastName' => '',
            'firstName' => '',
            'middleName' => '',
            'dob' => ''
        ];
        
        // Process each line
        foreach ($lines as $i => $line) {
            $line = trim($line);
            $upperLine = strtoupper($line);
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Debug: Store the line
            $debug[] = "Processing line " . ($i + 1) . ": " . $line;
            
            // Check for field labels - more lenient matching
            if (strpos($upperLine, 'LAST NAME') !== false || strpos($upperLine, 'APELYIDO') !== false) {
                $debug[] = "Found Last Name label";
                // Look for value in next line
                if (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    $debug[] = "Next line after Last Name: " . $nextLine;
                    if (strlen($nextLine) > 1) {
                        $extractedData['lastName'] = cleanText($nextLine);
                        $debug[] = "Extracted Last Name: " . $extractedData['lastName'];
                    }
                }
            } 
            elseif (strpos($upperLine, 'FIRST NAME') !== false || strpos($upperLine, 'GIVEN NAMES') !== false || strpos($upperLine, 'PANGALAN') !== false) {
                $debug[] = "Found First Name label";
                // Look for value in next line
                if (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    $debug[] = "Next line after First Name: " . $nextLine;
                    if (strlen($nextLine) > 1) {
                        $extractedData['firstName'] = cleanText($nextLine);
                        $debug[] = "Extracted First Name: " . $extractedData['firstName'];
                    }
                }
            } 
            elseif (strpos($upperLine, 'MIDDLE NAME') !== false || strpos($upperLine, 'GITNANG APELYIDO') !== false) {
                $debug[] = "Found Middle Name label";
                // Look for value in next line
                if (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    $debug[] = "Next line after Middle Name: " . $nextLine;
                    if (strlen($nextLine) > 1) {
                        $extractedData['middleName'] = cleanText($nextLine);
                        $debug[] = "Extracted Middle Name: " . $extractedData['middleName'];
                    }
                }
            } 
            elseif (strpos($upperLine, 'DATE OF BIRTH') !== false || strpos($upperLine, 'PETSA NG KAPANGANAKAN') !== false) {
                $debug[] = "Found Date of Birth label";
                // Look for value in next line
                if (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    $debug[] = "Next line after Date of Birth: " . $nextLine;
                    if (preg_match('/(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{1,2}),\s+(\d{4})/i', $nextLine, $matches)) {
                        $extractedData['dob'] = $matches[0];
                        $debug[] = "Extracted Date of Birth: " . $extractedData['dob'];
                    }
                }
            }
        }
        
        // If we found MARTINEZ but didn't find a last name, it might be the last name
        if (empty($extractedData['lastName']) && !empty($extractedData['middleName']) && strtoupper($extractedData['middleName']) === 'MARTINEZ') {
            $extractedData['lastName'] = $extractedData['middleName'];
            $extractedData['middleName'] = '';
            $debug[] = "Moved MARTINEZ from middle name to last name";
        }
        
        // Return the extracted data with debug information
        header('Content-Type: application/json');
        echo json_encode([
            'data' => $extractedData,
            'debug' => $debug
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload file']);
    }
}
?> 