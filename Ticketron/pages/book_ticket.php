<?php
// book_ticket.php - MINIMAL COACH SELECTION PAGE
session_start();
include '../db.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error_message = "";
$train_details = [];
$coaches = [];
$train_id = "";
$travel_date = "";
$schedule_id = "";
$from_station = "";
$to_station = "";
$is_virtual = false;

// Get booking details from POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['train_id'])) {
    $train_id = mysqli_real_escape_string($conn, $_POST['train_id']);
    $from_station = mysqli_real_escape_string($conn, $_POST['from_station']);
    $to_station = mysqli_real_escape_string($conn, $_POST['to_station']);
    $travel_date = mysqli_real_escape_string($conn, $_POST['travel_date']);
    $schedule_id = isset($_POST['schedule_id']) ? mysqli_real_escape_string($conn, $_POST['schedule_id']) : '';
    
    // Validate travel date
    $today = date('Y-m-d');
    if ($travel_date < $today) {
        $error_message = "Travel date cannot be in the past.";
        header("Location: dashboard.php?error=" . urlencode($error_message));
        exit();
    }
    
    // Check if this is a virtual schedule
    $is_virtual = (strpos($schedule_id, 'VIRTUAL_') === 0);
    
    // Get basic train info
    $train_sql = "SELECT train_id, name as train_name, type as train_type, status FROM train WHERE train_id = '$train_id'";
    $train_result = $conn->query($train_sql);
    
    if ($train_result && $train_result->num_rows > 0) {
        $train_details = $train_result->fetch_assoc();
        
        // Fetch available coaches
        if (!$is_virtual) {
            $coach_query = "
                SELECT 
                    c.coach_id,
                    c.name as coach_name,
                    c.coach_type,
                    c.seat_count,
                    tp.price as fare_per_seat,
                    (c.seat_count - IFNULL((
                        SELECT COUNT(*)
                        FROM ticket tk
                        WHERE tk.schedule_id = '$schedule_id'
                        AND tk.seat_id LIKE CONCAT(c.coach_id, '%')
                        AND tk.booking_status != 'Cancelled'
                    ), 0)) as available_seats
                FROM coach c
                JOIN ticket_price tp ON c.coach_type = tp.coach_type
                WHERE c.train_id = '$train_id'
                ORDER BY 
                    CASE c.coach_type 
                        WHEN 'Snigdha' THEN 1 
                        WHEN 'Shovan Chair' THEN 2 
                        ELSE 3 
                    END,
                    c.name
            ";
        } else {
            $coach_query = "
                SELECT 
                    c.coach_id,
                    c.name as coach_name,
                    c.coach_type,
                    c.seat_count,
                    tp.price as fare_per_seat,
                    c.seat_count as available_seats
                FROM coach c
                JOIN ticket_price tp ON c.coach_type = tp.coach_type
                WHERE c.train_id = '$train_id'
                ORDER BY 
                    CASE c.coach_type 
                        WHEN 'Snigdha' THEN 1 
                        WHEN 'Shovan Chair' THEN 2 
                        ELSE 3 
                    END,
                    c.name
            ";
        }
        
        $coaches_result = $conn->query($coach_query);
        if ($coaches_result) {
            while ($coach = $coaches_result->fetch_assoc()) {
                $coaches[] = $coach;
            }
        }
        
    } else {
        $error_message = "Train not found.";
        header("Location: dashboard.php?error=" . urlencode($error_message));
        exit();
    }
    
} else {
    // Redirect to dashboard if no train selected
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Coach | Ticketron</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #7c3aed;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .train-info {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .train-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .train-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .train-detail {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .coach-selection {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .coaches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .coach-card {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 20px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .coach-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .coach-card.selected {
            border-color: var(--primary-color);
            background: rgba(79, 70, 229, 0.05);
        }

        .coach-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .coach-type {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .coach-name {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .coach-info {
            margin-bottom: 15px;
        }

        .coach-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .info-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .seat-availability {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .seat-available {
            background: #d1fae5;
            color: #065f46;
        }

        .seat-limited {
            background: #fef3c7;
            color: #92400e;
        }

        .seat-full {
            background: #fee2e2;
            color: #991b1b;
        }

        .coach-fare {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-color);
            text-align: center;
            margin: 15px 0;
        }

        .select-coach-btn {
            width: 100%;
            padding: 12px;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .select-coach-btn:hover {
            background: #0da271;
        }

        .select-coach-btn.unavailable {
            background: var(--text-secondary);
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-bg);
        }

        .btn-back {
            background: var(--light-bg);
            color: var(--text-primary);
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-back:hover {
            background: #e2e8f0;
        }

        .btn-proceed {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .btn-proceed:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-proceed:disabled {
            background: var(--light-bg);
            color: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .coaches-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .train-details {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="header">
            <h1>Select Your Coach</h1>
            <p>Choose a coach type to proceed to seat selection</p>
        </div>

        <!-- Train Information -->
        <div class="train-info">
            <div class="train-name"><?php echo htmlspecialchars($train_details['train_name']); ?></div>
            <div class="train-details">
                <div class="train-detail">
                    <strong>Train No:</strong> <?php echo htmlspecialchars($train_details['train_id']); ?>
                </div>
                <div class="train-detail">
                    <strong>Type:</strong> <?php echo htmlspecialchars($train_details['train_type']); ?>
                </div>
                <div class="train-detail">
                    <strong>Travel Date:</strong> <?php echo date('F j, Y', strtotime($travel_date)); ?>
                </div>
                <div class="train-detail">
                    <strong>From:</strong> Station <?php echo htmlspecialchars($from_station); ?>
                </div>
                <div class="train-detail">
                    <strong>To:</strong> Station <?php echo htmlspecialchars($to_station); ?>
                </div>
            </div>
        </div>

        <!-- Coach Selection -->
        <div class="coach-selection">
            <h2 class="section-title">
                <i class="fas fa-chair"></i>
                Available Coaches
            </h2>
            
            <?php if (empty($coaches)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    <i class="fas fa-train" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <h3>No Coaches Available</h3>
                    <p>There are no coaches available for this train.</p>
                </div>
            <?php else: ?>
                <div class="coaches-grid">
                    <?php foreach ($coaches as $coach): 
                        // Determine seat availability
                        $seat_percentage = ($coach['available_seats'] / $coach['seat_count']) * 100;
                        if ($seat_percentage > 50) {
                            $seat_class = 'seat-available';
                            $seat_text = $coach['available_seats'] . ' seats available';
                            $available = true;
                        } elseif ($seat_percentage > 10) {
                            $seat_class = 'seat-limited';
                            $seat_text = $coach['available_seats'] . ' seats left';
                            $available = true;
                        } elseif ($coach['available_seats'] > 0) {
                            $seat_class = 'seat-limited';
                            $seat_text = 'Few seats left';
                            $available = true;
                        } else {
                            $seat_class = 'seat-full';
                            $seat_text = 'Sold out';
                            $available = false;
                        }
                        
                        if ($is_virtual) {
                            $seat_class = 'seat-available';
                            $seat_text = $coach['available_seats'] . ' seats (estimated)';
                            $available = true;
                        }
                    ?>
                        <div class="coach-card" 
                             data-coach-id="<?php echo $coach['coach_id']; ?>"
                             data-coach-type="<?php echo $coach['coach_type']; ?>"
                             onclick="selectCoach(this, <?php echo $available ? 'true' : 'false'; ?>)">
                            <div class="coach-header">
                                <div class="coach-type"><?php echo htmlspecialchars($coach['coach_type']); ?></div>
                                <div class="coach-name"><?php echo htmlspecialchars($coach['coach_name']); ?></div>
                            </div>
                            
                            <div class="coach-info">
                                <div class="coach-info-row">
                                    <span class="info-label">Coach ID:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($coach['coach_id']); ?></span>
                                </div>
                                <div class="coach-info-row">
                                    <span class="info-label">Total Seats:</span>
                                    <span class="info-value"><?php echo $coach['seat_count']; ?></span>
                                </div>
                                <div class="coach-info-row">
                                    <span class="info-label">Available:</span>
                                    <span class="seat-availability <?php echo $seat_class; ?>">
                                        <i class="fas fa-chair"></i>
                                        <?php echo $seat_text; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="coach-fare">
                                ৳ <?php echo number_format($coach['fare_per_seat'], 2); ?>
                                <small>per seat</small>
                            </div>
                            
                            <button class="select-coach-btn <?php echo !$available ? 'unavailable' : ''; ?>" 
                                    onclick="event.stopPropagation(); selectCoach(this.parentElement, <?php echo $available ? 'true' : 'false'; ?>);"
                                    <?php echo !$available ? 'disabled' : ''; ?>>
                                <i class="fas fa-check-circle"></i>
                                <?php echo $available ? 'Select This Coach' : 'Sold Out'; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
                
                <!-- Hidden form for coach selection -->
                <form method="post" action="seat_selection.php" id="coachForm" style="display: none;">
                    <input type="hidden" name="coach_id" id="formCoachId" value="">
                    <input type="hidden" name="coach_type" id="formCoachType" value="">
                    <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($schedule_id); ?>">
                    <input type="hidden" name="train_id" value="<?php echo htmlspecialchars($train_id); ?>">
                    <input type="hidden" name="from_station" value="<?php echo htmlspecialchars($from_station); ?>">
                    <input type="hidden" name="to_station" value="<?php echo htmlspecialchars($to_station); ?>">
                    <input type="hidden" name="travel_date" value="<?php echo htmlspecialchars($travel_date); ?>">
                </form>
                
                <button class="btn-proceed" id="proceedBtn" disabled onclick="proceedToSeats()">
                    <i class="fas fa-arrow-right"></i>
                    Proceed to Seat Selection
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedCoach = null;
        
        function selectCoach(coachCard, isAvailable) {
            if (!isAvailable) return;

            // Reset all cards
            document.querySelectorAll('.coach-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Select current card
            coachCard.classList.add('selected');

            // Store selected coach
            selectedCoach = {
                coachId: coachCard.dataset.coachId,
                coachType: coachCard.dataset.coachType
            };

            // Enable proceed button
            document.getElementById('proceedBtn').disabled = false;
        }
        
        function proceedToSeats() {
            if (!selectedCoach) {
                alert('Please select a coach first');
                return;
            }
            
            // Fill form and submit
            document.getElementById('formCoachId').value = selectedCoach.coachId;
            document.getElementById('formCoachType').value = selectedCoach.coachType;
            document.getElementById('coachForm').submit();
        }
        
        // Auto-select first available coach for quick booking
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const quickBook = urlParams.get('quick_book');
            
            if (quickBook === 'true') {
                setTimeout(() => {
                    const firstAvailableCoach = document.querySelector('.coach-card .select-coach-btn:not(.unavailable)');
                    if (firstAvailableCoach) {
                        const coachCard = firstAvailableCoach.closest('.coach-card');
                        selectCoach(coachCard, true);
                        alert("Quick Booking: First available coach auto-selected.");
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>