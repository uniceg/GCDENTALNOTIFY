// Load all doctors
window.addEventListener('DOMContentLoaded', () => {
    fetch('getDoctors.php')
        .then(response => response.json())
        .then(doctors => {
            displayDoctors(doctors);
        })
        .catch(error => {
            console.error('Error fetching doctors:', error);
        });
});

// Prevent selecting past dates
document.addEventListener('DOMContentLoaded', function () {
    const dateInput = document.getElementById('getDayWeek');
    if (dateInput) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0'); 
        const minDate = `${year}-${month}-${day}`;
        dateInput.setAttribute('min', minDate);
    }
});

// Display all doctors
function displayDoctors(doctors) {
    const container = document.getElementById('doctors-container');
    container.innerHTML = doctors.map((doctor, index) => {
        // Set image and profession per doctor
        let imageFile = '';
        let profession = 'General Practitioner';  // Default profession
        let description = 'Provides general medical care for a wide range of conditions.';

        // Set specific image and profession for each doctor based on exact names in database
        if (doctor.FirstName === 'John' && doctor.LastName === 'Doe') {
            imageFile = 'Doc1.jpg';
            profession = 'Pediatrician';
            description = 'Children\'s health specialist';
        } else if (doctor.FirstName === 'Will' && doctor.LastName === 'Smith') {
            imageFile = 'Doc2.jpg';
            profession = 'Cardiologist';
            description = 'Heart specialist';
        } else if (doctor.FirstName === 'Jane' && doctor.LastName === 'Willer') {
            imageFile = 'Doc3.jpg';
            profession = 'Orthopedic Surgeon';
            description = 'Bone and joint specialist';
        } else if (doctor.FirstName === 'William' && doctor.LastName === 'Andrews') {
            imageFile = 'Doc4.jpg';
            profession = 'Dermatologist';
            description = 'Skin specialist';
        } else if (doctor.FirstName === 'Stephen' && doctor.LastName === 'Strange') {
            imageFile = 'Doc5.jpg';
            profession = 'Neurologist';
            description = 'Brain and nervous system specialist';
        } else if (doctor.FirstName === 'Kim' && doctor.LastName === 'Lee') {
            imageFile = 'Doc6.jpg';
            profession = 'Endocrinologist';
            description = 'Hormone specialist';
        } else {
            imageFile = 'DefaultDoc.jpg';
        }

        doctor.ImageFile = imageFile;
        doctor.Profession = profession;
        doctor.Description = description;

        return `
            <div class="box">
                <img src="${doctor.ImageFile}" alt="${doctor.FirstName} ${doctor.LastName}" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 2px solid #03777e;">
                <h3>Dr. ${doctor.FirstName} ${doctor.LastName}</h3>
                <p style="font-weight: bold; color: #03777e;">${doctor.Profession}</p>
                <p style="font-size: 0.9rem; color: #555;">${doctor.Description}</p>
                <button class="btn" onclick="viewDoctor(${index})">View</button>
            </div>
        `;
    }).join('');
}

// View doctor details
function viewDoctor(index) {
    fetch('getDoctors.php')
        .then(response => response.json())
        .then(doctors => {
            const doctor = doctors[index];

            // Set specific image and profession per doctor (use specific image paths)
            let imagePath = 'DefaultDoc.jpg';
            let profession = 'General Practitioner';
            let description = 'Provides general medical care for a wide range of conditions.';

            // Assign specific professions and images based on exact names in database
            if (doctor.FirstName === 'John' && doctor.LastName === 'Doe') {
                imagePath = 'Doc1.jpg';
                profession = 'Pediatrician';
                description = 'Specialist in children\'s health, treating common <br> childhood illnesses and monitoring development.';
            } else if (doctor.FirstName === 'Will' && doctor.LastName === 'Smith') {
                imagePath = 'Doc2.jpg';
                profession = 'Cardiologist';
                description = 'Heart specialist focusing on cardiovascular health,<br> diagnosing and treating heart conditions.';
            } else if (doctor.FirstName === 'Jane' && doctor.LastName === 'Willer') {
                imagePath = 'Doc3.jpg';
                profession = 'Orthopedic Surgeon';
                description = 'Specialist in bone and joint health, treating fractures, <br> sprains, and other musculoskeletal issues.';
            } else if (doctor.FirstName === 'William' && doctor.LastName === 'Andrews') {
                imagePath = 'Doc4.jpg';
                profession = 'Dermatologist';
                description = 'Skin specialist treating acne, rashes,<br> and other skin, hair, and nail conditions.';
            } else if (doctor.FirstName === 'Stephen' && doctor.LastName === 'Strange') {
                imagePath = 'Doc5.jpg';
                profession = 'Neurologist';
                description = 'Specialist in diagnosing and treating disorders of <br> the nervous system, including the brain and spinal cord.';
            } else if (doctor.FirstName === 'Kim' && doctor.LastName === 'Lee') {
                imagePath = 'Doc6.jpg';
                profession = 'Endocrinologist';
                description = 'Specialist in hormone-related disorders, including <br> diabetes, thyroid issues, and metabolic disorders.';
            }

            const modalContent = document.querySelector('.modal-content');
            modalContent.innerHTML = `
                <div class="form-container">
                    <div class="form-box" style="text-align: center;">
                        <h1>Doctor Details</h1>
                        <img src="${imagePath}" alt="Doctor Photo" class="doctor-photo" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #03777e;">
                        <h2 style="margin-top: 10px;">Dr. ${doctor.FirstName} ${doctor.LastName}</h2>
                        <p style="font-weight: bold; color: #03777e; font-size: 1.2rem;">${profession}</p>
                        <p style="color: #444; padding: 0 20px; margin-top: 10px;">${description}</p>

                        <br>
                        <h2>Email:</h2>
                        <p>${doctor.Email || 'Not available'}</p>
                        <br>
                        <h2>Contact:</h2>
                        <p>${doctor.ContactNumber || 'Not available'}</p>
                        <br>
                        <h2>Schedule:</h2>
                        ${doctor.timeSlots && doctor.timeSlots.length > 0 ? doctor.timeSlots.map(slot => `
                            <p>${slot.AvailableDay}: ${slot.StartTime} - ${slot.EndTime}</p>
                        `).join('') : '<p>No scheduled hours available</p>'}
                        <br>
                        <button class="btn-red" onclick="closeModal()">Close</button>
                    </div>
                </div>
            `;
            showModal();
        })
        .catch(error => {
            console.error('Error fetching doctor details:', error);
        });
}

// Select available slots based on the selected date
document.getElementById('dateForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const dateInput = document.getElementById('getDayWeek').value;
    const filteredDoctorsContainer = document.getElementById('filteredDoctors');

    if (dateInput) {
        const date = new Date(dateInput);
        const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const dayOfWeek = daysOfWeek[date.getDay()];

        fetch('getDoctors.php?isAvailable=1')
            .then(response => response.json())
            .then(doctors => {
                const filteredDoctors = doctors.filter(doctor => {
                    const availableSlots = doctor.timeSlots.filter(slot => slot.AvailableDay === dayOfWeek && slot.IsAvailable);
                    return availableSlots.length > 0;
                });

                if (filteredDoctors.length === 0) {
                    filteredDoctorsContainer.innerHTML = `<p style="font-weight: bold; text-align: center;">No available slots for this day.</p>`;
                    document.querySelector('.modal-content').innerHTML = `<p>No available timeslots for this day.</p>`;
                    showModal();
                } else {
                    filteredDoctorsContainer.innerHTML = filteredDoctors.map(doctor => `
                        <div class="box-container">
                            <div class="box">
                                <img src="${doctor.ImageFile}" alt="Doctor's Photo">
                                <h3>${doctor.FirstName} ${doctor.LastName}</h3>
                                ${doctor.timeSlots.map(slot => slot.AvailableDay === dayOfWeek && slot.IsAvailable ? `
                                    <p>${slot.StartTime} - ${slot.EndTime}</p>
                                    <button class="btn" onclick="selectSlot(${doctor.DoctorID}, ${slot.SlotID}, '${dateInput}', '${slot.StartTime}', '${slot.EndTime}')">Book Slot</button>
                                ` : '').join('')}
                            </div>
                        </div>    
                    `).join('');
                }
            })
            .catch(error => console.error('Error:', error));
    } else {
        alert('Please select a date.');
    }
});

// Modal
function showModal() {
    document.getElementById('myModal').style.display = 'block';
    document.body.style.overflow = "hidden";
}
function closeModal() {
    document.getElementById('myModal').style.display = 'none';
    document.body.style.overflow = "";
}
window.onclick = function(event) {
    const modal = document.getElementById('myModal');
    if (event.target === modal) {
        closeModal();
    }
};

// Book appointment
function selectSlot(doctorID, slotID, appointmentDate, startTime, endTime) {
    fetch('getUser.php')
        .then(response => response.json())
        .then(data => {
            const userID = data.user_id;
            
            if (!userID) {
                alert('You must be logged in to book an appointment.');
                return;
            }

            const modalContent = document.querySelector('.modal-content');
            modalContent.innerHTML = `
                <div class="form-container">
                    <div class="form-box">
                        <h1>Confirm Appointment</h1>
                        <br>
                        <h3>Appointment Date:</h3>
                        <p>${appointmentDate}</p>
                        <br>
                        <h3>Time Slot:</h3>
                        <p>${startTime} - ${endTime}</p>
                        <br>
                        <h3>Reason for Appointment:</h3>
                        <select id="appointmentReason">
                            <option value="Examination">Medical Examination</option>
                            <option value="Clearance">Medical Clearance</option>
                            <option value="CheckUp">Oral Care Check-Up</option>
                            <option value="Certificate">Medical Certificate</option>
                        </select>
                        <br><br><br>
                        <button class="btn" id="confirmAppointment">Confirm Booking</button>
                        <br><br>    
                        <button class="btn-red" onclick="closeModal()">Back</button>
                    </div>
                </div>
            `;
            showModal();

            document.getElementById('confirmAppointment').addEventListener('click', function () {
                const reason = document.getElementById('appointmentReason').value; 
                const appointmentData = {
                    userID: userID, 
                    doctorID,
                    slotID,
                    appointmentDate,
                    appointmentReason: reason 
                };

                fetch('bookAppointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(appointmentData)
                })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        closeModal();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to book appointment.');
                    });
            });
        })
        .catch(error => {
            console.error('Error fetching user ID:', error);
            alert('Failed to fetch user ID.');
        });
}
