-- First, drop the foreign key constraints
ALTER TABLE appointments
DROP FOREIGN KEY IF EXISTS fk_appointments_student,
DROP FOREIGN KEY IF EXISTS fk_appointments_doctor,
DROP FOREIGN KEY IF EXISTS fk_appointments_slot;

-- First, update any existing records where studentID is 0
UPDATE appointments a
JOIN students s ON s.Email = (
    SELECT Email FROM students ORDER BY StudentID LIMIT 1
)
SET a.studentID = s.StudentID
WHERE a.studentID = 0;

-- Now modify the table structure
ALTER TABLE appointments
MODIFY COLUMN studentID INT NOT NULL,
ADD CONSTRAINT fk_appointments_student 
    FOREIGN KEY (studentID) REFERENCES students(StudentID)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- Re-add other foreign key constraints
ALTER TABLE appointments
ADD CONSTRAINT fk_appointments_doctor 
    FOREIGN KEY (doctorID) REFERENCES doctors(DoctorID)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
ADD CONSTRAINT fk_appointments_slot 
    FOREIGN KEY (slotID) REFERENCES timeslots(SlotID)
    ON DELETE RESTRICT
    ON UPDATE CASCADE; 