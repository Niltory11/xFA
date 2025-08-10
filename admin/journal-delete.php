<?php
include '../config/dbcon.php';

$message = '';
$message_type = '';
$entry = null;

// Get entry ID from URL
$entry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entry_id <= 0) {
    header('Location: journal.php');
    exit;
}

// Get journal entry data with account names
$stmt = $conn->prepare("SELECT je.*, 
                       da.account_name as debit_account_name, 
                       ca.account_name as credit_account_name 
                       FROM journal_entries je 
                       JOIN accounts da ON je.debit_account_id = da.id 
                       JOIN accounts ca ON je.credit_account_id = ca.id 
                       WHERE je.id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: journal.php');
    exit;
}

$entry = $result->fetch_assoc();
$stmt->close();

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Delete the journal entry
    $delete_stmt = $conn->prepare("DELETE FROM journal_entries WHERE id = ?");
    $delete_stmt->bind_param("i", $entry_id);
    
    if ($delete_stmt->execute()) {
        // Redirect after successful deletion
        header('Location: journal.php?deleted=1');
        exit;
    } else {
        $message = 'Error deleting journal entry: ' . $conn->error;
        $message_type = 'danger';
    }
    $delete_stmt->close();
}

// Include header after all potential redirects
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Delete Journal Entry</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="journal.php">Journal Entries</a></li>
        <li class="breadcrumb-item active">Delete Entry</li>
    </ol>
    
    <div class="row justify-content-center">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-trash me-1"></i>
                    Delete Journal Entry
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">Warning!</h5>
                        <p>You are about to delete journal entry #<strong><?php echo $entry_id; ?></strong></p>
                        <p>This action cannot be undone. Please make sure you want to delete this entry.</p>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Journal Entry Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Date:</strong><br>
                                    <?php echo date('m/d/Y', strtotime($entry['entry_date'])); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Type:</strong><br>
                                    <span class="badge bg-<?php echo $entry['entry_type'] === 'cash' ? 'success' : 'info'; ?>">
                                        <?php echo ucfirst($entry['entry_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Description:</strong><br>
                                    <?php echo htmlspecialchars($entry['description']); ?>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <strong>Debit Account:</strong><br>
                                    <?php echo htmlspecialchars($entry['debit_account_name']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Credit Account:</strong><br>
                                    <?php echo htmlspecialchars($entry['credit_account_name']); ?>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Amount:</strong><br>
                                    <span class="fw-bold text-primary">$<?php echo number_format($entry['amount'], 2); ?></span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Created:</strong><br>
                                    <?php echo date('m/d/Y H:i', strtotime($entry['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="post" action="">
                        <div class="d-flex gap-2">
                            <button type="submit" name="confirm_delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this journal entry? This action cannot be undone.')">
                                <i class="fas fa-trash me-1"></i>Delete Entry
                            </button>
                            <a href="journal.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 