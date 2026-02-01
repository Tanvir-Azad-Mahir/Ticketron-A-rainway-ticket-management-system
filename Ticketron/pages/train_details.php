<?php
session_start();
include '../db.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get parameters from URL
$schedule_id = isset($_GET['schedule_id']) ? $_GET['schedule_id'] : '';
$train_id = isset($_GET['train_id']) ? $_GET['train_id'] : '';
$travel_date = isset($_GET['travel_date']) ? $_GET['travel_date'] : '';
$from_station = isset($_GET['from_station']) ? $_GET['from_station'] : '';
$to_station = isset($_GET['to_station']) ? $_GET['to_station'] : '';

// Validate parameters
if (empty($schedule_id) || empty($train_id) || empty($travel_date) || empty($from_station) || empty($to_station)) {
    header("Location: dashboard.php");
    exit();
}

// Try multiple query approaches to find the train
$train = null;

// First, try exact match with schedule_id
$stmt = $conn->prepare("
    SELECT 
        t.train_id,
        t.name as train_name,
        t.type,
        t.status,
        s.schedule_id,
        s.travel_date,
        s.start_time,
        COALESCE(r.route_name, 'N/A') as route_name,
        COALESCE(r.route_id, '') as route_id
    FROM schedule s
    INNER JOIN train t ON s.train_id = t.train_id
    LEFT JOIN route r ON t.route_id = r.route_id
    WHERE s.schedule_id = ? AND s.train_id = ? AND DATE(s.travel_date) = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("sss", $schedule_id, $train_id, $travel_date);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $train = $result->fetch_assoc();
        }
    }
    $stmt->close();
}

// If first query failed, try without date constraint
if (!$train) {
    $stmt = $conn->prepare("
        SELECT 
            t.train_id,
            t.name as train_name,
            t.type,
            t.status,
            s.schedule_id,
            s.travel_date,
            s.start_time,
            COALESCE(r.route_name, 'N/A') as route_name,
            COALESCE(r.route_id, '') as route_id
        FROM schedule s
        INNER JOIN train t ON s.train_id = t.train_id
        LEFT JOIN route r ON t.route_id = r.route_id
        WHERE s.schedule_id = ? AND s.train_id = ?
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("ss", $schedule_id, $train_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $train = $result->fetch_assoc();
            }
        }
        $stmt->close();
    }
}

// If still not found, try just by train_id with any schedule
if (!$train) {
    $stmt = $conn->prepare("
        SELECT 
            t.train_id,
            t.name as train_name,
            t.type,
            t.status,
            s.schedule_id,
            s.travel_date,
            s.start_time,
            COALESCE(r.route_name, 'N/A') as route_name,
            COALESCE(r.route_id, '') as route_id
        FROM schedule s
        INNER JOIN train t ON s.train_id = t.train_id
        LEFT JOIN route r ON t.route_id = r.route_id
        WHERE s.train_id = ?
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $train_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $train = $result->fetch_assoc();
                // Override with the actual travel date and schedule_id from parameters
                $train['travel_date'] = $travel_date;
                $train['schedule_id'] = $schedule_id;
            }
        }
        $stmt->close();
    }
}

// Last resort: Get train info without requiring a schedule
if (!$train) {
    $stmt = $conn->prepare("
        SELECT 
            t.train_id,
            t.name as train_name,
            t.type,
            t.status,
            COALESCE(r.route_name, 'N/A') as route_name,
            COALESCE(r.route_id, '') as route_id
        FROM train t
        LEFT JOIN route r ON t.route_id = r.route_id
        WHERE t.train_id = ?
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $train_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $train = $result->fetch_assoc();
                // Use parameters since no schedule exists yet
                $train['schedule_id'] = $schedule_id;
                $train['travel_date'] = $travel_date;
                $train['start_time'] = '08:00:00'; // Default time
            }
        }
        $stmt->close();
    }
}

if (!$train) {
    // Show debug page instead of redirecting
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Error - Ticketron</title>
        <style>
            body { font-family: Arial; margin: 40px; background: #f0f4f8; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
            h1 { color: #ef4444; }
            .debug { background: #f8fafc; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; font-size: 12px; }
            a { color: #2563eb; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Unable to Load Train Details</h1>
            <p>The train details could not be found with the provided information:</p>
            <div class="debug">
                Schedule ID: <?php echo htmlspecialchars($schedule_id); ?><br>
                Train ID: <?php echo htmlspecialchars($train_id); ?><br>
                Travel Date: <?php echo htmlspecialchars($travel_date); ?>
            </div>
            <p>This might mean:</p>
            <ul>
                <li>The schedule is not available for this date</li>
                <li>The train ID is not linked to this schedule</li>
                <li>The database doesn't have this schedule</li>
            </ul>
            <p><a href="dashboard.php">← Back to Dashboard</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// $train already has the data from one of the queries above
// No need to fetch again

// Get from and to station names
$from_station_data = ['name' => 'Station', 'city' => 'Unknown'];
$to_station_data = ['name' => 'Station', 'city' => 'Unknown'];

$stmt = $conn->prepare("SELECT Station_id, name, city FROM stations WHERE Station_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $from_station);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $from_station_data = $row;
        }
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT Station_id, name, city FROM stations WHERE Station_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $to_station);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $to_station_data = $row;
        }
    }
    $stmt->close();
}

// Get coaches with seat availability
$coaches = [];

$query = "SELECT DISTINCT c.coach_id, c.coach_type FROM coach c WHERE c.train_id = ? ORDER BY c.coach_type";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("s", $train_id);
    if ($stmt->execute()) {
        $coaches_result = $stmt->get_result();
        while ($coach = $coaches_result->fetch_assoc()) {
            $coach['total_seats'] = 0;
            $coach['booked_seats'] = 0;
            
            // Get total seats
            $seat_stmt = $conn->prepare("SELECT COUNT(*) as total_seats FROM seat WHERE coach_id = ?");
            if ($seat_stmt) {
                $seat_stmt->bind_param("s", $coach['coach_id']);
                if ($seat_stmt->execute()) {
                    $seat_result = $seat_stmt->get_result();
                    $seat_row = $seat_result->fetch_assoc();
                    $coach['total_seats'] = $seat_row['total_seats'] ?? 0;
                }
                $seat_stmt->close();
            }
            
            // Get booked seats (JOIN with seat table since ticket doesn't have coach_id)
            $booked_stmt = $conn->prepare("SELECT COUNT(*) as booked_seats 
                                           FROM ticket t
                                           JOIN seat s ON t.seat_id = s.seat_id
                                           WHERE s.coach_id = ? AND t.schedule_id = ? AND t.booking_status = 'Booked'");
            if ($booked_stmt) {
                $booked_stmt->bind_param("ss", $coach['coach_id'], $schedule_id);
                if ($booked_stmt->execute()) {
                    $booked_result = $booked_stmt->get_result();
                    $booked_row = $booked_result->fetch_assoc();
                    $coach['booked_seats'] = $booked_row['booked_seats'] ?? 0;
                }
                $booked_stmt->close();
            }
            
            $coach['available_seats'] = $coach['total_seats'] - $coach['booked_seats'];
            $coach['capacity'] = $coach['total_seats'];
            
            $coaches[] = $coach;
        }
    }
    $stmt->close();
}

// Get ticket prices
$ticket_prices = [];
$stmt = $conn->prepare("SELECT coach_type, price FROM ticket_price WHERE coach_type = ? LIMIT 1");
if ($stmt) {
    foreach ($coaches as $coach) {
        $stmt->bind_param("s", $coach['coach_type']);
        if ($stmt->execute()) {
            $price_result = $stmt->get_result();
            if ($price_row = $price_result->fetch_assoc()) {
                $ticket_prices[$coach['coach_type']] = $price_row['price'];
            } else {
                $ticket_prices[$coach['coach_type']] = 'N/A';
            }
        }
    }
    $stmt->close();
} else {
    foreach ($coaches as $coach) {
        $ticket_prices[$coach['coach_type']] = 'N/A';
    }
}

// Get route details
$route_details = [];
if (!empty($train['route_id'])) {
    $stmt = $conn->prepare("SELECT total_distance FROM route WHERE route_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $train['route_id']);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($route_row = $result->fetch_assoc()) {
                $route_details['total_distance'] = $route_row['total_distance'] ?? 0;
            }
        }
        $stmt->close();
    }
}

// Get all route stations with distances
$route_stations = [];
if (!empty($train['route_id'])) {
    $stmt = $conn->prepare("
        SELECT rs.station_order, rs.station_id, rs.start_to_distance, s.name, s.city 
        FROM route_station rs 
        JOIN stations s ON rs.station_id = s.station_id 
        WHERE rs.route_id = ? 
        ORDER BY rs.station_order ASC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $train['route_id']);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $route_stations[] = $row;
            }
        }
        $stmt->close();
    }
}

// Calculate journey duration (simple estimate: ~60 km/hour average)
$journey_distance = $route_details['total_distance'] ?? 0;
$estimated_duration = ceil($journey_distance / 60);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Train Details | Ticketron</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --blue-50: #eff6ff;
            --blue-100: #dbeafe;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }

        .back-button:hover {
            background: var(--blue-50);
            border-color: var(--primary-color);
        }

        .train-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .train-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .train-name {
            font-size: 28px;
            font-weight: 700;
        }

        .train-type-badge {
            background: var(--blue-50);
            color: var(--primary-color);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .journey-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: var(--blue-50);
            border-radius: 8px;
            border: 1px solid var(--blue-100);
        }

        .info-label {
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 700;
        }

        .coaches-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .coaches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .coach-card {
            background: var(--light-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .coach-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }

        .coach-type {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .coach-price {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .seat-stat {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 8px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .book-coach-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
        }

        .book-coach-btn:hover {
            transform: translateY(-2px);
        }

        .book-coach-btn:disabled {
            background: var(--border-color);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .footer-info {
            background: var(--blue-50);
            border: 1px solid var(--blue-100);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .route-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .route-timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline-item {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            position: relative;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 12px;
            top: 40px;
            width: 2px;
            height: calc(100% + 20px);
            background: var(--border-color);
        }

        .timeline-dot {
            min-width: 26px;
            width: 26px;
            height: 26px;
            background: var(--primary-color);
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 1px var(--border-color);
            position: relative;
            z-index: 1;
            margin-top: 5px;
        }

        .timeline-dot.start {
            background: var(--success-color);
        }

        .timeline-dot.end {
            background: var(--danger-color);
        }

        .timeline-content {
            flex: 1;
            padding-top: 5px;
        }

        .station-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .station-city {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .station-distance {
            font-size: 12px;
            color: var(--primary-color);
            font-weight: 600;
        }

        .train-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: var(--blue-50);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--blue-100);
            text-align: center;
        }

        .stat-label {
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .journey-info {
                grid-template-columns: 1fr;
            }
            .coaches-grid {
                grid-template-columns: 1fr;
            }
            .timeline-item {
                gap: 15px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Search
    </a>

    <div class="train-header">
        <div class="train-title">
            <div>
                <div class="train-name"><?php echo htmlspecialchars($train['train_name']); ?></div>
                <div class="train-type-badge"><?php echo htmlspecialchars($train['type']); ?></div>
            </div>
        </div>

        <div class="train-stats">
            <div class="stat-box">
                <div class="stat-label">Total Distance</div>
                <div class="stat-value"><?php echo number_format($journey_distance, 0); ?> km</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Estimated Duration</div>
                <div class="stat-value"><?php echo $estimated_duration; ?> hrs</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Stops</div>
                <div class="stat-value"><?php echo count($route_stations); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Coaches</div>
                <div class="stat-value"><?php echo count($coaches); ?></div>
            </div>
        </div>

        <div class="journey-info">
            <div class="info-item">
                <div class="info-label">From Station</div>
                <div class="info-value"><?php echo htmlspecialchars($from_station_data['name']); ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;"><?php echo htmlspecialchars($from_station_data['city']); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">To Station</div>
                <div class="info-value"><?php echo htmlspecialchars($to_station_data['name']); ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;"><?php echo htmlspecialchars($to_station_data['city']); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Travel Date</div>
                <div class="info-value"><?php echo date('M d, Y', strtotime($travel_date)); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Departure Time</div>
                <div class="info-value"><?php echo date('H:i', strtotime($train['start_time'])); ?></div>
            </div>
        </div>
    </div>

    <?php if (count($route_stations) > 0): ?>
    <div class="route-section">
        <h2 class="section-title">
            <i class="fas fa-map-marker-alt"></i> Route & Stops
        </h2>
        
        <div class="route-timeline">
            <?php foreach ($route_stations as $index => $station): 
                $is_start = ($index === 0);
                $is_end = ($index === count($route_stations) - 1);
                $dot_class = $is_start ? 'start' : ($is_end ? 'end' : '');
            ?>
                <div class="timeline-item">
                    <div class="timeline-dot <?php echo $dot_class; ?>"></div>
                    <div class="timeline-content">
                        <div class="station-city"><?php echo htmlspecialchars($station['city']); ?></div>
                        <div class="station-name"><?php echo htmlspecialchars($station['name']); ?></div>
                        <div class="station-distance">
                            <i class="fas fa-road"></i> <?php echo number_format($station['start_to_distance'], 2); ?> km from start
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="coaches-section">
        <h2 class="section-title">
            <i class="fas fa-door-open"></i> Available Coaches & Seats
        </h2>

        <div class="coaches-grid">
            <?php if (count($coaches) > 0): ?>
                <?php foreach ($coaches as $coach): 
                    $price = isset($ticket_prices[$coach['coach_type']]) ? $ticket_prices[$coach['coach_type']] : 'N/A';
                    $is_available = $coach['available_seats'] > 0;
                ?>
                    <div class="coach-card">
                        <div class="coach-type"><?php echo htmlspecialchars($coach['coach_type']); ?></div>
                        <div class="coach-price"><i class="fas fa-tag"></i> ৳<?php echo htmlspecialchars($price); ?></div>
                        
                        <div class="seat-stat">
                            <span>Available:</span>
                            <span style="color: var(--success-color); font-weight: 700;"><?php echo $coach['available_seats']; ?></span>
                        </div>
                        <div class="seat-stat">
                            <span>Booked:</span>
                            <span style="color: var(--danger-color); font-weight: 700;"><?php echo $coach['booked_seats']; ?></span>
                        </div>
                        <div class="seat-stat">
                            <span>Total:</span>
                            <span><?php echo $coach['capacity']; ?></span>
                        </div>

                        <form method="POST" action="seat_selection.php" style="margin-top: 15px;">
                            <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($schedule_id); ?>">
                            <input type="hidden" name="train_id" value="<?php echo htmlspecialchars($train_id); ?>">
                            <input type="hidden" name="coach_id" value="<?php echo htmlspecialchars($coach['coach_id']); ?>">
                            <input type="hidden" name="coach_type" value="<?php echo htmlspecialchars($coach['coach_type']); ?>">
                            <input type="hidden" name="travel_date" value="<?php echo htmlspecialchars($travel_date); ?>">
                            <input type="hidden" name="from_station" value="<?php echo htmlspecialchars($from_station); ?>">
                            <input type="hidden" name="to_station" value="<?php echo htmlspecialchars($to_station); ?>">
                            <input type="hidden" name="price" value="<?php echo htmlspecialchars($price); ?>">
                            
                            <button type="submit" class="book-coach-btn" <?php echo !$is_available ? 'disabled' : ''; ?>>
                                <i class="fas fa-check-circle"></i> <?php echo $is_available ? 'Book Now' : 'No Seats'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; padding: 40px 20px; text-align: center; color: var(--text-secondary);">
                    <p>No coaches available for this train. Please contact support.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Train ID: <?php echo htmlspecialchars($train_id); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-info">
            <i class="fas fa-info-circle"></i> Select a coach and click "Book Now" to choose your seats
        </div>
    </div>
</div>

</body>
</html>