<?php
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include('includes/config.php');
include_once('includes/pagination.php');

$pageTitle = "कार्यक्रम व्यवस्थापन (Event Management)";
$pageSubtitle = "Manage office events, meetings, trainings, and other activities";
$activePage = "events";

// Get user role from session
$user_role_int = isset($_SESSION['user_role']) ? (int) $_SESSION['user_role'] : 0;
$user_role_string = '';
if ($user_role_int === 2) {
    $user_role_string = 'super_admin';
} elseif ($user_role_int === 1) {
    $user_role_string = 'admin';
} else {
    $user_role_string = 'user';
}

// Get current user's personnel ID
$current_personnel_id = isset($_SESSION['user_personnel_id']) ? (int) $_SESSION['user_personnel_id'] : 0;
$current_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

// Determine if user can manage events (create, edit, delete)
$can_manage_events = ($user_role_int >= 1); // Admin or Super Admin

// For testing - REMOVE IN PRODUCTION
if (!isset($_SESSION['user_role'])) {
    $_SESSION['user_role'] = 2;
    $_SESSION['user_personnel_id'] = 1;
    $_SESSION['user_id'] = 1;
    $user_role_int = 2;
    $current_personnel_id = 1;
    $current_user_id = 1;
    $can_manage_events = true;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');

    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Security token validation failed']);
            exit;
        }

        $action = $_POST['action'] ?? '';

        // Get all events with combined filters
        if ($action === 'get_events') {
            $filter_status = $_POST['filter_status'] ?? 'all';
            $filter_priority = $_POST['filter_priority'] ?? 'all';
            $search = $_POST['search'] ?? '';
            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';
            $page = (int) ($_POST['page'] ?? 1);
            $per_page = (int) ($_POST['per_page'] ?? 10);
            $offset = ($page - 1) * $per_page;

            $where = "1=1";
            $params = [];

            // Role-based filter - Regular users can only see their own events
            if ($user_role_int == 0) {
                $where .= " AND e.personnel_id = ?";
                $params = [$current_personnel_id];
            }

            // Status filter
            if ($filter_status !== 'all') {
                $where .= " AND e.status = ?";
                $params[] = $filter_status;
            }

            // Priority filter
            if ($filter_priority !== 'all') {
                $where .= " AND e.priority = ?";
                $params[] = $filter_priority;
            }

            // Text search
            if (!empty($search)) {
                $where .= " AND (e.event_title LIKE ? OR e.location LIKE ? OR e.event_description LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Date range filter
            if (!empty($date_from)) {
                $where .= " AND e.start_date >= ?";
                $params[] = $date_from;
            }

            if (!empty($date_to)) {
                $where .= " AND e.end_date <= ?";
                $params[] = $date_to;
            }

            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM events e WHERE $where";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get events
            $sql = "SELECT e.*
                    FROM events e
                    WHERE $where
                    ORDER BY 
                        CASE e.status
                            WHEN 'ongoing' THEN 1
                            WHEN 'upcoming' THEN 2
                            WHEN 'completed' THEN 3
                            WHEN 'cancelled' THEN 4
                        END,
                        e.start_date ASC 
                    LIMIT ? OFFSET ?";

            $stmt = $pdo->prepare($sql);
            $param_index = 1;
            foreach ($params as $param) {
                $stmt->bindValue($param_index++, $param);
            }
            $stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
            $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $events,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $per_page),
                'can_manage' => $can_manage_events
            ]);
            exit;
        }

        // Get single event for editing - Check permissions
        if ($action === 'get_event') {
            // Only admins can get events for editing
            if (!$can_manage_events) {
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            $id = (int) $_POST['id'];

            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($event) {
                echo json_encode(['success' => true, 'data' => $event]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Event not found']);
            }
            exit;
        }

        // Create event - Check permissions
        if ($action === 'create_event') {
            if (!$can_manage_events) {
                echo json_encode(['success' => false, 'error' => 'Permission denied. Only administrators can create events.']);
                exit;
            }

            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $event_type = $_POST['event_type'] ?? 'meeting';
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';

            if (empty($title) || empty($start_date)) {
                echo json_encode(['success' => false, 'error' => 'Title and start date are required']);
                exit;
            }

            if (!empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
                echo json_encode(['success' => false, 'error' => 'End date cannot be before start date']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO events (event_title, event_description, event_type, start_date, end_date, location, priority, personnel_id, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')
            ");

            $result = $stmt->execute([
                $title,
                $description,
                $event_type,
                $start_date,
                $end_date ?: null,
                $location,
                $priority,
                $current_personnel_id,
                $current_user_id
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create event']);
            }
            exit;
        }

        // Update event - Check permissions
        if ($action === 'update_event') {
            if (!$can_manage_events) {
                echo json_encode(['success' => false, 'error' => 'Permission denied. Only administrators can update events.']);
                exit;
            }

            $id = (int) $_POST['id'];
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $event_type = $_POST['event_type'] ?? 'meeting';
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $status = $_POST['status'] ?? 'upcoming';

            if (empty($title) || empty($start_date)) {
                echo json_encode(['success' => false, 'error' => 'Title and start date are required']);
                exit;
            }

            if (!empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
                echo json_encode(['success' => false, 'error' => 'End date cannot be before start date']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE events 
                SET event_title = ?, event_description = ?, event_type = ?, 
                    start_date = ?, end_date = ?, location = ?, 
                    priority = ?, status = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $title,
                $description,
                $event_type,
                $start_date,
                $end_date ?: null,
                $location,
                $priority,
                $status,
                $id
            ]);

            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update event']);
            }
            exit;
        }

        // Delete event - Check permissions
        if ($action === 'delete_event') {
            if (!$can_manage_events) {
                echo json_encode(['success' => false, 'error' => 'Permission denied. Only administrators can delete events.']);
                exit;
            }

            $id = (int) $_POST['id'];

            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $result = $stmt->execute([$id]);

            echo json_encode(['success' => $result]);
            exit;
        }

        // Get statistics with filters
        if ($action === 'get_stats') {
            $filter_status = $_POST['filter_status'] ?? 'all';
            $filter_priority = $_POST['filter_priority'] ?? 'all';
            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';

            $stats = [];
            $where = "1=1";
            $params = [];

            // Apply role-based filter for stats
            if ($user_role_int == 0) {
                $where .= " AND personnel_id = ?";
                $params[] = $current_personnel_id;
            }

            if ($filter_status !== 'all') {
                $where .= " AND status = ?";
                $params[] = $filter_status;
            }

            if ($filter_priority !== 'all') {
                $where .= " AND priority = ?";
                $params[] = $filter_priority;
            }

            if (!empty($date_from)) {
                $where .= " AND start_date >= ?";
                $params[] = $date_from;
            }

            if (!empty($date_to)) {
                $where .= " AND end_date <= ?";
                $params[] = $date_to;
            }

            // Get counts by status
            $statuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];
            foreach ($statuses as $status) {
                $status_where = $where . " AND status = ?";
                $status_params = array_merge($params, [$status]);
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE $status_where");
                $stmt->execute($status_params);
                $stats[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            }

            // Get total in range
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE $where");
            $stmt->execute($params);
            $stats['total_in_range'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            echo json_encode(['success' => true, 'data' => $stats]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;

    } catch (Exception $e) {
        error_log("Events.php AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Get initial stats with role-based filtering
$stats = [];
if ($user_role_int == 0) {
    // Regular user - only their events
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'upcoming' AND personnel_id = ?");
    $stmt->execute([$current_personnel_id]);
    $stats['upcoming'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'ongoing' AND personnel_id = ?");
    $stmt->execute([$current_personnel_id]);
    $stats['ongoing'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'completed' AND personnel_id = ?");
    $stmt->execute([$current_personnel_id]);
    $stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} else {
    // Admin/Super Admin - all events
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'upcoming'");
    $stats['upcoming'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'ongoing'");
    $stats['ongoing'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'completed'");
    $stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

ob_start();
?>

<!-- Page Header Actions -->
<div class="page-actions">
    <?php if ($can_manage_events): ?>
        <button class="btn-add" id="addEventBtn">
            <i class="fas fa-plus-circle"></i> नयाँ कार्यक्रम थप्नुहोस्
        </button>
    <?php endif; ?>
    <button class="btn-filter-date" id="toggleAdvancedFilterBtn">
        <i class="fas fa-sliders-h"></i> Advanced Filters
    </button>
    <?php if ($can_manage_events): ?>
        <button class="btn-export" id="exportBtn">
            <i class="fas fa-download"></i> Export Events
        </button>
    <?php endif; ?>
    <button class="btn-reset" id="resetFiltersBtn">
        <i class="fas fa-undo-alt"></i> Reset All
    </button>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-week"></i>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <div class="stat-label" style="font-size:12px;">
                आगामी कार्यक्रमहरु <br />(Upcoming Events)
            </div>

            <div class="stat-value" id="upcomingCount">
                <?php echo $stats['upcoming']; ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-week"></i>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <div class="stat-label" style="font-size:12px;">
                चालु कार्यक्रमहरु <br />(Ongoing Events)
            </div>

            <div class="stat-value" id="upcomingCount">
                <?php echo $stats['ongoing']; ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-week"></i>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <div class="stat-label" style="font-size:12px;">
                सम्पन्न भएका कार्यक्रमहरु <br />(Completed Events)
            </div>

            <div class="stat-value" id="upcomingCount">
                <?php echo $stats['completed']; ?>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Filter Section -->
<div class="advanced-filter-section" id="advancedFilterSection" style="display: none;">
    <div class="filter-box">
        <div class="filter-header">
            <h4><i class="fas fa-filter"></i> Advance Filter</h4>
            <button id="closeAdvancedFilter" class="close-filter"><i class="fas fa-times"></i></button>
        </div>
        <div class="filter-body">
            <div class="filter-row">
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> मिति (देखि - सम्म)</label>
                    <div class="date-range-group">
                        <input type="text" id="dateFrom" class="filter-input nepali-datepicker" placeholder="From Date">
                        <span>देखि</span>
                        <input type="text" id="dateTo" class="filter-input nepali-datepicker" placeholder="To Date">
                        <span>सम्म</span>
                    </div>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-tasks"></i> कार्यक्रमको अवस्था (Status)</label>
                    <select id="filterStatus" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="upcoming">आगामी कार्यक्रम (Upcoming)</option>
                        <option value="ongoing">चालु कार्यक्रमह (Ongoing)</option>
                        <option value="completed">सम्पन्न भएका कार्यक्रम (Completed)</option>
                        <!-- <option value="cancelled">Cancelled</option> -->
                    </select>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-flag"></i>कार्यक्रमको प्राथमिकता (Priority)</label>
                    <select id="filterPriority" class="filter-select">
                        <option value="all">All Priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>

            <div class="filter-buttons-row">
                <button id="applyFilters" class="btn-apply-filters"><i class="fas fa-check"></i> Apply Filters</button>
                <button id="clearFilters" class="btn-clear-filters"><i class="fas fa-times"></i> Clear All</button>
            </div>
        </div>
    </div>
</div>

<!-- Search Section -->
<div class="search-section">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input"
            placeholder="Search by title, location, or description...">
        <button id="clearSearch" class="clear-search" style="display: none;">✕</button>
    </div>
</div>

<!-- Active Filters Display -->
<div id="activeFiltersContainer" class="active-filters-container" style="display: none;">
    <div class="active-filters-title">Active Filters:</div>
    <div class="active-filters-list" id="activeFiltersList"></div>
</div>

<!-- Events Table -->
<div class="data-table">
    <table id="eventsTable">
        <thead>
            <tr>
                <th style="width: 15px;text-align:center;">S.N.</th>
                <th style="text-align:center;">कार्यक्रम शिर्षक</th>
                <th style="text-align:center;">प्रकार</th>
                <th style="text-align:center;">मिति</th>
                <th style="text-align:center;">स्थान</th>
                <th style="text-align:center;">प्राथमिकता</th>
                <th style="text-align:center;">अवस्था</th>
                <?php if ($can_manage_events): ?>
                    <th style="width: 140px;text-align:center;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="tableBody">
            <tr>
                <?php if ($can_manage_events): ?>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                    <?php else: ?>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                    <?php endif; ?>
                    <i class="fas fa-spinner fa-spin"></i> Loading events...
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div id="paginationContainer" class="pagination-container" style="display: none;"></div>

<!-- Records Per Page -->
<div class="records-per-page">
    <label>Show:</label>
    <select id="recordsPerPage" class="no-select2">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>
    <span>entries per page</span>
</div>

<!-- No Results Message -->
<div id="noResults" class="no-results" style="display: none;">
    <i class="fas fa-calendar-alt"></i>
    <p>No events found matching your criteria.</p>
</div>

<?php if ($can_manage_events): ?>
    <!-- Add/Edit Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-calendar-plus"></i> Add New Event</h3>
                <span class="close"><i class="fas fa-times"></i></span>
            </div>
            <div class="modal-body">
                <form id="eventForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" id="eventId" name="event_id" value="0">

                    <div class="form-grid">
                        <div class="input-field full-width">
                            <label>कार्यक्रमको शिर्षक<span class="required-star">*</span></label>
                            <input type="text" id="title" name="title" placeholder="Enter event title" required>
                        </div>

                        <div class="input-field">
                            <label><i class="fas fa-tag"></i> कार्यक्रमको प्रकार</label>
                            <select id="eventType" name="event_type">
                                <option value="meeting">📋 Meeting</option>
                                <option value="training">🎓 Training</option>
                                <option value="ceremony">🎖️ Ceremony</option>
                                <option value="holiday">🎉 Holiday</option>
                                <option value="deadline">⏰ Deadline</option>
                                <option value="other">📌 Other</option>
                            </select>
                        </div>

                        <div class="input-field">
                            <label><i class="fas fa-flag"></i> प्राथमिकता</label>
                            <select id="priority" name="priority">
                                <option value="low">🟢 Low</option>
                                <option value="medium" selected>🟡 Medium</option>
                                <option value="high">🔴 High</option>
                            </select>
                        </div>

                        <div class="input-field">
                            <label><i class="fas fa-calendar-day"></i> कार्यक्रम शुरु मिति<span
                                    class="required-star">*</span></label>
                            <input type="text" id="startDate" name="start_date" class="nepali-datepicker" placeholder="YYYY-MM-DD" autocomplete="off" required>
                        </div>

                        <div class="input-field">
                            <label><i class="fas fa-calendar-day"></i>कार्यक्रम समापन मिति</label>
                            <input type="text" id="endDate" name="end_date" class="nepali-datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                            <small>Leave empty if single day event</small>
                        </div>

                        <div class="input-field">
                            <label><i class="fas fa-tasks"></i> कार्यक्रमको अवस्था</label>
                            <select id="eventStatus" name="status">
                                <option value="upcoming">⏰ Upcoming</option>
                                <option value="ongoing">▶️ Ongoing</option>
                                <option value="completed">✅ Completed</option>
                                <option value="cancelled">❌ Cancelled</option>
                            </select>
                        </div>

                        <div class="input-field full-width">
                            <label><i class="fas fa-location-dot"></i> स्थान</label>
                            <input type="text" id="location" name="location" placeholder="Venue, room, or online link">
                        </div>

                        <div class="input-field full-width">
                            <label><i class="fas fa-align-left"></i> कार्यक्रमको संक्षिप्त विवरण</label>
                            <textarea id="description" name="description" rows="4"
                                placeholder="Event details, agenda, notes..."></textarea>
                        </div>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" id="cancelModalBtn">Cancel</button>
                        <button type="submit" class="btn-submit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- View Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Event Details</h3>
            <span class="close-details"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body" id="detailsContent"></div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast" style="display: none;">
    <span id="toastMessage"></span>
</div>

<style>
    .page-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .btn-add,
    .btn-export,
    .btn-filter-date,
    .btn-reset {
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border: none;
    }

    .btn-add {
        background: #10263f;
        color: white;
    }

    .btn-add:hover {
        background: #0d2036;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-filter-date {
        background: #4b5563;
        color: white;
    }

    .btn-filter-date:hover {
        background: #374151;
        transform: translateY(-1px);
    }

    .btn-export {
        background: #0e7490;
        color: white;
    }

    .btn-export:hover {
        background: #1e4a3a;
        transform: translateY(-1px);
    }

    .btn-reset {
        background: #dc2626;
        color: white;
    }

    .btn-reset:hover {
        background: #b91c1c;
        transform: translateY(-1px);
    }

    /* Advanced Filter Section */
    .advanced-filter-section {
        background: white;
        border-radius: 12px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #eef2f6;
    }

    .filter-box {
        padding: 16px;
    }

    .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        padding-bottom: 8px;
        border-bottom: 1px solid #eef2f6;
    }

    .filter-header h4 {
        margin: 0;
        color: #1a2c3e;
        font-size: 16px;
    }

    .close-filter {
        background: none;
        border: none;
        font-size: 16px;
        cursor: pointer;
        color: #9aa9bc;
    }

    .close-filter:hover {
        color: #c2410c;
    }

    .filter-row {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
    }

    .date-range-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .date-range-group input {
        flex: 1;
        padding: 8px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
    }

    .date-range-group span {
        color: #6c7a8e;
    }

    .filter-input,
    .filter-select {
        width: 100%;
        padding: 8px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
    }

    .filter-input:focus,
    .filter-select:focus {
        border-color: #0e7490;
    }

    .filter-buttons-row {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding-top: 10px;
        border-top: 1px solid #eef2f6;
    }

    .btn-apply-filters,
    .btn-clear-filters {
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        border: none;
    }

    .btn-apply-filters {
        background: #10263f;
        color: white;
    }

    .btn-apply-filters:hover {
        background: #0d2036;
    }

    .btn-clear-filters {
        background: #f3f4f6;
        color: #4b5563;
    }

    .btn-clear-filters:hover {
        background: #e5e7eb;
    }

    /* Search Section */
    .search-section {
        margin-bottom: 14px;
    }

    .search-container {
        position: relative;
        max-width: 400px;
    }

    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9aa9bc;
        font-size: 14px;
    }

    .search-input {
        width: 100%;
        padding: 10px 35px 10px 38px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
    }

    .search-input:focus {
        border-color: #0e7490;
        box-shadow: 0 0 0 3px rgba(14, 116, 144, 0.08);
    }

    .clear-search {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #9aa9bc;
        font-size: 14px;
        padding: 0;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .clear-search:hover {
        background: #e2e8f0;
        color: #c2410c;
    }

    /* Active Filters */
    .active-filters-container {
        background: #f8fafc;
        border-radius: 8px;
        padding: 9px 12px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        border: 1px solid #e2e8f0;
    }

    .active-filters-title {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }

    .active-filters-list {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: white;
        color: #1e40af;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        border: 1px solid #dbeafe;
    }

    .remove-filter-tag {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 14px;
        color: #6c7a8e;
        margin-left: 5px;
    }

    .remove-filter-tag:hover {
        color: #c2410c;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #eef2f6;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        background: #f0fdf4;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        color: #0e7490;
        flex-shrink: 0;
    }

    .stat-value {
        font-size: 19px;
        font-weight: 700;
        color: #1a2c3e;
        line-height: 1.2;
    }

    .stat-label {
        font-size: 11px;
        color: #6c7a8e;
        margin-top: 3px;
    }

    .data-table {
        overflow-x: auto;
        background: white;
        border-radius: 12px;
        padding: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #eef2f6;
    }

    .data-table table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
    }

    .data-table th {
        text-align: left;
        padding: 12px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        color: #1a2c3e;
    }

    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #eef2f6;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-upcoming {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-ongoing {
        background: #fed7aa;
        color: #92400e;
    }

    .status-completed {
        background: #d1fae5;
        color: #065f46;
    }

    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .priority-badge {
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }

    .priority-high {
        background: #fee2e2;
        color: #dc2626;
    }

    .priority-medium {
        background: #fef3c7;
        color: #d97706;
    }

    .priority-low {
        background: #d1fae5;
        color: #059669;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }

    .action-btn-small {
        padding: 5px 10px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-view {
        background: #e0e7ff;
        color: #3730a3;
    }

    .btn-view:hover {
        background: #c7d2fe;
    }

    .btn-edit {
        background: #dbeafe;
        color: #1e40af;
    }

    .btn-edit:hover {
        background: #bfdbfe;
    }

    .btn-delete {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-delete:hover {
        background: #fecaca;
    }

    .pagination-container {
        margin-top: 16px;
        padding-top: 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        border-top: 1px solid #eef2f6;
    }

    .pagination-info {
        color: #6c7a8e;
        font-size: 13px;
    }

    .pagination {
        display: flex;
        gap: 5px;
        align-items: center;
        flex-wrap: wrap;
    }

    .pagination-btn,
    .page-number {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        color: #495057;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.2s;
        cursor: pointer;
    }

    .pagination-btn:hover,
    .page-number:hover {
        background: #f8f9fa;
        border-color: #10263f;
        color: #10263f;
    }

    .page-number.active {
        background: #10263f;
        border-color: #10263f;
        color: white;
    }

    .records-per-page {
        margin-top: 12px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #6c7a8e;
    }

    .records-per-page select {
        padding: 5px 9px;
        border: 1.5px solid #e2e8f0;
        border-radius: 6px;
        background: white;
        font-size: 13px;
        cursor: pointer;
        outline: none;
    }

    .no-results {
        text-align: center;
        padding: 40px;
        color: #6c7a8e;
    }

    .no-results i {
        font-size: 48px;
        margin-bottom: 10px;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s;
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        width: 90%;
        max-width: 650px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        animation: slideDown 0.3s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        padding: 14px 18px;
        border-bottom: 1px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        color: #1a2c3e;
        font-size: 17px;
    }

    .close,
    .close-details {
        font-size: 18px;
        cursor: pointer;
        color: #9aa9bc;
        transition: 0.2s;
    }

    .close:hover,
    .close-details:hover {
        color: #c2410c;
    }

    .modal-body {
        padding: 18px;
        max-height: 70vh;
        overflow-y: auto;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .input-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .input-field label {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }

    .input-field input,
    .input-field select,
    .input-field textarea {
        padding: 9px 11px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        transition: all 0.2s;
        outline: none;
        font-family: inherit;
    }

    .input-field input:focus,
    .input-field select:focus,
    .input-field textarea:focus {
        border-color: #0e7490;
        box-shadow: 0 0 0 3px rgba(14, 116, 144, 0.08);
    }

    .full-width {
        grid-column: span 2;
    }

    .required-star {
        color: #c2410c;
    }

    .modal-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 18px;
        padding-top: 14px;
        border-top: 1px solid #eef2f6;
    }

    .btn-cancel {
        padding: 9px 18px;
        background: #f1f3f5;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: 0.2s;
    }

    .btn-cancel:hover {
        background: #e9ecef;
    }

    .btn-submit {
        padding: 9px 20px;
        background: #10263f;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s;
    }

    .btn-submit:hover {
        background: #0d2036;
    }

    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .detail-item {
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
    }

    .detail-label {
        font-size: 11px;
        color: #6c7a8e;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        font-size: 14px;
        font-weight: 600;
        color: #1a2c3e;
    }

    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #10263f;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 1100;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .full-width {
            grid-column: span 1;
        }

        .modal-content {
            margin: 10% auto;
            width: 95%;
        }

        .search-container {
            max-width: 100%;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .pagination-container {
            flex-direction: column;
            align-items: center;
        }

        .pagination {
            justify-content: center;
        }

        .page-actions {
            justify-content: stretch;
        }

        .page-actions button {
            flex: 1;
        }

        .filter-row {
            flex-direction: column;
        }

        .date-range-group {
            flex-direction: column;
        }

        .date-range-group span {
            display: none;
        }

        .filter-buttons-row {
            justify-content: stretch;
        }

        .filter-buttons-row button {
            flex: 1;
        }
    }
</style>

<script>
    let eventsData = [];
    let currentFilterStatus = 'all';
    let currentFilterPriority = 'all';
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    let currentPerPage = 10;
    let currentSearch = '';
    let currentUserRole = <?php echo $user_role_int; ?>;
    let canManageEvents = <?php echo $can_manage_events ? 'true' : 'false'; ?>;
    let dateFrom = '';
    let dateTo = '';

    async function loadEvents(page = 1) {
        try {
            currentPage = page;

            const formData = new FormData();
            formData.append('action', 'get_events');
            formData.append('filter_status', currentFilterStatus);
            formData.append('filter_priority', currentFilterPriority);
            formData.append('search', currentSearch);
            formData.append('date_from', dateFrom);
            formData.append('date_to', dateTo);
            formData.append('page', currentPage);
            formData.append('per_page', currentPerPage);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            if (result.success) {
                eventsData = result.data || [];
                totalPages = result.total_pages;
                totalRecords = result.total;
                renderTable();
                renderPagination();
                loadStatistics();
                updateActiveFiltersDisplay();
            } else {
                showToast(result.error || 'Failed to load events', 'error');
            }
        } catch (error) {
            console.error('Error loading events:', error);
            showToast('Error loading events', 'error');
            renderTable();
        }
    }

    function renderTable() {
        const tbody = document.getElementById('tableBody');
        const noResults = document.getElementById('noResults');

        if (!tbody) return;

        if (!eventsData || eventsData.length === 0) {
            const colspan = canManageEvents ? 9 : 8;
            tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center; padding: 40px;">No events found</td></tr>`;
            if (noResults) noResults.style.display = 'block';
            return;
        }

        tbody.innerHTML = '';
        if (noResults) noResults.style.display = 'none';

        const startSerial = ((currentPage - 1) * currentPerPage) + 1;

        eventsData.forEach((event, index) => {
            const row = tbody.insertRow();
            const startDate = new Date(event.start_date).toLocaleDateString();
            const endDate = event.end_date ? ' to ' + new Date(event.end_date).toLocaleDateString() : '';
            const serialNumber = startSerial + index;

            row.innerHTML = `
                <td>${serialNumber}</td>
                <td><strong>${escapeHtml(event.event_title)}</strong><br><small style="color:#666;">${escapeHtml((event.event_description || '').substring(0, 50))}${(event.event_description || '').length > 50 ? '...' : ''}</small></td>
                <td><span style="text-transform:capitalize">${event.event_type}</span></td>
                <td>${startDate}${endDate}</td>
                <td>${escapeHtml(event.location || '-')}</td>
                <td><span class="priority-badge priority-${event.priority}">${event.priority}</span></td>
                <td><span class="status-badge status-${event.status}">${event.status}</span></td>
            `;

            // Add actions column only if user can manage events
            if (canManageEvents) {
                const actionCell = row.insertCell();
                actionCell.innerHTML = `
                    <div class="action-buttons">
                        <button class="action-btn-small btn-view" onclick="viewDetails(${event.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn-small btn-edit" onclick="editEvent(${event.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn-small btn-delete" onclick="deleteEvent(${event.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
            } else {
                // Add view button for regular users
                const actionCell = row.insertCell();
                actionCell.innerHTML = `
                    <div class="action-buttons">
                        <button class="action-btn-small btn-view" onclick="viewDetails(${event.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                `;
            }
        });
    }

    function renderPagination() {
        const container = document.getElementById('paginationContainer');
        if (!container) return;

        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'flex';

        const offset = (currentPage - 1) * currentPerPage;
        const start = offset + 1;
        const end = Math.min(offset + currentPerPage, totalRecords);

        let paginationHtml = `
            <div class="pagination-info">
                Showing ${start} to ${end} of ${totalRecords} events
            </div>
            <div class="pagination">
        `;

        if (currentPage > 1) {
            paginationHtml += `<button onclick="loadEvents(1)" class="pagination-btn"><i class="fas fa-angle-double-left"></i></button>`;
            paginationHtml += `<button onclick="loadEvents(${currentPage - 1})" class="pagination-btn"><i class="fas fa-angle-left"></i></button>`;
        }

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) paginationHtml += `<span class="page-dots">...</span>`;

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `<button onclick="loadEvents(${i})" class="page-number ${i === currentPage ? 'active' : ''}">${i}</button>`;
        }

        if (endPage < totalPages) paginationHtml += `<span class="page-dots">...</span>`;

        if (currentPage < totalPages) {
            paginationHtml += `<button onclick="loadEvents(${currentPage + 1})" class="pagination-btn"><i class="fas fa-angle-right"></i></button>`;
            paginationHtml += `<button onclick="loadEvents(${totalPages})" class="pagination-btn"><i class="fas fa-angle-double-right"></i></button>`;
        }

        paginationHtml += `</div>`;
        container.innerHTML = paginationHtml;
    }

    async function loadStatistics() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_stats');
            formData.append('filter_status', currentFilterStatus);
            formData.append('filter_priority', currentFilterPriority);
            formData.append('date_from', dateFrom);
            formData.append('date_to', dateTo);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            if (result.success && result.data) {
                document.getElementById('upcomingCount').textContent = result.data.upcoming || 0;
                document.getElementById('ongoingCount').textContent = result.data.ongoing || 0;
                document.getElementById('completedCount').textContent = result.data.completed || 0;
                document.getElementById('totalInRange').textContent = result.data.total_in_range || 0;
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }

    function updateActiveFiltersDisplay() {
        const container = document.getElementById('activeFiltersContainer');
        const list = document.getElementById('activeFiltersList');

        if (!container || !list) return;

        let filters = [];

        if (dateFrom || dateTo) {
            let rangeText = '';
            if (dateFrom && dateTo) {
                rangeText = `📅 ${new Date(dateFrom).toLocaleDateString()} - ${new Date(dateTo).toLocaleDateString()}`;
            } else if (dateFrom) {
                rangeText = `📅 From ${new Date(dateFrom).toLocaleDateString()}`;
            } else if (dateTo) {
                rangeText = `📅 Until ${new Date(dateTo).toLocaleDateString()}`;
            }
            filters.push({ type: 'date', text: rangeText });
        }

        if (currentFilterStatus !== 'all') {
            const statusLabels = { upcoming: 'Upcoming', ongoing: 'Ongoing', completed: 'Completed', cancelled: 'Cancelled' };
            filters.push({ type: 'status', text: `📌 Status: ${statusLabels[currentFilterStatus]}` });
        }

        if (currentFilterPriority !== 'all') {
            filters.push({ type: 'priority', text: `⚡ Priority: ${currentFilterPriority.toUpperCase()}` });
        }

        if (currentSearch) {
            filters.push({ type: 'search', text: `🔍 Search: "${currentSearch}"` });
        }

        if (filters.length > 0) {
            container.style.display = 'flex';
            list.innerHTML = filters.map(filter => `
                <span class="filter-tag">
                    ${filter.text}
                    <button class="remove-filter-tag" onclick="removeFilter('${filter.type}')"><i class="fas fa-times"></i></button>
                </span>
            `).join('');
        } else {
            container.style.display = 'none';
        }
    }

    function removeFilter(type) {
        if (type === 'date') {
            dateFrom = '';
            dateTo = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
        } else if (type === 'status') {
            currentFilterStatus = 'all';
            document.getElementById('filterStatus').value = 'all';
        } else if (type === 'priority') {
            currentFilterPriority = 'all';
            document.getElementById('filterPriority').value = 'all';
        } else if (type === 'search') {
            currentSearch = '';
            document.getElementById('searchInput').value = '';
            document.getElementById('clearSearch').style.display = 'none';
        }
        currentPage = 1;
        loadEvents(1);
    }

    async function editEvent(id) {
        if (!canManageEvents) {
            showToast('Permission denied. Only administrators can edit events.', 'error');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'get_event');
            formData.append('id', id);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const result = await response.json();

            if (result.success) {
                const event = result.data;
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Event';
                document.getElementById('eventId').value = event.id;
                document.getElementById('title').value = event.event_title;
                document.getElementById('description').value = event.event_description || '';
                document.getElementById('eventType').value = event.event_type;
                document.getElementById('startDate').value = event.start_date;
                document.getElementById('endDate').value = event.end_date || '';
                document.getElementById('location').value = event.location || '';
                document.getElementById('priority').value = event.priority;
                document.getElementById('eventStatus').value = event.status;
                document.getElementById('eventModal').style.display = 'block';
            } else {
                showToast(result.error || 'Failed to load event', 'error');
            }
        } catch (error) {
            console.error('Error loading event:', error);
            showToast('Error loading event details', 'error');
        }
    }

    function viewDetails(id) {
        const event = eventsData.find(e => e.id == id);
        if (!event) return;

        const detailsContent = document.getElementById('detailsContent');
        if (!detailsContent) return;

        const startDate = new Date(event.start_date).toLocaleDateString();
        const endDate = event.end_date ? new Date(event.end_date).toLocaleDateString() : 'N/A';
        const createdDate = event.created_at ? new Date(event.created_at).toLocaleString() : 'N/A';

        detailsContent.innerHTML = `
            <div class="details-grid">
                <div class="detail-item full-width"><div class="detail-label">Event Title</div><div class="detail-value">${escapeHtml(event.event_title)}</div></div>
                <div class="detail-item"><div class="detail-label">Event Type</div><div class="detail-value">${event.event_type}</div></div>
                <div class="detail-item"><div class="detail-label">Priority</div><div class="detail-value priority-${event.priority}">${event.priority}</div></div>
                <div class="detail-item"><div class="detail-label">Start Date</div><div class="detail-value">${startDate}</div></div>
                <div class="detail-item"><div class="detail-label">End Date</div><div class="detail-value">${endDate}</div></div>
                <div class="detail-item"><div class="detail-label">Location</div><div class="detail-value">${escapeHtml(event.location || 'N/A')}</div></div>
                <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${event.status}</div></div>
                <div class="detail-item"><div class="detail-label">Created On</div><div class="detail-value">${createdDate}</div></div>
                <div class="detail-item full-width"><div class="detail-label">Description</div><div class="detail-value">${escapeHtml(event.event_description || 'No description provided.')}</div></div>
            </div>
        `;

        const detailsModal = document.getElementById('detailsModal');
        if (detailsModal) detailsModal.style.display = 'block';
    }

    async function deleteEvent(id) {
        if (!canManageEvents) {
            showToast('Permission denied. Only administrators can delete events.', 'error');
            return;
        }

        if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_event');
            formData.append('id', id);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            if (result.success) {
                showToast('Event deleted successfully', 'success');
                loadEvents(1);
            } else {
                showToast(result.error || 'Failed to delete event', 'error');
            }
        } catch (error) {
            console.error('Error deleting event:', error);
            showToast('Error deleting event', 'error');
        }
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');

        if (!toast || !toastMessage) return;

        toastMessage.textContent = message;
        toast.style.backgroundColor = type === 'success' ? '#10263f' : '#dc2626';
        toast.style.display = 'block';

        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function openModal() {
        if (!canManageEvents) {
            showToast('Permission denied. Only administrators can create events.', 'error');
            return;
        }

        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> नयाँ कार्यक्रम थप्नुहोस्';
        document.getElementById('eventId').value = '0';
        document.getElementById('eventForm').reset();
        document.getElementById('eventStatus').value = 'upcoming';
        document.getElementById('eventModal').style.display = 'block';
    }

    function closeModal() {
        const modal = document.getElementById('eventModal');
        if (modal) modal.style.display = 'none';
    }

    function applyFilters() {
        dateFrom = document.getElementById('dateFrom').value;
        dateTo = document.getElementById('dateTo').value;
        currentFilterStatus = document.getElementById('filterStatus').value;
        currentFilterPriority = document.getElementById('filterPriority').value;
        currentPage = 1;
        loadEvents(1);
        document.getElementById('advancedFilterSection').style.display = 'none';
    }

    function clearAllFilters() {
        dateFrom = '';
        dateTo = '';
        currentFilterStatus = 'all';
        currentFilterPriority = 'all';
        currentSearch = '';

        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        document.getElementById('filterStatus').value = 'all';
        document.getElementById('filterPriority').value = 'all';
        document.getElementById('searchInput').value = '';
        document.getElementById('clearSearch').style.display = 'none';

        currentPage = 1;
        loadEvents(1);
        document.getElementById('advancedFilterSection').style.display = 'none';
        showToast('All filters cleared', 'success');
    }

    function resetAllFilters() {
        clearAllFilters();
    }

    function searchEvents() {
        currentSearch = document.getElementById('searchInput').value;
        currentPage = 1;
        loadEvents(1);

        const clearBtn = document.getElementById('clearSearch');
        if (clearBtn) {
            clearBtn.style.display = currentSearch.trim() !== '' ? 'flex' : 'none';
        }
    }

    function clearSearch() {
        document.getElementById('searchInput').value = '';
        searchEvents();
        document.getElementById('searchInput').focus();
    }

    // Event Listeners
    <?php if ($can_manage_events): ?>
        document.getElementById('addEventBtn').onclick = openModal;
        document.getElementById('cancelModalBtn').onclick = closeModal;
        document.querySelector('.close').onclick = closeModal;

        // Form submission for Create/Update
        document.getElementById('eventForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const eventId = document.getElementById('eventId').value;
            const action = eventId == 0 ? 'create_event' : 'update_event';
            const title = document.getElementById('title').value;
            const startDate = document.getElementById('startDate').value;

            if (!title || !startDate) {
                showToast('Please fill all required fields', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            formData.append('title', title);
            formData.append('description', document.getElementById('description').value);
            formData.append('event_type', document.getElementById('eventType').value);
            formData.append('start_date', startDate);
            formData.append('end_date', document.getElementById('endDate').value);
            formData.append('location', document.getElementById('location').value);
            formData.append('priority', document.getElementById('priority').value);
            formData.append('status', document.getElementById('eventStatus').value);

            if (eventId != 0) {
                formData.append('id', eventId);
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();

                if (result.success) {
                    showToast(eventId == 0 ? 'Event created successfully' : 'Event updated successfully', 'success');
                    closeModal();
                    loadEvents(1);
                } else {
                    showToast(result.error || 'Failed to save event', 'error');
                }
            } catch (error) {
                console.error('Error saving event:', error);
                showToast('Error saving event', 'error');
            }
        });
    <?php endif; ?>

    document.querySelector('.close-details').onclick = () => {
        document.getElementById('detailsModal').style.display = 'none';
    };

    document.getElementById('toggleAdvancedFilterBtn').onclick = () => {
        const filterSection = document.getElementById('advancedFilterSection');
        filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
    };

    document.getElementById('closeAdvancedFilter').onclick = () => {
        document.getElementById('advancedFilterSection').style.display = 'none';
    };

    document.getElementById('applyFilters').onclick = applyFilters;
    document.getElementById('clearFilters').onclick = clearAllFilters;
    document.getElementById('resetFiltersBtn').onclick = resetAllFilters;

    document.getElementById('recordsPerPage').addEventListener('change', function () {
        currentPerPage = parseInt(this.value);
        currentPage = 1;
        loadEvents(1);
    });

    document.getElementById('searchInput').addEventListener('input', function () {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(searchEvents, 500);
    });

    document.getElementById('clearSearch').onclick = clearSearch;

    // Export functionality
    document.getElementById('exportBtn')?.addEventListener('click', function () {
        if (!eventsData || eventsData.length === 0) {
            showToast('No data to export', 'error');
            return;
        }

        const rows = [['S.N.', 'Event Title', 'Type', 'Start Date', 'End Date', 'Location', 'Priority', 'Status', 'Description']];
        const startSerial = ((currentPage - 1) * currentPerPage) + 1;

        eventsData.forEach((event, index) => {
            rows.push([
                startSerial + index,
                event.event_title,
                event.event_type,
                event.start_date,
                event.end_date || '',
                event.location || '',
                event.priority,
                event.status,
                event.event_description || ''
            ]);
        });

        const csvContent = rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `events_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('Events exported successfully', 'success');
    });

    // Set min date for start date
    const today = new Date().toISOString().split('T')[0];
    const startDateInput = document.getElementById('startDate');
    if (startDateInput) startDateInput.min = today;

    // Initial load
    document.addEventListener('DOMContentLoaded', function () {
        loadEvents(1);

        // Auto-refresh every 60 seconds
        setInterval(function () {
            loadEvents(currentPage);
        }, 60000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            if (e.key === 'n' || e.key === 'N') {
                if (!e.target.matches('input, textarea, select') && canManageEvents) {
                    e.preventDefault();
                    openModal();
                }
            }

            if (e.key === 'Escape') {
                closeModal();
                document.getElementById('detailsModal').style.display = 'none';
                document.getElementById('advancedFilterSection').style.display = 'none';
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>