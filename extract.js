document.addEventListener("DOMContentLoaded", function () {
    const idForm = document.getElementById("idUploadForm");
  
    if (idForm) {
      idForm.addEventListener("submit", function (e) {
        e.preventDefault();
  
        const formData = new FormData(this);
  
        fetch("upload_extract.php", {
          method: "POST",
          body: formData
        })
          .then(res => res.json())
          .then(data => {
            console.log("Extracted Data:", data);
  
            // Fill input fields with extracted data (if available)
            document.getElementById("firstName").value = data.firstName || "";
            document.getElementById("lastName").value = data.lastName || "";
            document.getElementById("middleInitial").value = data.middleInitial || "";
            document.getElementById("address").value = data.address || "";
          })
          .catch(err => {
            console.error("Error extracting data:", err);
          });
      });
    } else {
      console.warn("Form with ID 'idUploadForm' not found!");
    }
  });
  