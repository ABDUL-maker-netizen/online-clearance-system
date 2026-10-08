<?php
session_start();
include '../../includes/config.php';
include '../../includes/auth.php';

if(!isset($_SESSION['faculty_officers_id'])){
    header("Location: login.php");
    exit();
}

$faculty_name = $_SESSION['faculty_officers_name'];
$office = 'FACULTY';

/* =========================
   FETCH LATEST STATUS PER STUDENT
========================= */
$query = mysqli_query($conn,
"SELECT cs.*, s.fullname, s.reg_number, s.department
 FROM clearance_status cs
 INNER JOIN students s ON s.id = cs.student_id
 INNER JOIN (
     SELECT student_id, MAX(id) AS max_id
     FROM clearance_status
     WHERE department_role='FACULTY'
     GROUP BY student_id
 ) latest
 ON cs.student_id = latest.student_id
 AND cs.id = latest.max_id
 WHERE cs.department_role='FACULTY'
 AND s.faculty_name='$faculty_name'
 ORDER BY cs.id DESC
");

// Get counts for summary
$total = mysqli_num_rows($query);
$approved = 0;
$rejected = 0;
$pending = 0;

// Count query with faculty filter
$count_q = mysqli_query($conn,
"SELECT cs.status, COUNT(*) as count
 FROM clearance_status cs
 INNER JOIN students s ON s.id = cs.student_id
 INNER JOIN (
     SELECT student_id, MAX(id) AS max_id
     FROM clearance_status
     WHERE department_role='FACULTY'
     GROUP BY student_id
 ) latest
 ON cs.student_id = latest.student_id
 AND cs.id = latest.max_id
 WHERE cs.department_role='FACULTY'
 AND s.faculty_name='$faculty_name'
 GROUP BY cs.status
");

while($c = mysqli_fetch_assoc($count_q)){
    if($c['status'] == 'approved') $approved = $c['count'];
    elseif($c['status'] == 'rejected') $rejected = $c['count'];
    else $pending = $c['count'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>FACULTY REPORTS - <?php echo htmlspecialchars($faculty_name); ?></title>
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
        }
        
        .container {
            max-width: 1280px;
            padding: 24px 20px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 12px;
            }
        }
        
        /* Card */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            background: #ffffff;
            padding: 24px;
        }
        
        @media (max-width: 576px) {
            .card {
                padding: 16px;
            }
        }
        
        @media (max-width: 400px) {
            .card {
                padding: 12px;
            }
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .page-header h3 {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 24px;
            margin: 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .page-header h3 i {
            color: var(--primary-blue);
            margin-right: 10px;
        }
        
        .page-header .faculty-badge {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-block;
        }
        
        @media (max-width: 576px) {
            .page-header h3 {
                font-size: 20px;
            }
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            .page-header .faculty-badge {
                align-self: flex-start;
            }
        }
        
        @media (max-width: 400px) {
            .page-header h3 {
                font-size: 18px;
            }
        }
        
        /* Buttons */
        .btn-back {
            background: #4b5563;
            color: #ffffff;
            padding: 8px 20px;
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
        
        .btn-print {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 8px 20px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.15s ease;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-print:hover {
            background: var(--primary-dark);
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-export {
            background: #006c49;
            color: #ffffff;
            padding: 8px 20px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.15s ease;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-export:hover {
            background: #005236;
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-group-custom {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        
        @media (max-width: 576px) {
            .btn-back, .btn-print, .btn-export {
                width: 100%;
                justify-content: center;
                font-size: 13px;
                padding: 6px 16px;
            }
            .btn-group-custom {
                flex-direction: column;
            }
        }
        
        @media (max-width: 400px) {
            .btn-back, .btn-print, .btn-export {
                font-size: 12px;
                padding: 5px 12px;
            }
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stats-card {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 16px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .stats-card .number {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .stats-card .number.primary { color: var(--primary-blue); }
        .stats-card .number.success { color: #006c49; }
        .stats-card .number.danger { color: #dc2626; }
        .stats-card .number.warning { color: #f59e0b; }
        
        .stats-card .label {
            color: var(--text-muted);
            font-size: 13px;
            font-family: 'Hanken Grotesk', sans-serif;
            margin-top: 4px;
        }
        
        .stats-card .label i {
            margin-right: 4px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .stats-card .number {
                font-size: 22px;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .stats-card {
                padding: 12px;
            }
            .stats-card .number {
                font-size: 18px;
            }
            .stats-card .label {
                font-size: 11px;
            }
        }
        
        @media (max-width: 400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 6px;
            }
            .stats-card {
                padding: 10px;
            }
            .stats-card .number {
                font-size: 16px;
            }
            .stats-card .label {
                font-size: 10px;
            }
        }
        
        /* Table */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
            padding: 0 4px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 14px;
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #111827;
            color: #ffffff;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border: none;
            white-space: nowrap;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .table thead th:first-child {
            border-radius: 0.5rem 0 0 0;
        }
        
        .table thead th:last-child {
            border-radius: 0 0.5rem 0 0;
        }
        
        .table tbody td {
            padding: 10px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table tbody tr:hover {
            background: #f8f9ff;
        }
        
        @media (max-width: 768px) {
            .table thead th,
            .table tbody td {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 576px) {
            .table thead {
                display: none;
            }
            
            .table tbody tr {
                display: block;
                margin-bottom: 12px;
                border: 1px solid var(--border-color);
                border-radius: 0.5rem;
                padding: 8px;
                background: #ffffff;
            }
            
            .table tbody td {
                display: flex;
                justify-content: space-between;
                padding: 6px 10px !important;
                border-bottom: 1px solid #f0f0f0;
                align-items: center;
                width: 100%;
                font-size: 12px;
            }
            
            .table tbody td:last-child {
                border-bottom: none;
            }
            
            .table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 11px;
                color: var(--text-muted);
                margin-right: 8px;
                flex-shrink: 0;
            }
        }
        
        @media (max-width: 400px) {
            .table tbody td {
                font-size: 11px;
                padding: 4px 8px !important;
            }
            .table tbody td::before {
                font-size: 10px;
            }
        }
        
        /* Status Badge */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        @media (max-width: 576px) {
            .status-badge {
                font-size: 10px;
                padding: 3px 10px;
            }
        }
        
        @media (max-width: 400px) {
            .status-badge {
                font-size: 9px;
                padding: 2px 8px;
            }
        }
        
        /* Comment styling */
        .comment-text {
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .comment-text.empty {
            color: #9ca3af;
            font-style: italic;
        }
        
        @media (max-width: 576px) {
            .comment-text {
                font-size: 12px;
            }
        }
        
        /* Empty state */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            color: #d1d5db;
        }
        
        .empty-state p {
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        @media (max-width: 576px) {
            .empty-state {
                padding: 30px 16px;
            }
            .empty-state i {
                font-size: 36px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <!-- Header -->
        <div class="page-header">
            <h3><i class="bi bi-file-earmark-text"></i> FACULTY REPORTS</h3>
            <div>
                <span class="faculty-badge"><?php echo htmlspecialchars($faculty_name); ?></span>
                <a href="dashboard.php" class="btn-back ms-2">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stats-card">
                <div class="number primary"><?php echo $total; ?></div>
                <div class="label"><i class="bi bi-file-earmark"></i> Total Requests</div>
            </div>
            <div class="stats-card">
                <div class="number success"><?php echo $approved; ?></div>
                <div class="label"><i class="bi bi-check-circle text-success"></i> Approved</div>
            </div>
            <div class="stats-card">
                <div class="number danger"><?php echo $rejected; ?></div>
                <div class="label"><i class="bi bi-x-circle text-danger"></i> Rejected</div>
            </div>
            <div class="stats-card">
                <div class="number warning"><?php echo $pending; ?></div>
                <div class="label"><i class="bi bi-clock text-warning"></i> Pending</div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive-custom">
            <table class="table" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Reg No</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0){ 
                        $counter = 1;
                    ?>
                    <?php while($row = mysqli_fetch_assoc($query)){ 
                        $status = strtolower($row['status']);
                        $status_icon = $status == 'approved' ? 'bi-check-circle' : ($status == 'rejected' ? 'bi-x-circle' : 'bi-clock');
                    ?>
                    <tr>
                        <td data-label="#"><?php echo $counter++; ?></td>
                        <td data-label="Name"><?php echo htmlspecialchars($row['fullname']); ?></td>
                        <td data-label="Reg No"><?php echo htmlspecialchars($row['reg_number']); ?></td>
                        <td data-label="Department"><?php echo htmlspecialchars($row['department']); ?></td>
                        <td data-label="Status">
                            <span class="status-badge <?php echo $status; ?>">
                                <i class="bi <?php echo $status_icon; ?>"></i> <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td data-label="Comment">
                            <?php 
                            if(!empty($row['comment'])){
                                echo "<span class='comment-text'>" . htmlspecialchars($row['comment']) . "</span>";
                            } else {
                                echo "<span class='comment-text empty'>No comment</span>";
                            }
                            ?>
                        </td>
                        <td data-label="Date">
                            <?php 
                            $date = !empty($row['approved_at']) ? $row['approved_at'] : ($row['updated_at'] ?? '');
                            echo !empty($date) ? date('Y-m-d H:i', strtotime($date)) : '<span class="text-muted">-</span>';
                            ?>
                        </td>
                    </tr>
                    <?php } ?>
                    <?php } else { ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No records found for <?php echo htmlspecialchars($faculty_name); ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Export Options -->
        <div class="btn-group-custom">
            <button onclick="window.print()" class="btn-print">
                <i class="bi bi-printer"></i> Print Report
            </button>
            <button onclick="exportCSV()" class="btn-export">
                <i class="bi bi-file-earmark-excel"></i> Export CSV
            </button>
        </div>

    </div>

</div>

<script>
function exportCSV() {
    let rows = document.querySelectorAll('#reportTable tbody tr');
    let csv = '\uFEFFStudent Name,Reg Number,Status,Comment,Date\n'; // Add BOM for UTF-8
    
    rows.forEach(row => {
        let cols = row.querySelectorAll('td');
        if(cols.length > 1) {
            let data = [];
            for(let i = 1; i < cols.length - 1; i++) {
                let text = cols[i].innerText.replace(/"/g, '""').trim();
                data.push('"' + text + '"');
            }
            csv += data.join(',') + '\n';
        }
    });
    
    let blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = '<?php echo $office; ?>_report.csv';
    link.click();
}
</script>

</body>
</html>