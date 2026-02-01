<?php
session_start();
include '../db.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$search_results = [];
$search_error = "";
$search_performed = false;
$travel_date = "";
$day_of_week = "";
$from_station = "";
$to_station = "";

// Process train search form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_trains'])) {
    $from_station = $_POST['from_station'];
    $to_station = $_POST['to_station'];
    $travel_date = $_POST['travel_date'];
    
    if (!empty($from_station) && !empty($to_station) && !empty($travel_date)) {
        $search_performed = true;
        
        // Check if stations are different
        if ($from_station == $to_station) {
            $search_error = "Departure and arrival stations cannot be the same.";
        } else {
            // Get the day of week from travel date
            $day_of_week = date('l', strtotime($travel_date));
            
            // Get station names for display
            $from_station_name = "";
            $to_station_name = "";
            
            // Get from station - using prepared statement
            $stmt = $conn->prepare("SELECT name, city FROM stations WHERE Station_id = ? LIMIT 1");
            $stmt->bind_param("s", $from_station);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $station_data = $result->fetch_assoc();
                $from_station_name = $station_data['name'];
                $from_city = $station_data['city'];
            }
            $stmt->close();
            
            // Get to station - using prepared statement
            $stmt = $conn->prepare("SELECT name, city FROM stations WHERE Station_id = ? LIMIT 1");
            $stmt->bind_param("s", $to_station);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $station_data = $result->fetch_assoc();
                $to_station_name = $station_data['name'];
                $to_city = $station_data['city'];
            }
            $stmt->close();
            
            // Query to find trains between stations - using prepared statement
            $sql = "
                SELECT DISTINCT
                    t.train_id,
                    t.name as train_name,
                    t.type as train_type,
                    t.status,
                    t.route_id,
                    r.route_name,
                    rs_from.station_order as from_order,
                    rs_to.station_order as to_order,
                    rs_from.start_to_distance as from_distance,
                    rs_to.start_to_distance as to_distance
                FROM train t
                INNER JOIN route r ON t.route_id = r.route_id
                INNER JOIN route_station rs_from ON r.route_id = rs_from.route_id
                INNER JOIN route_station rs_to ON r.route_id = rs_to.route_id
                WHERE rs_from.station_id = ?
                AND rs_to.station_id = ?
                AND rs_from.station_order < rs_to.station_order
                AND t.status = 'Active'
                ORDER BY t.train_id
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $from_station, $to_station);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Now check if this train runs on the selected weekday
                    $train_id = $row['train_id'];
                    
                    // Check train running day by weekday (not date)
                    $day_check_sql = "
                        SELECT * FROM train_running_day 
                        WHERE train_id = ? 
                        AND weekday = ?
                    ";
                    
                    $day_stmt = $conn->prepare($day_check_sql);
                    $day_stmt->bind_param("ss", $train_id, $day_of_week);
                    $day_stmt->execute();
                    $day_result = $day_stmt->get_result();
                    $day_stmt->close();
                    $runs_today = ($day_result && $day_result->num_rows > 0);
                    
                    // Skip trains that don't run on this weekday
                    if (!$runs_today) {
                        continue;
                    }
                    
                    // ============ SCHEDULE HANDLING (ANY SCHEDULE FOR THIS TRAIN) ============
                    // Get any schedule for this train (ignore date - trains run by weekday)
                    $schedule_sql = "
                        SELECT schedule_id, route_id, start_time 
                        FROM schedule 
                        WHERE train_id = ? 
                        LIMIT 1
                    ";
                    
                    $schedule_stmt = $conn->prepare($schedule_sql);
                    $schedule_stmt->bind_param("s", $train_id);
                    $schedule_stmt->execute();
                    $schedule_result = $schedule_stmt->get_result();
                    
                    if ($schedule_result && $schedule_result->num_rows > 0) {
                        // Use existing schedule
                        $schedule = $schedule_result->fetch_assoc();
                        $row['schedule_id'] = $schedule['schedule_id'];
                        $row['start_time'] = $schedule['start_time'];
                        $row['has_schedule'] = true;
                    } else {
                        // No schedule exists - skip this train
                        $schedule_stmt->close();
                        continue;
                    }
                    $schedule_stmt->close();
                    // ============ END SCHEDULE HANDLING ============
                    
                    // Add station information
                    $row['from_station_name'] = $from_station_name;
                    $row['from_city'] = $from_city;
                    $row['to_station_name'] = $to_station_name;
                    $row['to_city'] = $to_city;
                    
                    // Calculate distance and travel time
                    $distance = $row['to_distance'] - $row['from_distance'];
                    
                    // Calculate estimated travel time
                    $avg_speed = ($row['train_type'] == 'Intercity') ? 60 : 50;
                    $travel_hours = $distance / $avg_speed;
                    
                    $start_time = strtotime($row['start_time']);
                    $estimated_arrival = date('H:i:s', strtotime("+{$travel_hours} hours", $start_time));
                    $row['estimated_arrival'] = $estimated_arrival;
                    $row['distance_km'] = round($distance, 2);
                    $row['travel_hours'] = round($travel_hours, 1);
                    
                    // Calculate fare using database prices
                    $row['ac_fare'] = isset($ticket_prices['Snigdha']) ? $ticket_prices['Snigdha'] : 850.00;
                    $row['non_ac_fare'] = isset($ticket_prices['Shovan Chair']) ? $ticket_prices['Shovan Chair'] : 350.00;
                    
                    // Get total seats for this train - using prepared statement
                    $seat_sql = "
                        SELECT COUNT(*) as total_seats
                        FROM seat se
                        INNER JOIN coach c ON se.coach_id = c.coach_id
                        WHERE c.train_id = ?
                    ";
                    $seat_stmt = $conn->prepare($seat_sql);
                    $seat_stmt->bind_param("s", $train_id);
                    $seat_stmt->execute();
                    $seat_result = $seat_stmt->get_result();
                    if ($seat_result) {
                        $seat_data = $seat_result->fetch_assoc();
                        $row['total_seats'] = $seat_data['total_seats'] ?? 80;
                    } else {
                        $row['total_seats'] = 80;
                    }
                    $seat_stmt->close();
                    
                    // Get available seats for this specific date
                    if (isset($row['schedule_id']) && strpos($row['schedule_id'], 'VIRTUAL_') === false) {
                        $available_seats_sql = "
                            SELECT COUNT(*) as available_seats
                            FROM seat se
                            INNER JOIN coach c ON se.coach_id = c.coach_id
                            WHERE c.train_id = ?
                            AND se.seat_id NOT IN (
                                SELECT seat_id FROM ticket 
                                WHERE schedule_id = ?
                                AND booking_status != 'Cancelled'
                            )
                        ";
                        $avail_stmt = $conn->prepare($available_seats_sql);
                        $avail_stmt->bind_param("ss", $train_id, $row['schedule_id']);
                        $avail_stmt->execute();
                        $available_result = $avail_stmt->get_result();
                        if ($available_result) {
                            $available_data = $available_result->fetch_assoc();
                            $row['available_seats'] = $available_data['available_seats'] ?? $row['total_seats'];
                        } else {
                            $row['available_seats'] = $row['total_seats'];
                        }
                        $avail_stmt->close();
                    } else {
                        $row['available_seats'] = $row['total_seats'];
                    }
                    
                    // Get coach types - using prepared statement
                    $coach_sql = "
                        SELECT DISTINCT coach_type
                        FROM coach
                        WHERE train_id = ?
                    ";
                    $coach_stmt = $conn->prepare($coach_sql);
                    $coach_stmt->bind_param("s", $train_id);
                    $coach_stmt->execute();
                    $coach_result = $coach_stmt->get_result();
                    $coach_types = [];
                    if ($coach_result) {
                        while ($coach = $coach_result->fetch_assoc()) {
                            $coach_types[] = $coach['coach_type'];
                        }
                    }
                    $coach_stmt->close();
                    $row['coach_types'] = !empty($coach_types) ? implode(', ', $coach_types) : 'AC, Non-AC';
                    
                    // Add day info
                    $row['runs_today'] = $runs_today;
                    $row['day_of_week'] = $day_of_week;
                    
                    $search_results[] = $row;
                }
            } else {
                $search_error = "No trains found between selected stations.";
            }
        }
    } else {
        $search_error = "Please fill in all search fields.";
    }
}

// Get all stations for dropdown - using prepared statement (but this is safe since it's not filtered)
$stations_sql = "SELECT Station_id, name, city FROM stations ORDER BY city, name";
$stations_result = $conn->query($stations_sql);
$stations = [];
if ($stations_result) {
    while ($row = $stations_result->fetch_assoc()) {
        $stations[] = $row;
    }
}

// Get ticket prices from database
$ticket_prices_sql = "SELECT coach_type, price FROM ticket_price ORDER BY coach_type";
$ticket_prices_result = $conn->query($ticket_prices_sql);
$ticket_prices = [];
if ($ticket_prices_result) {
    while ($row = $ticket_prices_result->fetch_assoc()) {
        $ticket_prices[$row['coach_type']] = $row['price'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Ticketron</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #0ea5e9;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --dark-bg: #0f172a;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --card-shadow-lg: 0 20px 25px rgba(0, 0, 0, 0.15);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --blue-50: #eff6ff;
            --blue-100: #dbeafe;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
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
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            background: #ffffff;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .logo i {
            background: rgba(255,255,255,0.2);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .logo:hover i {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        .user-profile {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
        }

        .avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            margin: 0 auto 15px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transition: all 0.3s ease;
        }

        .avatar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        }

        .user-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-primary);
        }

        .user-email {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 15px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            margin: 6px 0;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            background: transparent;
        }

        .nav-item:hover {
            background: var(--blue-50);
            color: var(--primary-color);
            transform: translateX(3px);
        }

        .nav-item.active {
            background: var(--blue-50);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 600;
            box-shadow: inset 0 2px 4px rgba(37, 99, 235, 0.1);
        }

        .nav-item i {
            width: 20px;
            font-size: 16px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: #f8fafc;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 15px;
            background: #fee2e2;
            color: #991b1b;
            border: none;
            border-radius: 8px;
            width: 100%;
            text-align: left;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
            text-decoration: none;
            font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .logout-btn:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
            text-decoration: none;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: #f0f4f8;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }

        .date-display {
            font-size: 14px;
            color: var(--text-secondary);
            background: var(--blue-50);
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            border: 1px solid var(--blue-100);
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-color);
            font-size: 24px;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: white;
            font-weight: 500;
        }

        .form-control:hover {
            border-color: var(--primary-color);
            background: var(--blue-50);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .btn-search {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 12px 26px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            grid-column: 1 / -1;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }

        .btn-search:active {
            transform: translateY(0);
        }

        /* Alert Messages */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-danger {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }

        .alert-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #92400e;
        }

        /* Day Info */
        .day-info {
            background: var(--blue-50);
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            text-align: center;
            font-size: 13px;
            color: var(--primary-color);
            font-weight: 600;
            border: 1px solid var(--blue-100);
        }

        /* Results Section */
        .results-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .train-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .train-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--card-shadow-lg);
            transform: translateY(-3px);
        }

        .train-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .train-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .day-badge {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .day-available {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .day-not-available {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .train-type {
            background: var(--blue-50);
            color: var(--primary-color);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            margin-right: 8px;
            border: 1px solid var(--blue-100);
        }

        .seat-availability {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }

        .seat-available {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .seat-limited {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .seat-full {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .train-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .detail-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
        }

        .detail-label {
            font-size: 10px;
            color: var(--text-secondary);
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .fare-info {
            background: var(--blue-50);
            padding: 16px;
            border-radius: 8px;
            margin: 16px 0;
            border: 1px solid var(--blue-100);
        }

        .fare-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--blue-100);
        }

        .fare-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }

        .fare-type {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 13px;
        }

        .fare-amount {
            font-weight: 800;
            color: var(--primary-color);
            font-size: 15px;
        }

        .train-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .btn-book, .btn-details {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: none;
            color: inherit;
        }

        .btn-book {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }

        .btn-details {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            text-decoration: none;
        }

        .btn-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }

        .btn-book:disabled, .btn-details:disabled {
            background: var(--border-color);
            color: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 50px 30px;
            color: var(--text-secondary);
        }

        .no-results i {
            font-size: 56px;
            margin-bottom: 16px;
            color: var(--primary-color);
            opacity: 0.5;
        }

        .no-results h3 {
            font-size: 22px;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 700;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header h1 {
                font-size: 24px;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .train-actions {
                flex-direction: column;
            }
            
            .btn-book, .btn-details {
                width: 100%;
                justify-content: center;
            }

            .search-section, .results-section {
                padding: 20px;
            }

            .train-details {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>


    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-train"></i>
                    <span>Ticketron</span>
                </div>
            </div>

            <div class="user-profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="my_bookings.php" class="nav-item">
                    <i class="fas fa-ticket-alt"></i>
                    <span>My Bookings</span>
                </a>
                <a href="ticket.php" class="nav-item">
                    <i class="fas fa-list"></i>
                    <span>All Tickets</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <button type="button" class="logout-btn" onclick="logout();">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
                <div class="date-display">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('l, F j, Y'); ?>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <h2 class="section-title">
                    <i class="fas fa-search"></i>
                    Search Trains
                </h2>
                
                <?php if ($search_error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $search_error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="search-form" id="searchForm">
                    <div class="form-group">
                        <label for="from_station">From Station</label>
                        <select name="from_station" id="from_station" class="form-control" required>
                            <option value="">Select departure station</option>
                            <?php foreach ($stations as $station): 
                                $isSelected = isset($_POST['from_station']) && $_POST['from_station'] == $station['Station_id'];
                            ?>
                                <option value="<?php echo $station['Station_id']; ?>" 
                                    <?php echo $isSelected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($station['name'] . ' - ' . $station['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="to_station">To Station</label>
                        <select name="to_station" id="to_station" class="form-control" required>
                            <option value="">Select destination station</option>
                            <?php foreach ($stations as $station): 
                                $isSelected = isset($_POST['to_station']) && $_POST['to_station'] == $station['Station_id'];
                            ?>
                                <option value="<?php echo $station['Station_id']; ?>" 
                                    <?php echo $isSelected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($station['name'] . ' - ' . $station['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="travel_date">Travel Date</label>
                        <input type="date" name="travel_date" id="travel_date" 
                               class="form-control" required 
                               value="<?php echo isset($_POST['travel_date']) ? $_POST['travel_date'] : date('Y-m-d'); ?>"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <button type="submit" name="search_trains" class="btn-search">
                        <i class="fas fa-search"></i>
                        Search Trains
                    </button>
                </form>
                
                <?php if ($search_performed && $day_of_week): ?>
                    <div class="day-info">
                        <i class="fas fa-calendar-check"></i>
                        Selected date: <strong><?php echo date('F j, Y', strtotime($travel_date)); ?></strong>
                        | Day: <strong><?php echo $day_of_week; ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Results Section -->
            <?php if ($search_performed): ?>
                <div class="results-section">
                    <h2 class="section-title">
                        <i class="fas fa-train"></i>
                        Search Results
                        <?php if (!empty($search_results)): ?>
                            <span style="font-size: 16px; color: var(--success-color); margin-left: 10px;">
                                (<?php echo count($search_results); ?> trains found)
                            </span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (empty($search_results)): ?>
                        <div class="no-results">
                            <i class="fas fa-train"></i>
                            <h3>No Trains Found</h3>
                            <p>No trains were found connecting the selected stations on <?php echo $day_of_week; ?>.</p>
                            <p><small>Try a different date or search between major cities.</small></p>
                        </div>
                    <?php else: ?>
                        
                        <?php foreach ($search_results as $train): 
                            // Determine seat availability status
                            $seat_percentage = ($train['available_seats'] / $train['total_seats']) * 100;
                            if ($seat_percentage > 50) {
                                $seat_class = 'seat-available';
                                $seat_text = $train['available_seats'] . ' seats available';
                            } elseif ($seat_percentage > 10) {
                                $seat_class = 'seat-limited';
                                $seat_text = $train['available_seats'] . ' seats left';
                            } else {
                                $seat_class = 'seat-full';
                                $seat_text = 'Few seats left';
                            }
                        ?>
                            <div class="train-card">
                                <div class="train-header">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="train-name"><?php echo htmlspecialchars($train['train_name']); ?></div>
                                        <span class="train-type"><?php echo htmlspecialchars($train['train_type']); ?></span>
                                        <span class="seat-availability <?php echo $seat_class; ?>">
                                            <i class="fas fa-chair"></i>
                                            <?php echo $seat_text; ?>
                                        </span>
                                    </div>
                                    <div class="day-badge <?php echo $train['runs_today'] ? 'day-available' : 'day-not-available'; ?>">
                                        <?php echo $train['runs_today'] ? '✓ Runs Today' : '✗ Not Available'; ?>
                                    </div>
                                </div>
                                
                                <div class="train-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Train No</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($train['train_id']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Route</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($train['route_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">From</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($train['from_station_name'] . ' (' . $train['from_city'] . ')'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">To</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($train['to_station_name'] . ' (' . $train['to_city'] . ')'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Departure</span>
                                        <span class="detail-value"><?php echo date('h:i A', strtotime($train['start_time'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Arrival (Est.)</span>
                                        <span class="detail-value"><?php echo date('h:i A', strtotime($train['estimated_arrival'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Distance</span>
                                        <span class="detail-value"><?php echo number_format($train['distance_km'], 0); ?> km</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Duration</span>
                                        <span class="detail-value">~<?php echo $train['travel_hours']; ?> hours</span>
                                    </div>
                                </div>
                                
                                <div class="fare-info">
                                    <div class="fare-row">
                                        <span class="fare-type">AC Coach Fare</span>
                                        <span class="fare-amount">৳ <?php echo number_format($train['ac_fare'], 2); ?></span>
                                    </div>
                                    <div class="fare-row">
                                        <span class="fare-type">Non-AC Coach Fare</span>
                                        <span class="fare-amount">৳ <?php echo number_format($train['non_ac_fare'], 2); ?></span>
                                    </div>
                                    <div class="fare-row">
                                        <span class="fare-type">Coach Types Available</span>
                                        <span class="fare-amount"><?php echo htmlspecialchars($train['coach_types']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="train-actions">
                                    <?php if ($train['runs_today']): ?>
                                        <!-- View Details Button -->
                                        <form method="GET" action="train_details.php" style="display: inline;">
                                            <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($train['schedule_id']); ?>">
                                            <input type="hidden" name="train_id" value="<?php echo htmlspecialchars($train['train_id']); ?>">
                                            <input type="hidden" name="travel_date" value="<?php echo htmlspecialchars($travel_date); ?>">
                                            <input type="hidden" name="from_station" value="<?php echo htmlspecialchars($from_station); ?>">
                                            <input type="hidden" name="to_station" value="<?php echo htmlspecialchars($to_station); ?>">
                                            <button type="submit" class="btn-details">
                                                <i class="fas fa-info-circle"></i>
                                                View Details
                                            </button>
                                        </form>
                                        
                                        <!-- Book Now Button -->
                                        <form method="post" action="book_ticket.php" style="display: inline;">
                                            <input type="hidden" name="train_id" value="<?php echo $train['train_id']; ?>">
                                            <input type="hidden" name="from_station" value="<?php echo $from_station; ?>">
                                            <input type="hidden" name="to_station" value="<?php echo $to_station; ?>">
                                            <input type="hidden" name="travel_date" value="<?php echo $travel_date; ?>">
                                            <input type="hidden" name="day_of_week" value="<?php echo $day_of_week; ?>">
                                            <input type="hidden" name="schedule_id" value="<?php echo $train['schedule_id']; ?>">
                                            <button type="submit" class="btn-book" <?php echo $train['available_seats'] == 0 ? 'disabled title="No seats available"' : ''; ?>>
                                                <i class="fas fa-ticket-alt"></i>
                                                <?php echo $train['available_seats'] > 0 ? 'Book Now' : 'Sold Out'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-book" disabled title="This train does not run on <?php echo $day_of_week; ?>">
                                            <i class="fas fa-calendar-times"></i>
                                            Not Available
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Set minimum date to today
        document.getElementById('travel_date').min = new Date().toISOString().split('T')[0];
        
        // Set maximum date to 60 days from today
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 60);
        document.getElementById('travel_date').max = maxDate.toISOString().split('T')[0];
        
        // Auto-swap stations button
        const fromStation = document.getElementById('from_station');
        const toStation = document.getElementById('to_station');
        
        // Add swap button
        const swapButton = document.createElement('button');
        swapButton.innerHTML = '<i class="fas fa-exchange-alt"></i>';
        swapButton.type = 'button';
        swapButton.title = 'Swap stations';
        swapButton.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        `;
        
        const fromContainer = fromStation.parentElement;
        fromContainer.style.position = 'relative';
        fromContainer.appendChild(swapButton);
        
        swapButton.addEventListener('click', function() {
            const fromValue = fromStation.value;
            const toValue = toStation.value;
            
            fromStation.value = toValue;
            toStation.value = fromValue;
        });
        
        // Show weekday for selected date
        function updateWeekdayDisplay() {
            const dateInput = document.getElementById('travel_date');
            const date = new Date(dateInput.value);
            
            if (dateInput.value) {
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const dayName = days[date.getDay()];
                
                let weekdayDisplay = document.getElementById('weekday-display');
                if (!weekdayDisplay) {
                    weekdayDisplay = document.createElement('div');
                    weekdayDisplay.id = 'weekday-display';
                    weekdayDisplay.style.cssText = `
                        margin-top: 10px;
                        font-size: 14px;
                        color: var(--text-secondary);
                    `;
                    dateInput.parentNode.appendChild(weekdayDisplay);
                }
                weekdayDisplay.innerHTML = `<i class="fas fa-calendar-alt"></i> ${dayName}`;
            }
        }
        
        document.getElementById('travel_date').addEventListener('change', updateWeekdayDisplay);
        
        // Trigger on page load if date is set
        const travelDateInput = document.getElementById('travel_date');
        if (travelDateInput.value) {
            updateWeekdayDisplay();
        }
        
        // Form validation
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            const fromValue = fromStation.value;
            const toValue = toStation.value;
            const travelDate = document.getElementById('travel_date').value;
            
            if (!fromValue || !toValue || !travelDate) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            if (fromValue === toValue) {
                e.preventDefault();
                alert('Departure and destination stations cannot be the same');
                return false;
            }
            
            // Check if date is not in the past
            const selectedDate = new Date(travelDate);
            const today = new Date();
            today.setHours(0,0,0,0);
            
            if (selectedDate < today) {
                e.preventDefault();
                alert('Please select a future date');
                return false;
            }
            
            return true;
        });
        
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                // Create a form and submit to logout.php
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'logout.php';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-focus on first field if no search performed
        <?php if (!$search_performed): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('from_station').focus();
            });
        <?php endif; ?>
    </script>
</body>
</html>