<?php
// session_start(); // uncomment if needed
include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/auth.php';

studentAuth();

/* SECURITY CHECK */
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* =========================
   VERIFY STUDENT EXISTS
========================= */
$student_q = mysqli_query($conn,
    "SELECT * FROM students WHERE id='$student_id' LIMIT 1");

$student = mysqli_fetch_assoc($student_q);

if (!$student) {
    die("Student not found");
}

/* =========================
   GET LATEST STATUS PER OFFICE
========================= */
$query = mysqli_query($conn,
    "SELECT cs1.*
     FROM clearance_status cs1
     INNER JOIN (
        SELECT department_role,
               MAX(id) AS max_id
        FROM clearance_status
        WHERE student_id='$student_id'
        GROUP BY department_role
     ) cs2
     ON cs1.id = cs2.max_id
     ORDER BY cs1.department_role ASC
");

// Check if there are any records
$has_records = mysqli_num_rows($query) > 0;
?>

<!-- =========================================================
     CLEARANCE STATUS PAGE CONTENT - UPDATED WITH MATCHING DESIGN
     This is included inside dashboard.php
========================================================= -->

<style>
/* Matching design styles */
.status-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 20px;
}

/* Page Header */
.page-header-status {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    margin-top: 8px;
}

.page-header-status .header-icon {
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

.page-header-status .header-icon i {
    font-size: 2rem;
    line-height: 1;
}

.page-header-status h1 {
    font-size: 32px;
    font-weight: 700;
    line-height: 40px;
    letter-spacing: -0.02em;
    color: #111827;
    margin: 0;
    font-family: 'Hanken Grotesk', sans-serif;
}

.page-header-status p {
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
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

/* Table styling */
.table-wrapper {
    overflow-x: auto;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.table-custom {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Hanken Grotesk', sans-serif;
    font-size: 14px;
    line-height: 20px;
}

.table-custom thead th {
    background-color: #111827;
    color: #ffffff;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    border: none;
    text-align: left;
}

.table-custom thead th:first-child {
    border-radius: 0.5rem 0 0 0;
}

.table-custom thead th:last-child {
    border-radius: 0 0.5rem 0 0;
}

.table-custom tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
    color: #111827;
}

.table-custom tbody tr {
    transition: background 0.2s ease;
}

.table-custom tbody tr:hover {
    background-color: #f8f9fa;
}

.table-custom tbody tr:last-child td {
    border-bottom: none;
}

/* Status badges */
.status-badge {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 14px;
    border-radius: 9999px;
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.status-badge.approved {
    background-color: #d1fae5;
    color: #065f46;
}

.status-badge.approved i {
    color: #16a34a;
}

.status-badge.rejected {
    background-color: #fee2e2;
    color: #991b1b;
}

.status-badge.rejected i {
    color: #dc2626;
}

.status-badge.pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-badge.pending i {
    color: #d97706;
}

/* Department name */
.department-name {
    font-weight: 500;
    color: #111827;
}

/* Comment text */
.comment-text {
    color: #4b5563;
}

.comment-text.empty {
    color: #9ca3af;
    font-style: italic;
}

/* Action buttons */
.btn-resubmit {
    background-color: #dc2626;
    color: #ffffff;
    border: none;
    padding: 4px 16px;
    border-radius: 0.375rem;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-block;
    font-family: 'Hanken Grotesk', sans-serif;
}

.btn-resubmit:hover {
    background-color: #b91c1c;
    color: #ffffff;
    transform: scale(0.95);
    text-decoration: none;
}

.btn-resubmit i {
    margin-right: 4px;
}

/* Empty state */
.empty-state {
    background: #ffffff;
    border-radius: 1rem;
    padding: 48px 32px;
    text-align: center;
    border: 2px dashed #e5e7eb;
}

.empty-state .empty-icon {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 12px;
    display: block;
}

.empty-state h5 {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.empty-state p {
    color: #4b5563;
    font-size: 14px;
    margin-bottom: 0;
}

/* Back button */
.btn-back {
    background-color: #f3f4f6;
    border: none;
    color: #111827;
    font-weight: 500;
    padding: 10px 24px;
    border-radius: 0.5rem;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-family: 'Hanken Grotesk', sans-serif;
    margin-top: 24px;
}

.btn-back:hover {
    background-color: #e5e7eb;
    color: #111827;
    text-decoration: none;
}

/* Responsive */
@media (max-width: 768px) {
    .status-page {
        padding: 12px;
    }
    
    .page-header-status {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .page-header-status h1 {
        font-size: 24px;
        line-height: 32px;
    }
    
    .page-header-status p {
        font-size: 14px;
    }
    
    .card-custom {
        padding: 16px;
    }
    
    .table-custom {
        font-size: 12px;
    }
    
    .table-custom thead th,
    .table-custom tbody td {
        padding: 10px 12px;
    }
    
    .status-badge {
        font-size: 11px;
        padding: 3px 10px;
    }
    
    .btn-resubmit {
        font-size: 11px;
        padding: 3px 12px;
    }
    
    .empty-state {
        padding: 32px 20px;
    }
    
    .empty-state .empty-icon {
        font-size: 2.5rem;
    }
}

@media (max-width: 576px) {
    .page-header-status h1 {
        font-size: 20px;
    }
    
    .page-header-status .header-icon {
        width: 40px;
        height: 40px;
    }
    
    .page-header-status .header-icon i {
        font-size: 1.5rem;
    }
    
    .card-custom {
        padding: 12px;
    }
    
    /* Stack table on mobile */
    .table-custom thead {
        display: none;
    }
    
    .table-custom tbody tr {
        display: block;
        margin-bottom: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 8px;
        background: #fff;
    }
    
    .table-custom tbody tr:hover {
        background: #fff;
    }
    
    .table-custom tbody td {
        display: flex;
        justify-content: space-between;
        padding: 6px 10px !important;
        border-bottom: 1px solid #f0f0f0;
        align-items: center;
        width: 100%;
    }
    
    .table-custom tbody td:last-child {
        border-bottom: none;
    }
    
    .table-custom tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 11px;
        color: #6b7280;
        margin-right: 8px;
        flex-shrink: 0;
    }
    
    .department-name {
        font-weight: 600;
    }
    
    .btn-back {
        width: 100%;
        justify-content: center;
    }
}

/* Small screen optimizations */
@media (max-width: 400px) {
    .page-header-status h1 {
        font-size: 18px;
    }
    
    .status-badge {
        font-size: 10px;
        padding: 2px 8px;
    }
    
    .table-custom tbody td {
        font-size: 11px;
        padding: 4px 8px !important;
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

/* Action cell */
.action-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
</style>

<div class="status-page">

    <!-- Page Header -->
    <div class="page-header-status">
        <div class="header-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div>
            <h1>Clearance Status</h1>
            <p>Review the approval status of your clearance requests across departments.</p>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card-custom">

        <?php if ($has_records): ?>

            <div class="table-wrapper">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Department / Office</th>
                            <th>Status</th>
                            <th>Comment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php while ($row = mysqli_fetch_assoc($query)): ?>

                            <?php
                                $status = strtolower($row['status']);
                                $department = htmlspecialchars($row['department_role']);
                                $comment = htmlspecialchars($row['comment'] ?? '');
                                $comment_display = !empty($comment) ? $comment : 'No comment';
                                $comment_class = !empty($comment) ? 'comment-text' : 'comment-text empty';
                                
                                // Status icon
                                $status_icon = '';
                                if ($status == 'approved') {
                                    $status_icon = 'bi-check-circle-fill';
                                } elseif ($status == 'rejected') {
                                    $status_icon = 'bi-x-circle-fill';
                                } else {
                                    $status_icon = 'bi-clock-fill';
                                }
                            ?>

                            <tr>
                                <td data-label="Department / Office">
                                    <span class="department-name"><?php echo $department; ?></span>
                                </td>

                                <td data-label="Status">
                                    <span class="status-badge <?php echo $status; ?>">
                                        <i class="bi <?php echo $status_icon; ?>"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>

                                <td data-label="Comment">
                                    <span class="<?php echo $comment_class; ?>">
                                        <?php echo $comment_display; ?>
                                    </span>
                                </td>

                                <td data-label="Action">
                                    <div class="action-cell">
                                        <?php if ($status == 'rejected'): ?>
                                            <a href="resubmit.php?office=<?php echo urlencode($row['department_role']); ?>"
                                               class="btn-resubmit"
                                               onclick="return confirm('Resubmit this request?')">
                                                <i class="bi bi-arrow-repeat"></i> Resubmit
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#9ca3af; font-size:14px;">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <!-- Empty State -->
            <div class="empty-state">
                <i class="bi bi-inbox empty-icon"></i>
                <h5>No clearance records found</h5>
                <p>You haven't submitted any clearance requests yet.</p>
            </div>

        <?php endif; ?>

        <!-- Back to Dashboard -->
        <a href="dashboard.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>

    </div>

</div>