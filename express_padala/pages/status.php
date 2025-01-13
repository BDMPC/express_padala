<?php
require_once '../config/database.php';

// Get sort parameters
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'sent_datetime';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_sort_columns = [
    'reference_number',
    'sender_name',
    'sent_datetime',
    'departure_datetime',
    'delivery_status'
];

if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'sent_datetime';
}

// Get all documents with their status
$sql = "SELECT 
            d.id,
            d.reference_number,
            d.sender_name,
            d.sent_datetime,
            GROUP_CONCAT(DISTINCT dt.type_name) as document_types,
            t.transportation_type,
            t.driver_name,
            t.driver_contact,
            t.sprinter_rider_name,
            t.departure_datetime,
            t.estimated_arrival,
            b.branch_name,
            CASE 
                WHEN del.status = 'received' THEN 'received'
                WHEN t.departure_datetime IS NOT NULL THEN 'in_transit'
                ELSE 'processing'
            END as delivery_status
        FROM documents d
        LEFT JOIN document_types dt ON d.id = dt.document_id
        LEFT JOIN transits t ON d.id = t.document_id
        LEFT JOIN branches b ON t.branch_id = b.id
        LEFT JOIN deliveries del ON d.id = del.document_id
        GROUP BY d.id
        ORDER BY " . $sort_by . " " . ($order === 'ASC' ? 'ASC' : 'DESC');

$result = $conn->query($sql);

if (!$result) {
    die("Error fetching status: " . $conn->error);
}

// Function to generate sort URL
function getSortUrl($column, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    return '?sort=' . $column . '&order=' . $newOrder;
}

// Function to display sort icon
function getSortIcon($column, $currentSort, $currentOrder) {
    if ($currentSort !== $column) {
        return '<i class="bi bi-arrow-down-up text-muted"></i>';
    }
    return $currentOrder === 'ASC' ? 
        '<i class="bi bi-arrow-up-short"></i>' : 
        '<i class="bi bi-arrow-down-short"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Status - Express Padala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .table th {
            background-color: #f8f9fa;
        }
        .badge {
            font-size: 0.9em;
        }
        .sort-header {
            cursor: pointer;
            white-space: nowrap;
        }
        .sort-header:hover {
            background-color: #e9ecef !important;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/nav.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clipboard-check me-2"></i>Delivery Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th class="sort-header" onclick="window.location.href='<?php echo getSortUrl('reference_number', $sort_by, $order); ?>'">
                                            <i class="bi bi-hash me-1"></i>Reference
                                            <?php echo getSortIcon('reference_number', $sort_by, $order); ?>
                                        </th>
                                        <th class="sort-header" onclick="window.location.href='<?php echo getSortUrl('sender_name', $sort_by, $order); ?>'">
                                            <i class="bi bi-person me-1"></i>Sender
                                            <?php echo getSortIcon('sender_name', $sort_by, $order); ?>
                                        </th>
                                        <th><i class="bi bi-files me-1"></i>Document Types</th>
                                        <th><i class="bi bi-building me-1"></i>Destination</th>
                                        <th><i class="bi bi-person-badge me-1"></i>Sprinter/Rider</th>
                                        <th class="sort-header" onclick="window.location.href='<?php echo getSortUrl('sent_datetime', $sort_by, $order); ?>'">
                                            <i class="bi bi-calendar-event me-1"></i>Departure
                                            <?php echo getSortIcon('sent_datetime', $sort_by, $order); ?>
                                        </th>
                                        <th><i class="bi bi-clock me-1"></i>Est. Arrival</th>
                                        <th class="sort-header" onclick="window.location.href='<?php echo getSortUrl('delivery_status', $sort_by, $order); ?>'">
                                            <i class="bi bi-check-circle me-1"></i>Status
                                            <?php echo getSortIcon('delivery_status', $sort_by, $order); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <a href="track.php?ref=<?php echo urlencode($row['reference_number']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($row['reference_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['document_types'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['branch_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($row['sprinter_rider_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo isset($row['departure_datetime']) ? date('M d, Y h:i A', strtotime($row['departure_datetime'])) : 'Not Set'; ?></td>
                                        <td><?php echo isset($row['estimated_arrival']) ? date('M d, Y h:i A', strtotime($row['estimated_arrival'])) : 'Not Set'; ?></td>
                                        <td>
                                            <?php if ($row['delivery_status'] == 'received'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Delivered</span>
                                            <?php elseif ($row['delivery_status'] == 'in_transit'): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-truck me-1"></i>In Transit</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="bi bi-hourglass me-1"></i>Processing</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 