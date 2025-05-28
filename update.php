<?php
session_start();
include 'config.php';

// Ensure the user is logged in
if (!isset($_SESSION['studentID'])) {
    header('Location: login.php');
    exit;
}

$studentID = $_SESSION['studentID'];

// Fetch current user data
$query = "SELECT * FROM students WHERE StudentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $studentID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    die("User not found.");
}

// Update user data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $conn->real_escape_string($_POST['firstName']);
    $lastName = $conn->real_escape_string($_POST['lastName']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $contactNumber = $conn->real_escape_string($_POST['contactNumber']);
    $parentGuardian = $conn->real_escape_string($_POST['parentGuardian']);
    $parentContact = $conn->real_escape_string($_POST['parentContact']);
    $emergencyContactName = $conn->real_escape_string($_POST['emergencyContactName'] ?? '');
    $emergencyContactRelationship = $conn->real_escape_string($_POST['emergencyContactRelationship'] ?? '');
    $emergencyContactNumber = $conn->real_escape_string($_POST['emergencyContactNumber'] ?? '');
    $bloodType = $conn->real_escape_string($_POST['bloodType'] ?? '');
    $allergies = $conn->real_escape_string($_POST['allergies'] ?? '');
    $medicalConditions = $conn->real_escape_string($_POST['medicalConditions'] ?? '');
    $medications = $conn->real_escape_string($_POST['medications'] ?? '');
    
    // Handle profile photo upload
    $profilePhotoPath = $user['profilePhoto']; // Default to current photo
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (in_array($_FILES['profilePhoto']['type'], $allowedTypes) && $_FILES['profilePhoto']['size'] <= 2 * 1024 * 1024) {
            $ext = pathinfo($_FILES['profilePhoto']['name'], PATHINFO_EXTENSION);
            $newFileName = 'uploads/profile_' . $studentID . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $newFileName)) {
                $profilePhotoPath = $newFileName;
            }
        }
    }

    // Handle password update
    $passwordUpdate = "";
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $passwordUpdate = ", password = '$password'";
    }

    // Update query - now including profilePhoto
    $updateQuery = "UPDATE students SET 
                    FirstName = '$firstName',
                    LastName = '$lastName',
                    email = '$email',
                    address = '$address',
                    GENDER = '$gender',
                    ContactNumber = '$contactNumber',
                    parentGuardian = '$parentGuardian',
                    parentContact = '$parentContact',
                    emergencyContactName = '$emergencyContactName',
                    emergencyContactRelationship = '$emergencyContactRelationship',
                    emergencyContactNumber = '$emergencyContactNumber',
                    bloodType = '$bloodType',
                    allergies = '$allergies',
                    medicalConditions = '$medicalConditions',
                    medications = '$medications',
                    profilePhoto = '$profilePhotoPath'
                    $passwordUpdate
                    WHERE StudentID = '$studentID'";

    if ($conn->query($updateQuery) === TRUE) {
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: studentHome.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Error updating profile: " . $conn->error;
        header("Location: studentHome.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    <style>
        body, html {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .title {
            font-size: 30px;
            font-weight: bold;
            color: #000000;
            background-color: #04AA6D;
            padding: 10px 20px; 
            border-radius: 8px 8px 0 0;
            width: 100%; 
            box-sizing: border-box; 
        }

        .form-container {
            background: white;
            padding: 30px; 
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 500px; 
            width: 100%; 
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-container h1 {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-container input, 
        .form-container select {
            width: 100%; 
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; 
        }

        .form-container button {
            background-color: #04AA6D;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 15px;
            width: 100%; 
        }

        .form-container button:hover {
            background-color: #039f5a;
            transition: all .50s ease;
        }

        /* Update Profile Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .modal-header {
            background: #011f4b;
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.25rem 1.5rem;
            border: none;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-floating > .form-control,
        .form-floating > .form-select {
            height: 48px;
            padding: 1rem 0.75rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-floating > .form-control:focus,
        .form-floating > .form-select:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.1);
        }

        .form-floating > label {
            padding: 1rem 0.75rem;
            color: #666;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #357abd 0%, #2c6aa0 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.2);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1 class="title">Update Profile</h1>
        <form method="POST">
            <label for="firstName">First Name</label>
            <input type="text" id="firstName" name="firstName" value="<?= htmlspecialchars($user['FirstName'] ?? '') ?>" required>
            
            <label for="lastName">Last Name</label>
            <input type="text" id="lastName" name="lastName" value="<?= htmlspecialchars($user['LastName'] ?? '') ?>" required>
            
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            
            <label for="address">Address</label>
            <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" required>
            
            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
                <option value="" disabled>Select Gender</option>
                <option value="Male" <?= ($user['GENDER'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($user['GENDER'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= ($user['GENDER'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
            
            <label for="contactNumber">Contact Number</label>
            <input type="number" id="contactNumber" name="contactNumber" value="<?= htmlspecialchars($user['ContactNumber'] ?? '') ?>" required>

            <label for="parentGuardian">Parent/Guardian</label>
            <input type="text" id="parentGuardian" name="parentGuardian" value="<?= htmlspecialchars($user['parentGuardian'] ?? '') ?>" required>

            <label for="parentContact">Parent/Guardian Contact Info</label>
            <input type="number" id="parentContact" name="parentContact" value="<?= htmlspecialchars($user['parentContact'] ?? '') ?>" required>

            <label for="emergencyContactName">Emergency Contact Name</label>
            <input type="text" id="emergencyContactName" name="emergencyContactName" value="<?= htmlspecialchars($user['emergencyContactName'] ?? '') ?>" required>

            <label for="emergencyContactRelationship">Emergency Contact Relationship</label>
            <input type="text" id="emergencyContactRelationship" name="emergencyContactRelationship" value="<?= htmlspecialchars($user['emergencyContactRelationship'] ?? '') ?>" required>

            <label for="emergencyContactNumber">Emergency Contact Number</label>
            <input type="number" id="emergencyContactNumber" name="emergencyContactNumber" value="<?= htmlspecialchars($user['emergencyContactNumber'] ?? '') ?>" required>

            <label for="bloodType">Blood Type</label>
            <input type="text" id="bloodType" name="bloodType" value="<?= htmlspecialchars($user['bloodType'] ?? '') ?>" required>

            <label for="allergies">Allergies</label>
            <input type="text" id="allergies" name="allergies" value="<?= htmlspecialchars($user['allergies'] ?? '') ?>" required>

            <label for="medicalConditions">Medical Conditions</label>
            <input type="text" id="medicalConditions" name="medicalConditions" value="<?= htmlspecialchars($user['medicalConditions'] ?? '') ?>" required>

            <label for="medications">Medications</label>
            <input type="text" id="medications" name="medications" value="<?= htmlspecialchars($user['medications'] ?? '') ?>" required>

            <label for="password">Password (leave blank to keep current)</label>
            <input type="password" id="password" name="password">

            <button type="submit">Update Profile</button>
        </form>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateProfileModalLabel">Update Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="update.php" id="updateProfileForm" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="firstName" name="firstName" required>
                                    <label for="firstName">First Name</label>
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="lastName" name="lastName" required>
                                    <label for="lastName">Last Name</label>
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" required>
                            <label for="email">Email Address</label>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="address" name="address" required>
                            <label for="address">Address</label>
                            <div class="invalid-feedback">Please enter address</div>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <label for="gender">Gender</label>
                            <div class="invalid-feedback">Please select gender</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="contactNumber" name="contactNumber" required pattern="[0-9]{11}">
                            <label for="contactNumber">Contact Number (11 digits)</label>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="parentGuardian" name="parentGuardian" required>
                            <label for="parentGuardian">Parent/Guardian</label>
                            <div class="invalid-feedback">Please enter parent/guardian name</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="parentContact" name="parentContact" required pattern="[0-9]{11}">
                            <label for="parentContact">Parent/Guardian Contact (11 digits)</label>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="emergencyContactName" name="emergencyContactName" required>
                            <label for="emergencyContactName">Emergency Contact Name</label>
                            <div class="invalid-feedback">Please enter emergency contact name</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="emergencyContactRelationship" name="emergencyContactRelationship" required>
                            <label for="emergencyContactRelationship">Emergency Contact Relationship</label>
                            <div class="invalid-feedback">Please enter emergency contact relationship</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="emergencyContactNumber" name="emergencyContactNumber" required pattern="[0-9]{11}">
                            <label for="emergencyContactNumber">Emergency Contact Number (11 digits)</label>
                            <div class="invalid-feedback">Please enter a valid 11-digit emergency contact number</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="bloodType" name="bloodType" required>
                            <label for="bloodType">Blood Type</label>
                            <div class="invalid-feedback">Please enter blood type</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="allergies" name="allergies" required>
                            <label for="allergies">Allergies</label>
                            <div class="invalid-feedback">Please enter allergies</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="medicalConditions" name="medicalConditions" required>
                            <label for="medicalConditions">Medical Conditions</label>
                            <div class="invalid-feedback">Please enter medical conditions</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="medications" name="medications" required>
                            <label for="medications">Medications</label>
                            <div class="invalid-feedback">Please enter medications</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password">
                            <label for="password">New Password (leave blank to keep current)</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the update profile modal
        const updateProfileModal = new bootstrap.Modal(document.getElementById('updateProfileModal'), {
            backdrop: 'static',
            keyboard: false
        });

        // Form validation
        const updateProfileForm = document.getElementById('updateProfileForm');
        updateProfileForm.addEventListener('submit', function(event) {
            if (!updateProfileForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            updateProfileForm.classList.add('was-validated');
        }, { passive: true });

        // Phone number validation
        const phoneInputs = document.querySelectorAll('#updateProfileForm input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) {
                    value = value.slice(0, 11);
                }
                e.target.value = value;
            });
        });

        // Function to populate form with user data
        window.populateUpdateForm = function(userData) {
            document.getElementById('firstName').value = userData.FirstName || '';
            document.getElementById('lastName').value = userData.LastName || '';
            document.getElementById('email').value = userData.email || '';
            document.getElementById('address').value = userData.address || '';
            document.getElementById('gender').value = userData.GENDER || '';
            document.getElementById('contactNumber').value = userData.ContactNumber || '';
            document.getElementById('parentGuardian').value = userData.parentGuardian || '';
            document.getElementById('parentContact').value = userData.parentContact || '';
            document.getElementById('emergencyContactName').value = userData.emergencyContactName || '';
            document.getElementById('emergencyContactRelationship').value = userData.emergencyContactRelationship || '';
            document.getElementById('emergencyContactNumber').value = userData.emergencyContactNumber || '';
            document.getElementById('bloodType').value = userData.bloodType || '';
            document.getElementById('allergies').value = userData.allergies || '';
            document.getElementById('medicalConditions').value = userData.medicalConditions || '';
            document.getElementById('medications').value = userData.medications || '';
            document.getElementById('password').value = '';
            
            updateProfileModal.show();
        };

        // Clear form when modal is closed
        document.getElementById('updateProfileModal').addEventListener('hidden.bs.modal', function () {
            updateProfileForm.reset();
            updateProfileForm.classList.remove('was-validated');
        });
    });
    </script>

    <button type="button" class="btn btn-primary" onclick="populateUpdateForm(<?php echo json_encode($user); ?>)">
        <i class="bi bi-pencil-square"></i> Update Profile
    </button>
</body>
</html>
