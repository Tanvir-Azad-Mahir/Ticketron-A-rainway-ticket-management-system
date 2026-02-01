<?php
session_start();
include '../db.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get parameters
$schedule_id = $_POST['schedule_id'] ?? $_GET['schedule_id'] ?? '';
$train_id = $_POST['train_id'] ?? $_GET['train_id'] ?? '';
$coach_id = $_POST['coach_id'] ?? $_GET['coach_id'] ?? '';
$coach_type = $_POST['coach_type'] ?? $_GET['coach_type'] ?? '';
$travel_date = $_POST['travel_date'] ?? $_GET['travel_date'] ?? '';
$from_station = $_POST['from_station'] ?? $_GET['from_station'] ?? '';
$to_station = $_POST['to_station'] ?? $_GET['to_station'] ?? '';
$price = $_POST['price'] ?? $_GET['price'] ?? 0;

// Validate required parameters
if (empty($schedule_id) || empty($train_id) || empty($coach_id)) {
    header("Location: dashboard.php");
    exit();
}

// Get train details
$train_details = [];
$stmt = $conn->prepare("SELECT train_id, name as train_name, type FROM train WHERE train_id = ?");
$stmt->bind_param("s", $train_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $train_details = $result->fetch_assoc();
}
$stmt->close();

// Get coach details
$coach_details = [];
$stmt = $conn->prepare("SELECT coach_id, name as coach_name, coach_type, seat_count FROM coach WHERE coach_id = ?");
$stmt->bind_param("s", $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $coach_details = $result->fetch_assoc();
}
$stmt->close();

// Get ticket price if not provided
if (empty($price) && !empty($coach_type)) {
    $stmt = $conn->prepare("SELECT price FROM ticket_price WHERE coach_type = ?");
    $stmt->bind_param("s", $coach_type);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $price = $row['price'];
    }
    $stmt->close();
}

// Get station names
$from_station_name = 'Station';
$to_station_name = 'Station';

$stmt = $conn->prepare("SELECT name, city FROM stations WHERE station_id = ?");
if ($stmt) {
    $stmt->bind_param("s", $from_station);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $from_station_name = $row['name'] . ', ' . $row['city'];
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT name, city FROM stations WHERE station_id = ?");
if ($stmt) {
    $stmt->bind_param("s", $to_station);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $to_station_name = $row['name'] . ', ' . $row['city'];
    }
    $stmt->close();
}

// Get all seats for this coach with seat type
$seats = [];
$stmt = $conn->prepare("SELECT seat_id, seat_number FROM seat WHERE coach_id = ? ORDER BY seat_number");
$stmt->bind_param("s", $coach_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $seats[] = $row;
}
$stmt->close();

// Function to determine seat type based on position
function getSeatType($position_in_row) {
    // Position 0,1 = left side, Position 2,3 = right side
    // Window: positions 0 and 3
    // Middle: positions 1 and 2
    if ($position_in_row == 0 || $position_in_row == 3) {
        return 'window';
    } else {
        return 'middle';
    }
}

// Get booked seats for this schedule and coach
$booked_seats = [];
$stmt = $conn->prepare("
    SELECT t.seat_id 
    FROM ticket t 
    INNER JOIN seat s ON t.seat_id = s.seat_id 
    WHERE t.schedule_id = ? 
    AND s.coach_id = ? 
    AND t.booking_status = 'Booked'
");
if ($stmt) {
    $stmt->bind_param("ss", $schedule_id, $coach_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $booked_seats[] = $row['seat_id'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Seats - Ticketron</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-800: #1e293b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            font-size: 28px;
            color: var(--gray-800);
            margin-bottom: 15px;
        }

        .journey-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px;
            background: var(--gray-100);
            border-radius: 10px;
        }

        .info-box {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            color: var(--gray-600);
            font-weight: 600;
        }

        .info-value {
            font-size: 16px;
            color: var(--gray-800);
            font-weight: 700;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 20px;
            align-items: start;
        }

        .seat-map-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .summary-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 20px;
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            padding: 15px;
            background: var(--gray-100);
            border-radius: 8px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .legend-box {
            width: 35px;
            height: 35px;
            border-radius: 6px;
            border: 2px solid;
        }

        .legend-box.available {
            background: white;
            border-color: var(--gray-300);
        }

        .legend-box.selected {
            background: var(--success);
            border-color: var(--success);
        }

        .legend-box.booked {
            background: #ef4444;
            border-color: #dc2626;
            cursor: not-allowed;
        }

        .coach-layout {
            position: relative;
            background: linear-gradient(to right, #f8fafc 0%, #e2e8f0 50%, #f8fafc 100%);
            border-radius: 20px;
            padding: 30px;
            border: 4px solid var(--gray-300);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .driver-section {
            text-align: center;
            margin-bottom: 25px;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
        }

        .seats-container {
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 10px 0;
        }

        .seat-row {
            display: grid;
            grid-template-columns: auto repeat(2, auto) 40px repeat(2, auto);
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .seat {
            width: 55px;
            height: 65px;
            border: none;
            border-radius: 12px 12px 8px 8px;
            background: linear-gradient(145deg, #ffffff 0%, #e8f0fe 50%, #f1f5f9 100%);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: var(--gray-700);
            position: relative;
            box-shadow: 
                0 6px 12px rgba(0, 0, 0, 0.1),
                inset 0 -2px 6px rgba(0, 0, 0, 0.08),
                inset 0 2px 4px rgba(255, 255, 255, 0.8);
        }

        .seat::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 48px;
            height: 10px;
            background: linear-gradient(145deg, #cbd5e1 0%, #94a3b8 50%, #e2e8f0 100%);
            border-radius: 10px 10px 0 0;
            box-shadow: 
                0 2px 4px rgba(0, 0, 0, 0.15),
                inset 0 -1px 3px rgba(0, 0, 0, 0.1);
        }

        .seat.window::before {
            background: linear-gradient(145deg, #3b82f6 0%, #2563eb 50%, #60a5fa 100%);
            box-shadow: 
                0 3px 6px rgba(59, 130, 246, 0.4),
                inset 0 -2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Subtle side stripe to visually mark window seats */
        .seat.window {
            box-shadow: 
                0 6px 12px rgba(0, 0, 0, 0.1),
                inset 0 -2px 6px rgba(0, 0, 0, 0.08),
                inset 4px 0 0 #3b82f6;
        }

        /* When booked, keep solid red look (no blue stripe) */
        .seat.booked.window {
            box-shadow: 
                0 6px 12px rgba(239, 68, 68, 0.3),
                inset 0 -3px 8px rgba(0, 0, 0, 0.2),
                inset 0 0 0 transparent;
        }

        .seat:hover:not(.booked) {
            transform: translateY(-5px) scale(1.08);
            box-shadow: 
                0 10px 20px rgba(37, 99, 235, 0.3),
                inset 0 -2px 6px rgba(0, 0, 0, 0.08),
                inset 0 2px 6px rgba(255, 255, 255, 0.9);
            background: linear-gradient(145deg, #e0f2fe 0%, #bae6fd 50%, #dbeafe 100%);
        }

        .seat.selected {
            background: linear-gradient(145deg, #10b981 0%, #059669 50%, #34d399 100%);
            color: white;
            transform: translateY(-8px) scale(1.1);
            box-shadow: 
                0 12px 25px rgba(16, 185, 129, 0.5),
                inset 0 2px 6px rgba(255, 255, 255, 0.4),
                inset 0 -2px 6px rgba(0, 0, 0, 0.15);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 
                    0 12px 25px rgba(16, 185, 129, 0.5),
                    inset 0 2px 6px rgba(255, 255, 255, 0.4),
                    inset 0 -2px 6px rgba(0, 0, 0, 0.15);
            }
            50% {
                box-shadow: 
                    0 12px 35px rgba(16, 185, 129, 0.7),
                    inset 0 2px 6px rgba(255, 255, 255, 0.5),
                    inset 0 -2px 6px rgba(0, 0, 0, 0.15);
            }
        }

        .seat.selected::after {
            content: '✓';
            position: absolute;
            top: 3px;
            right: 3px;
            font-size: 14px;
            background: white;
            color: #059669;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .seat.booked {
            background: linear-gradient(145deg, #ef4444 0%, #dc2626 50%, #f87171 100%);
            cursor: not-allowed;
            opacity: 0.85;
            color: white;
            box-shadow: 
                0 6px 12px rgba(239, 68, 68, 0.3),
                inset 0 -3px 8px rgba(0, 0, 0, 0.2),
                inset 0 2px 4px rgba(255, 255, 255, 0.2);
            filter: grayscale(10%);
        }

        .seat.booked::before {
            background: linear-gradient(145deg, #991b1b 0%, #7f1d1d 50%, #b91c1c 100%);
            box-shadow: 
                0 3px 6px rgba(153, 27, 27, 0.4),
                inset 0 -2px 4px rgba(0, 0, 0, 0.3);
        }

        .aisle-space {
            width: 40px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .aisle-space::before {
            content: '';
            position: absolute;
            width: 12px;
            height: 100%;
            background: repeating-linear-gradient(
                to bottom,
                var(--gray-300) 0px,
                var(--gray-300) 12px,
                transparent 12px,
                transparent 24px
            );
            border-radius: 6px;
        }

        .row-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--gray-600);
            background: var(--gray-200);
            padding: 6px 10px;
            border-radius: 8px;
            justify-self: end;
            min-width: 52px;
            text-align: center;
        }

        .summary-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .selected-seats-list {
            margin: 20px 0;
            padding: 15px;
            background: var(--gray-100);
            border-radius: 8px;
            min-height: 100px;
            max-height: 200px;
            overflow-y: auto;
        }

        .selected-seat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .remove-seat-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .price-breakdown {
            margin: 20px 0;
            padding: 15px;
            background: var(--gray-100);
            border-radius: 8px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .price-row.total {
            border-top: 2px solid var(--gray-300);
            margin-top: 10px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }

        .proceed-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .proceed-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }

        .proceed-btn:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
        }

        .empty-message {
            text-align: center;
            color: var(--gray-600);
            font-size: 14px;
            padding: 20px;
        }

        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .summary-panel {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .seat-row {
                gap: 8px;
            }

            .seat {
                width: 50px;
                height: 60px;
            }

            .row-label {
                min-width: 50px;
                font-size: 11px;
                padding: 8px;
            }

            .aisle-space {
                width: 30px;
            }
        }

        @media (max-width: 480px) {
            .seat {
                width: 45px;
                height: 55px;
                font-size: 10px;
            }

            .row-label {
                min-width: 40px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="javascript:history.back()" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Train Details
    </a>

    <div class="header">
        <h1><i class="fas fa-ticket-alt"></i> Select Your Seats</h1>
        <div class="journey-info">
            <div class="info-box">
                <span class="info-label">Train</span>
                <span class="info-value"><?php echo htmlspecialchars($train_details['train_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Coach</span>
                <span class="info-value"><?php echo htmlspecialchars($coach_details['coach_type'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($coach_details['coach_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Journey</span>
                <span class="info-value"><?php echo htmlspecialchars($from_station_name); ?> → <?php echo htmlspecialchars($to_station_name); ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Travel Date</span>
                <span class="info-value"><?php echo date('M d, Y', strtotime($travel_date)); ?></span>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="seat-map-section">
            <h2 class="section-title">
                <i class="fas fa-chair"></i> Seat Map
            </h2>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-box available"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box selected"></div>
                    <span>Selected</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box booked"></div>
                    <span>Booked</span>
                </div>
                <div class="legend-item">
                    <div style="width: 35px; height: 35px; background: linear-gradient(145deg, #3b82f6 0%, #2563eb 100%); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: bold;">W</div>
                    <span>Window Seat</span>
                </div>
            </div>

            <div class="coach-layout">
                <div class="driver-section">
                    <i class="fas fa-steering-wheel"></i>
                    <div>Driver</div>
                </div>

                <div class="seats-container">
                    <?php 
                    $seats_per_row = 5; // 2 seats + aisle + 2 seats
                    $total_seats = count($seats);
                    $rows = ceil($total_seats / 4); // 4 seats each row
                    
                    for ($row = 0; $row < $rows; $row++):
                        $start_idx = $row * 4;
                        $row_seats = array_slice($seats, $start_idx, 4);
                        if (empty($row_seats)) continue;
                    ?>
                        <div class="seat-row">
                            <div class="row-label">Row <?php echo ($row + 1); ?></div>

                            <?php 
                            // Left side seats
                            for ($i = 0; $i < 2 && isset($row_seats[$i]); $i++): 
                                $seat = $row_seats[$i];
                                $is_booked = in_array($seat['seat_id'], $booked_seats);
                                $seat_type = getSeatType($i);
                                $seat_class = $is_booked ? 'booked ' . $seat_type : $seat_type;
                            ?>
                                <div class="seat <?php echo $seat_class; ?>" 
                                     data-seat-id="<?php echo htmlspecialchars($seat['seat_id']); ?>"
                                     data-seat-number="<?php echo htmlspecialchars($seat['seat_number']); ?>"
                                     data-seat-type="<?php echo $seat_type; ?>"
                                     <?php echo $is_booked ? '' : 'onclick="toggleSeat(this)"'; ?>>
                                    <div><?php echo htmlspecialchars($seat['seat_number']); ?></div>
                                </div>
                            <?php endfor; ?>

                            <div class="aisle-space"></div>

                            <?php 
                            // Right side seats
                            for ($i = 2; $i < 4 && isset($row_seats[$i]); $i++): 
                                $seat = $row_seats[$i];
                                $is_booked = in_array($seat['seat_id'], $booked_seats);
                                $seat_type = getSeatType($i);
                                $seat_class = $is_booked ? 'booked ' . $seat_type : $seat_type;
                            ?>
                                <div class="seat <?php echo $seat_class; ?>" 
                                     data-seat-id="<?php echo htmlspecialchars($seat['seat_id']); ?>"
                                     data-seat-number="<?php echo htmlspecialchars($seat['seat_number']); ?>"
                                     data-seat-type="<?php echo $seat_type; ?>"
                                     <?php echo $is_booked ? '' : 'onclick="toggleSeat(this)"'; ?>>
                                    <div><?php echo htmlspecialchars($seat['seat_number']); ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="summary-panel">
            <h2 class="section-title">
                <i class="fas fa-clipboard-list"></i> Booking Summary
            </h2>

            <div class="selected-seats-list" id="selectedSeatsList">
                <div class="empty-message">
                    <i class="fas fa-hand-pointer"></i><br>
                    Click on seats to select
                </div>
            </div>

            <div class="price-breakdown">
                <div class="price-row">
                    <span>Price per seat:</span>
                    <span>৳<?php echo number_format($price, 2); ?></span>
                </div>
                <div class="price-row">
                    <span>Selected seats:</span>
                    <span id="seatCount">0</span>
                </div>
                <div class="price-row total">
                    <span>Total Amount:</span>
                    <span id="totalAmount">৳0.00</span>
                </div>
            </div>

            <form method="POST" action="../process/process_booking.php" id="bookingForm">
                <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($schedule_id); ?>">
                <input type="hidden" name="train_id" value="<?php echo htmlspecialchars($train_id); ?>">
                <input type="hidden" name="coach_id" value="<?php echo htmlspecialchars($coach_id); ?>">
                <input type="hidden" name="coach_type" value="<?php echo htmlspecialchars($coach_type); ?>">
                <input type="hidden" name="travel_date" value="<?php echo htmlspecialchars($travel_date); ?>">
                <input type="hidden" name="from_station" value="<?php echo htmlspecialchars($from_station); ?>">
                <input type="hidden" name="to_station" value="<?php echo htmlspecialchars($to_station); ?>">
                <input type="hidden" name="price_per_seat" value="<?php echo htmlspecialchars($price); ?>">
                <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">
                <input type="hidden" name="total_amount" id="totalAmountInput" value="0">

                <button type="submit" class="proceed-btn" id="proceedBtn" disabled>
                    <i class="fas fa-check-circle"></i> Proceed to Payment
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const selectedSeats = [];
const pricePerSeat = <?php echo $price; ?>;

function toggleSeat(seatElement) {
    if (seatElement.classList.contains('booked')) return;

    const seatId = seatElement.dataset.seatId;
    const seatNumber = seatElement.dataset.seatNumber;
    const seatType = seatElement.dataset.seatType;

    if (seatElement.classList.contains('selected')) {
        // Deselect
        seatElement.classList.remove('selected');
        const index = selectedSeats.findIndex(s => s.id === seatId);
        if (index > -1) {
            selectedSeats.splice(index, 1);
        }
    } else {
        // Select (limit to 4 seats)
        if (selectedSeats.length >= 4) {
            alert('You can select maximum 4 seats at a time');
            return;
        }
        seatElement.classList.add('selected');
        selectedSeats.push({ id: seatId, number: seatNumber, type: seatType });
    }

    updateSummary();
}

function removeSeat(seatId) {
    const index = selectedSeats.findIndex(s => s.id === seatId);
    if (index > -1) {
        selectedSeats.splice(index, 1);
    }

    // Deselect in seat map
    const seatElement = document.querySelector(`[data-seat-id="${seatId}"]`);
    if (seatElement) {
        seatElement.classList.remove('selected');
    }

    updateSummary();
}

function updateSummary() {
    const listContainer = document.getElementById('selectedSeatsList');
    const seatCountEl = document.getElementById('seatCount');
    const totalAmountEl = document.getElementById('totalAmount');
    const proceedBtn = document.getElementById('proceedBtn');
    const selectedSeatsInput = document.getElementById('selectedSeatsInput');
    const totalAmountInput = document.getElementById('totalAmountInput');

    if (selectedSeats.length === 0) {
        listContainer.innerHTML = '<div class="empty-message"><i class="fas fa-hand-pointer"></i><br>Click on seats to select</div>';
        proceedBtn.disabled = true;
    } else {
        let html = '';
        selectedSeats.forEach(seat => {
            html += `
                <div class="selected-seat-item">
                    <span>
                        <i class="fas fa-chair"></i> Seat ${seat.number}
                    </span>
                    <button type="button" class="remove-seat-btn" onclick="removeSeat('${seat.id}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        listContainer.innerHTML = html;
        proceedBtn.disabled = false;
    }

    const totalAmount = selectedSeats.length * pricePerSeat;
    seatCountEl.textContent = selectedSeats.length;
    totalAmountEl.textContent = '৳' + totalAmount.toFixed(2);

    // Update form inputs
    selectedSeatsInput.value = selectedSeats.map(s => s.id).join(',');
    totalAmountInput.value = totalAmount;
}

// Form validation
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    if (selectedSeats.length === 0) {
        e.preventDefault();
        alert('Please select at least one seat');
        return false;
    }
    
    // Debug: Log form data
    console.log('Form submitting with seats:', selectedSeats);
    console.log('Selected seats input value:', document.getElementById('selectedSeatsInput').value);
    console.log('Total amount:', document.getElementById('totalAmountInput').value);
    
    // Confirm submission
    if (!confirm('Proceed with booking ' + selectedSeats.length + ' seat(s) for ৳' + (selectedSeats.length * pricePerSeat).toFixed(2) + '?')) {
        e.preventDefault();
        return false;
    }
});
</script>

</body>
</html>
