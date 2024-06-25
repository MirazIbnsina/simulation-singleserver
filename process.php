<?php

// Function to process form input
function get_user_input() {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Retrieve and sanitize input
        $num_customers = isset($_POST["num_customers"]) ? (int)$_POST["num_customers"] : 0;
        $rv = isset($_POST["rv"]) ? array_map('intval', explode(" ", $_POST["rv"])) : [];
        $rv_st = isset($_POST["rv_st"]) ? array_map('intval', explode(" ", $_POST["rv_st"])) : [];
        $iat_intervals = isset($_POST["iat_intervals"]) ? array_map('intval', explode(" ", $_POST["iat_intervals"])) : [];
        $iat_probs = isset($_POST["iat_probs"]) ? array_map('floatval', explode(" ", $_POST["iat_probs"])) : [];
        $st_intervals = isset($_POST["st_intervals"]) ? array_map('intval', explode(" ", $_POST["st_intervals"])) : [];
        $st_probs = isset($_POST["st_probs"]) ? array_map('floatval', explode(" ", $_POST["st_probs"])) : [];

        return [$num_customers, $rv, $rv_st, $iat_intervals, $iat_probs, $st_intervals, $st_probs];
    }
    return [0, [], [], [], [], [], []]; // Default values if not POST
}

// Function to calculate simulation data
function calculate_simulation($num_customers, $rv, $rv_st, $iat_intervals, $iat_probs, $st_intervals, $st_probs) {
    // Create cumulative probabilities for IAT and Service Time
    $iat_cumulative_probs = [];
    $st_cumulative_probs = [];

    $cumulative = 0;
    foreach ($iat_probs as $prob) {
        $cumulative += $prob;
        $iat_cumulative_probs[] = $cumulative;
    }

    $cumulative = 0;
    foreach ($st_probs as $prob) {
        $cumulative += $prob;
        $st_cumulative_probs[] = $cumulative;
    }

    // Functions to find IAT and Service Time based on random variable
    function get_iat($random_variable, $iat_intervals, $iat_cumulative_probs) {
        foreach ($iat_cumulative_probs as $index => $cum_prob) {
            if ($random_variable <= $cum_prob * 1000) {
                return $iat_intervals[$index];
            }
        }
        return end($iat_intervals);
    }

    function get_service_time($random_variable, $st_intervals, $st_cumulative_probs) {
        foreach ($st_cumulative_probs as $index => $cum_prob) {
            if ($random_variable <= $cum_prob * 100) {
                return $st_intervals[$index];
            }
        }
        return end($st_intervals);
    }

    // Calculate simulation data
    $data = [];
    $arrival_time = 0;

    for ($i = 0; $i < $num_customers; $i++) {
        $customer_no = $i + 1;
        $iat = ($i == 0) ? 0 : get_iat($rv[$i], $iat_intervals, $iat_cumulative_probs);
        $arrival_time += $iat;
        $service_time = get_service_time($rv_st[$i], $st_intervals, $st_cumulative_probs);
        $time_service_begin = ($i == 0) ? $arrival_time : max($arrival_time, $data[$i - 1]["Time service End"]);
        $waiting_time = $time_service_begin - $arrival_time;
        $time_service_end = $time_service_begin + $service_time;
        $time_spent_in_system = $time_service_end - $arrival_time;
        $idle_time_of_server = ($i == 0) ? 0 : $time_service_begin - $data[$i - 1]["Time service End"];

        $data[] = [
            "Customer No." => $customer_no,
            "IAT" => $iat,
            "Arrival Time" => $arrival_time,
            "Service Time" => $service_time,
            "Time service Begin" => $time_service_begin,
            "Waiting Time" => $waiting_time,
            "Time service End" => $time_service_end,
            "Time spent in system" => $time_spent_in_system,
            "Idle time of server" => $idle_time_of_server
        ];
    }

    return $data;
}

// Main execution when form is submitted
list($num_customers, $rv, $rv_st, $iat_intervals, $iat_probs, $st_intervals, $st_probs) = get_user_input();
$simulation_data = calculate_simulation($num_customers, $rv, $rv_st, $iat_intervals, $iat_probs, $st_intervals, $st_probs);

// Calculate totals for the last row of the table and metrics
$total_customers = $total_IAT = $total_service_time = $total_waiting_time = $total_time_spent_system = $total_idle_time = 0;
$num_waiters = 0;

foreach ($simulation_data as $row) {
    $total_customers++;
    $total_IAT += $row["IAT"];
    $total_service_time += $row["Service Time"];
    $total_waiting_time += $row["Waiting Time"];
    $total_time_spent_system += $row["Time spent in system"];
    $total_idle_time += $row["Idle time of server"];

    if ($row["Waiting Time"] > 0) {
        $num_waiters++;
    }
}

// Calculate metrics
$average_waiting_time = round($total_waiting_time / $total_customers, 2);
$probability_wait = round(($num_waiters / $total_customers) * 100, 2);
$probability_idle = round(($total_idle_time / end($simulation_data)["Time service End"]) * 100, 2);
$average_service_time = round($total_service_time / $total_customers, 2);
$average_time_between_arrival = round($total_IAT / ($total_customers - 1), 2);
$average_waiting_time_waiters = $num_waiters > 0 ? round($total_waiting_time / $num_waiters, 2) : 0;
$average_time_in_system = round($total_time_spent_system / $total_customers, 2);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulation Table</title>
    <style>
        /* Embedded CSS for styling */
        /* Reset default margin and padding */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0; /* Light gray background */
            margin: 0;
        }

        .container {
            max-width: 1000px; /* Adjust as needed */
            margin: 20px auto;
            background-color: #fff; /* White background for container */
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Subtle shadow */
            padding: 20px;
            overflow-x: auto; /* Enable horizontal scrolling */
        }

        .header {
            text-align: center;
            margin: 15px auto;
        }

        .details {
            overflow-x: auto; /* Enable horizontal scrolling for the table */
        }

        table {
            width: 100%; /* Full width table */
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table, th, td {
            border: 1px solid #ccc;
        }

        th, td {
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f2f2f2; /* Light gray background for header cells */
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9; /* Alternate row background */
        }

        tbody tr:hover {
            background-color: #e0e0e0; /* Hover effect for rows */
        }

        .totals {
            margin-top: 20px;
            margin-bottom: 32px;
        }

        .totals table {
            margin: 0 auto;
            border-collapse: collapse;
            text-align: left;
            width: 70%;
        }

        .totals th, .totals td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .totals th {
            background-color: #f2f2f2;
        }

        .metrics {
            margin: 10px;
        }

        .metrics hr {
            border: none;
            height: 1px; /* Height of the horizontal rule */
            background-color: #ccc; /* Light gray color */
            margin: 10px 0; /* Adjust margin */
            max-width: 30%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Simulation Table</h1>
        </div>
        <div class="details">
            <?php if (!empty($simulation_data)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer No.</th>
                                <th>IAT</th>
                                <th>Arrival Time</th>
                                <th>Service Time</th>
                                <th>Time service Begin</th>
                                <th>Waiting Time</th>
                                <th>Time service End</th>
                                <th>Time spent in system</th>
                                <th>Idle time of server</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($simulation_data as $row): ?>
                                <tr>
                                    <td><?php echo $row["Customer No."]; ?></td>
                                    <td><?php echo $row["IAT"]; ?></td>
                                    <td><?php echo $row["Arrival Time"]; ?></td>
                                    <td><?php echo $row["Service Time"]; ?></td>
                                    <td><?php echo $row["Time service Begin"]; ?></td>
                                    <td><?php echo $row["Waiting Time"]; ?></td>
                                    <td><?php echo $row["Time service End"]; ?></td>
                                    <td><?php echo $row["Time spent in system"]; ?></td>
                                    <td><?php echo $row["Idle time of server"]; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals section -->
                <div class="totals">
                    <table>
                        <thead>
                            <tr>
                                <th>Total Customers</th>
                                <th>Total IAT</th>
                                <th>Total Service Time</th>
                                <th>Total Waiting Time</th>
                                <th>Total Time spent in system</th>
                                <th>Total Idle time of server</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo $total_customers; ?></td>
                                <td><?php echo number_format($total_IAT, 2); ?></td>
                                <td><?php echo number_format($total_service_time, 2); ?></td>
                                <td><?php echo number_format($total_waiting_time, 2); ?></td>
                                <td><?php echo number_format($total_time_spent_system, 2); ?></td>
                                <td><?php echo number_format($total_idle_time, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Metrics section -->
                <div class="metrics">
                    <h3>Summary</h3><hr>
                    <ul>
                        <li>1) Average waiting time for a customer: <?php echo $average_waiting_time; ?></li>
                        <li>2) Probability that a customer has to wait in the queue: <?php echo $probability_wait; ?>%</li>
                        <li>3) Fraction of idle time of the server: <?php echo $probability_idle; ?>%</li>
                        <li>4) Average service time: <?php echo $average_service_time; ?></li>
                        <li>5) Average time between arrivals: <?php echo $average_time_between_arrival; ?></li>
                        <li>6) Average waiting time of those who wait: <?php echo $average_waiting_time_waiters; ?></li>
                        <li>7) Average time a customer spends in the system: <?php echo $average_time_in_system; ?></li>
                    </ul>
                </div>

            <?php else: ?>
                <p>No simulation data available.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
