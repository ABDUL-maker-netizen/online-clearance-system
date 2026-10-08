<?php
// No need to start session here - dashboard.php already handles it

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

$message = "";

$student = mysqli_fetch_assoc(
    mysqli_query($conn,
    "SELECT * FROM students WHERE id='$student_id'")
);

if(isset($_POST['update_photo'])){

    $passport = $_FILES['passport']['name'];
    $tmp = $_FILES['passport']['tmp_name'];

    $allowed = ['jpg','jpeg','png'];
    $extension = strtolower(pathinfo($passport, PATHINFO_EXTENSION));

    if(!in_array($extension, $allowed)){

        $message = "<div class='alert alert-danger'>
        Only JPG, JPEG and PNG files are allowed.
        </div>";

    }else{

        $new_passport = time().'_'.$passport;

        if(move_uploaded_file(
            $tmp,
            '../assets/uploads/'.$new_passport
        )){

            /* Delete old passport */
            $old = $student['passport'];

            if(!empty($old) && file_exists("../assets/uploads/".$old)){
                unlink("../assets/uploads/".$old);
            }

            mysqli_query($conn,
            "UPDATE students
            SET passport='$new_passport'
            WHERE id='$student_id'");

            $message = "<div class='alert alert-success'>
            Passport updated successfully.
            </div>";

            // Refresh student data
            $student['passport'] = $new_passport;
        }
    }
}
?>

<!-- =========================================================
     EDIT PHOTO PAGE CONTENT - UPDATED WITH MATCHING DESIGN
     This is included inside dashboard.php
========================================================= -->

<style>
/* Matching design styles */
.edit-photo-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 20px;
}

/* Page Header */
.page-header-photo {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    margin-top: 8px;
}

.page-header-photo .header-icon {
    width: 48px;
    height: 48px;
    border-radius: 9999px;
    background-color: rgba(30, 64, 175, 0.1);
    color: #1e40af;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-photo .header-icon i {
    font-size: 2rem;
    line-height: 1;
}

.page-header-photo h1 {
    font-size: 32px;
    font-weight: 700;
    line-height: 40px;
    letter-spacing: -0.02em;
    color: #111827;
    margin: 0;
    font-family: 'Hanken Grotesk', sans-serif;
}

.page-header-photo p {
    font-size: 16px;
    font-weight: 400;
    line-height: 24px;
    color: #4b5563;
    margin-top: 4px;
    margin-bottom: 0;
}

/* Card styling */
.card-custom {
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

/* Alert styling */
.alert {
    border-radius: 0.5rem;
    padding: 12px 16px;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d1fae5;
    border-color: #a7f3d0;
    color: #065f46;
}

.alert-danger {
    background-color: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
}

.alert-warning {
    background-color: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
}

/* Photo Display */
.photo-display {
    text-align: center;
    margin-bottom: 24px;
}

.photo-display .photo-frame {
    position: relative;
    display: inline-block;
}

.photo-display .photo-frame img {
    width: 150px;
    height: 150px;
    border-radius: 9999px;
    border: 4px solid #e5e7eb;
    object-fit: cover;
}

.photo-display .photo-frame .placeholder {
    width: 150px;
    height: 150px;
    border-radius: 9999px;
    border: 4px solid #e5e7eb;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 64px;
    color: #9ca3af;
}

.photo-display .photo-label {
    margin-top: 12px;
    color: #4b5563;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

/* Form controls */
.form-control {
    display: block;
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    box-sizing: border-box;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
    transition: all 0.15s ease;
}

.form-control:focus {
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    outline: none;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #111827;
    font-family: 'Hanken Grotesk', sans-serif;
    font-size: 14px;
}

.form-hint {
    color: #4b5563;
    font-size: 12px;
    font-family: 'Hanken Grotesk', sans-serif;
}

/* Buttons */
.btn-update-photo {
    background: #1e40af;
    color: #ffffff;
    padding: 8px 24px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 500;
    transition: all 0.15s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Hanken Grotesk', sans-serif;
    font-size: 14px;
}

.btn-update-photo:hover {
    background: #1e3a8a;
    transform: scale(0.98);
}

.btn-back {
    background: #4b5563;
    color: #ffffff;
    padding: 8px 20px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 500;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Hanken Grotesk', sans-serif;
    font-size: 14px;
}

.btn-back:hover {
    background: #374151;
    color: #ffffff;
    text-decoration: none;
    transform: scale(0.98);
}

/* Button group */
.button-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .edit-photo-page {
        padding: 12px;
    }
    
    .page-header-photo {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .page-header-photo h1 {
        font-size: 24px;
        line-height: 32px;
    }
    
    .page-header-photo p {
        font-size: 14px;
    }
    
    .card-custom {
        padding: 16px;
    }
    
    .photo-display .photo-frame img,
    .photo-display .photo-frame .placeholder {
        width: 120px;
        height: 120px;
    }
    
    .photo-display .photo-frame .placeholder {
        font-size: 48px;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .button-group .btn-update-photo,
    .button-group .btn-back {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .page-header-photo h1 {
        font-size: 20px;
    }
    
    .page-header-photo .header-icon {
        width: 40px;
        height: 40px;
    }
    
    .page-header-photo .header-icon i {
        font-size: 1.5rem;
    }
    
    .card-custom {
        padding: 12px;
    }
    
    .photo-display .photo-frame img,
    .photo-display .photo-frame .placeholder {
        width: 100px;
        height: 100px;
    }
    
    .photo-display .photo-frame .placeholder {
        font-size: 40px;
    }
    
    .form-control {
        font-size: 16px; /* Prevents iOS zoom */
    }
    
    .btn-update-photo,
    .btn-back {
        font-size: 13px;
        padding: 6px 16px;
    }
}

@media (max-width: 400px) {
    .page-header-photo h1 {
        font-size: 18px;
    }
    
    .photo-display .photo-frame img,
    .photo-display .photo-frame .placeholder {
        width: 80px;
        height: 80px;
    }
    
    .photo-display .photo-frame .placeholder {
        font-size: 32px;
    }
    
    .photo-display .photo-label {
        font-size: 12px;
    }
}

/* Breadcrumb */
.breadcrumb-custom {
    color: #4b5563;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.breadcrumb-custom a {
    color: #1e40af;
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-custom a:hover {
    text-decoration: underline;
}

.breadcrumb-custom i {
    margin: 0 4px;
}

/* File input wrapper */
.file-input-wrapper {
    margin-bottom: 16px;
}

.file-input-wrapper .form-control {
    padding: 8px 12px;
}

/* Photo upload area */
.photo-upload-area {
    border: 2px dashed #e5e7eb;
    border-radius: 0.5rem;
    padding: 24px;
    text-align: center;
    transition: all 0.15s ease;
    cursor: pointer;
}

.photo-upload-area:hover {
    border-color: #1e40af;
    background: #f8f9ff;
}

.photo-upload-area .upload-icon {
    font-size: 48px;
    color: #9ca3af;
    display: block;
    margin-bottom: 8px;
}

.photo-upload-area .upload-text {
    color: #4b5563;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.photo-upload-area .upload-hint {
    color: #9ca3af;
    font-size: 12px;
    font-family: 'Hanken Grotesk', sans-serif;
}

/* Hide default file input */
.custom-file-input {
    display: none;
}
</style>

<div class="edit-photo-page">

    <!-- Page Header -->
    <div class="page-header-photo">
        <div class="header-icon">
            <i class="bi bi-camera"></i>
        </div>
        <div>
            <h1>Edit Passport Photo</h1>
            <p>Update your profile picture. Only JPG, JPEG, and PNG files are allowed.</p>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card-custom">

        <?php echo $message; ?>

        <!-- Current Photo Display -->
        <div class="photo-display">
            <div class="photo-frame">
                <?php if (!empty($student['passport']) && file_exists("../assets/uploads/".$student['passport'])): ?>
                    <img src="../assets/uploads/<?php echo htmlspecialchars($student['passport']); ?>" alt="Current Passport Photo">
                <?php else: ?>
                    <div class="placeholder">
                        <i class="bi bi-person"></i>
                    </div>
                <?php endif; ?>
            </div>
            <p class="photo-label">Current Photo</p>
        </div>

        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data">

            <div class="file-input-wrapper">
                <label class="form-label">Choose New Passport</label>
                
                <!-- Custom file upload area -->
                <div class="photo-upload-area" id="uploadArea" onclick="document.getElementById('passportInput').click()">
                    <i class="bi bi-cloud-upload upload-icon"></i>
                    <div class="upload-text">Click to upload or drag and drop</div>
                    <div class="upload-hint">JPG, JPEG, PNG (Max 5MB)</div>
                </div>
                
                <input type="file"
                       name="passport"
                       id="passportInput"
                       class="custom-file-input"
                       accept="image/jpeg,image/png"
                       required
                       onchange="handleFileSelect(event)">
                
                <!-- Selected file name display -->
                <div id="fileNameDisplay" style="display:none; margin-top:8px; padding:8px 12px; background:#f8f9fa; border-radius:0.5rem; color:#111827; font-size:14px; font-family:'Hanken Grotesk', sans-serif;">
                    <i class="bi bi-file-image" style="color:#1e40af;"></i>
                    <span id="selectedFileName"></span>
                </div>
            </div>

            <div class="button-group">
                <button type="submit"
                        name="update_photo"
                        class="btn-update-photo">
                    <i class="bi bi-upload"></i> Update Photo
                </button>
                <a href="dashboard.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

        </form>

    </div>

</div>

<script>
// Handle file selection for custom upload
function handleFileSelect(event) {
    var file = event.target.files[0];
    var fileNameDisplay = document.getElementById('fileNameDisplay');
    var selectedFileName = document.getElementById('selectedFileName');
    var uploadArea = document.getElementById('uploadArea');
    
    if (file) {
        // Validate file type
        var validTypes = ['image/jpeg', 'image/png'];
        if (!validTypes.includes(file.type)) {
            alert('Only JPG, JPEG, and PNG files are allowed.');
            event.target.value = '';
            fileNameDisplay.style.display = 'none';
            return;
        }
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB.');
            event.target.value = '';
            fileNameDisplay.style.display = 'none';
            return;
        }
        
        // Display file name
        selectedFileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        fileNameDisplay.style.display = 'block';
        uploadArea.style.borderColor = '#1e40af';
        uploadArea.style.background = '#f8f9ff';
    } else {
        fileNameDisplay.style.display = 'none';
        uploadArea.style.borderColor = '#e5e7eb';
        uploadArea.style.background = 'transparent';
    }
}

// Prevent default drag behaviors
document.addEventListener('dragover', function(e) {
    e.preventDefault();
});

document.addEventListener('drop', function(e) {
    e.preventDefault();
});

// Handle drag and drop
var uploadArea = document.getElementById('uploadArea');
var fileInput = document.getElementById('passportInput');

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = '#1e40af';
    this.style.background = '#f8f9ff';
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.style.borderColor = '#e5e7eb';
    this.style.background = 'transparent';
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#e5e7eb';
    this.style.background = 'transparent';
    
    var files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect({ target: fileInput });
    }
});

// Also trigger file selection on click of the upload area
uploadArea.addEventListener('click', function(e) {
    // Prevent the click from bubbling up if the user clicks inside
    // The onclick attribute already handles this, but we keep it clean
});
</script>