<?php
session_start();
include '../includes/config.php';

/* ==============================
   CHECK SESSION - Support both old and new session variables
============================== */
$officer_id = isset($_SESSION['officer_id']) ? $_SESSION['officer_id'] : (isset($_SESSION['office_officer_id']) ? $_SESSION['office_officer_id'] : null);

if(!$officer_id){
    header("Location: login.php");
    exit();
}

$message = "";

/* FETCH OFFICER - FIXED */
$officer_q = mysqli_query($conn,
"SELECT * FROM officers WHERE id='$officer_id'");
$officer = mysqli_fetch_assoc($officer_q);

if(!$officer){
    die("Officer not found");
}

/* SAVE SIGNATURE */
if(isset($_POST['save'])){

    if(!empty($_FILES['signature']['name'])){

        $ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));

        if(!in_array($ext, ['png','jpg','jpeg'])){
            $message = "<div class='alert alert-danger'>Invalid file type</div>";
        } else {

            $signature = time().'_signature_'.$officer_id.'.'.$ext;
            $path = "../assets/uploads/signatures/".$signature;

            // Create directory if not exists
            if(!file_exists("../assets/uploads/signatures/")){
                mkdir("../assets/uploads/signatures/", 0777, true);
            }

            if(move_uploaded_file($_FILES['signature']['tmp_name'], $path)){

                // Update officers table
                mysqli_query($conn,
                "UPDATE officers
                SET digital_signature='$signature'
                WHERE id='$officer_id'");

                // Also update department_officers if exists
                mysqli_query($conn,
                "UPDATE department_officers
                SET digital_signature='$signature'
                WHERE id='$officer_id'");

                // Also update faculty_officers if exists
                mysqli_query($conn,
                "UPDATE faculty_officers
                SET digital_signature='$signature'
                WHERE id='$officer_id'");

                $message = "<div class='alert alert-success'>Signature Updated Successfully <a href='dashboard.php' class='btn btn-sm ms-2' style='background:#006c49;color:#fff;border:none;border-radius:0.375rem;padding:4px 14px;font-family:\"Hanken Grotesk\",sans-serif;text-decoration:none;'>Back</a></div>";
            }
        }

    } else {
        $message = "<div class='alert alert-warning'>Please upload a signature</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Officer Profile | Online Clearance System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Matching design styles */
        :root {
            --primary-blue: #1e40af;
            --primary-dark: #1e3a8a;
            --bg-light: #f8f9ff;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.05);
            --border-color: #e5e7eb;
            --text-dark: #111827;
            --text-muted: #4b5563;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--bg-light);
            font-family: 'Hanken Grotesk', sans-serif;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .container {
            max-width: 600px;
            padding: 24px 20px;
            margin: 0 auto;
        }
        
        /* Card */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            background: #ffffff;
            padding: 32px;
        }
        
        @media (max-width: 576px) {
            .card {
                padding: 20px;
            }
            .container {
                padding: 16px 12px;
            }
        }
        
        @media (max-width: 400px) {
            .card {
                padding: 16px;
            }
            .container {
                padding: 12px 8px;
            }
        }
        
        /* Header */
        .card-header-custom {
            margin-bottom: 24px;
        }
        
        .card-header-custom h3 {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 24px;
            margin: 0 0 4px 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .card-header-custom h3 i {
            color: var(--primary-blue);
            margin-right: 10px;
        }
        
        .card-header-custom p {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        @media (max-width: 576px) {
            .card-header-custom h3 {
                font-size: 20px;
            }
            .card-header-custom p {
                font-size: 13px;
            }
        }
        
        @media (max-width: 400px) {
            .card-header-custom h3 {
                font-size: 18px;
            }
        }
        
        /* Alert styling */
        .alert {
            border-radius: 0.5rem;
            padding: 12px 16px;
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
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
        
        .alert .btn {
            flex-shrink: 0;
        }
        
        /* Form */
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 14px;
            margin-bottom: 6px;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-sizing: border-box;
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
            transition: all 0.15s ease;
            background: #ffffff;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }
        
        .form-control::file-selector-button {
            padding: 6px 14px;
            border-radius: 0.375rem;
            border: none;
            background: var(--primary-blue);
            color: #ffffff;
            font-weight: 500;
            font-family: 'Hanken Grotesk', sans-serif;
            cursor: pointer;
            transition: all 0.15s ease;
            margin-right: 10px;
        }
        
        .form-control::file-selector-button:hover {
            background: var(--primary-dark);
            transform: scale(0.95);
        }
        
        .mb-3 {
            margin-bottom: 16px;
        }
        
        /* Buttons */
        .btn-save {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 10px 24px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.15s ease;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-save:hover {
            background: var(--primary-dark);
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-back {
            background: #4b5563;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .btn-back:hover {
            background: #374151;
            color: #ffffff;
            text-decoration: none;
            transform: scale(0.95);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        
        @media (max-width: 576px) {
            .btn-save, .btn-back {
                font-size: 13px;
                padding: 8px 16px;
                width: 100%;
                justify-content: center;
            }
            .button-group {
                flex-direction: column;
            }
        }
        
        @media (max-width: 400px) {
            .btn-save, .btn-back {
                font-size: 12px;
                padding: 6px 14px;
            }
        }
        
        /* Current Signature Display */
        .signature-divider {
            border-top: 1px solid var(--border-color);
            margin: 20px 0 16px 0;
        }
        
        .signature-section h5 {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 16px;
            margin: 0 0 12px 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .signature-section h5 i {
            color: var(--primary-blue);
            margin-right: 6px;
        }
        
        .signature-image {
            max-width: 200px;
            border: 1px solid var(--border-color);
            padding: 8px;
            border-radius: 0.5rem;
            background: #ffffff;
            display: block;
        }
        
        @media (max-width: 576px) {
            .signature-image {
                max-width: 150px;
            }
        }
        
        @media (max-width: 400px) {
            .signature-image {
                max-width: 120px;
                padding: 6px;
            }
        }
        
        /* File info */
        .file-info {
            font-size: 12px;
            color: var(--text-muted);
            font-family: 'Hanken Grotesk', sans-serif;
            margin-top: 4px;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <!-- Header -->
        <div class="card-header-custom">
            <h3><i class="bi bi-upload"></i> Digital Signature Upload</h3>
            <p>Upload your digital signature to approve student requests.</p>
        </div>

        <?php echo $message; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label">Upload Signature</label>
                <input type="file"
                       name="signature"
                       class="form-control"
                       accept="image/*"
                       required>
                <div class="file-info">Supported formats: PNG, JPG, JPEG</div>
            </div>

            <div class="button-group">
                <button type="submit" name="save" class="btn-save">
                    <i class="bi bi-check-circle"></i> Save Signature
                </button>
                <a href="dashboard.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

        </form>

        <?php if(!empty($officer['digital_signature'])){ ?>
            <div class="signature-divider"></div>
            <div class="signature-section">
                <h5><i class="bi bi-image"></i> Current Signature</h5>
                <img src="../assets/uploads/signatures/<?php echo $officer['digital_signature']; ?>" 
                     alt="Current Signature" 
                     class="signature-image">
            </div>
        <?php } ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>