<?php
// Cron script: send WhatsApp reminders for Pending payments where due date is 3 days ahead
// Run daily at 00:00 from Hostinger cron

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'db.php';

try {
    // Find Pending payments whose expected_date (due date) is exactly 3 days after today
    $sql = "
        SELECT p.id, p.amount,
               COALESCE(p.expected_date, p.date) AS due_date, p.reason,
               c.phone, c.mobile, c.contact, c.name
        FROM payments p
        LEFT JOIN customers c ON c.id = p.customer_id
        WHERE p.type = 'Pending'
          AND DATEDIFF(COALESCE(p.expected_date, p.date), CURDATE()) = 3
          AND (c.phone IS NOT NULL OR c.mobile IS NOT NULL OR c.contact IS NOT NULL)
    ";

    $result = $conn->query($sql);
    if (!$result) {
        error_log('Cron SQL error: ' . $conn->error);
        exit;
    }

    while ($row = $result->fetch_assoc()) {
        $paymentId = (int)$row['id'];
        $amount    = $row['amount'];
        $dueDate   = $row['due_date'];
        $reason    = $row['reason'] ?: '-';
        $name      = $row['name'] ?: 'Customer';

        // Pick a phone number
        $phone = $row['phone'] ?: ($row['mobile'] ?: $row['contact']);
        if (!$phone) {
            continue;
        }

        // WhatsApp message in Marathi
        $message = "पेमेंट रिमाइंडर\n\n"
          . "प्रिय {$name},\n\n"
          . "आपले थकित पेमेंट:\n"
          . "रक्कम: ₹{$amount}\n"
          . "Due Date: {$dueDate}\n"
          . "Reason: {$reason}\n\n"
          . "कृपया due date आधी पेमेंट पूर्ण करा.\n\nधन्यवाद!";

        // Call existing send_whatsapp.php on live server
        // NOTE: adjust URL if your backend folder path is different
        $payload = json_encode([
            'mobile'  => $phone,
            'message' => $message,
        ]);

        // NOTE: update this URL if your backend path/domain is different on the server
        $whatsUrl = 'https://ingavalebusinesssolution.in/backend/send_whatsapp.php';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $whatsUrl,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            error_log('WhatsApp cron CURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        // Log to payment_reminders table if it exists
        $stmt = $conn->prepare("INSERT INTO payment_reminders (payment_id, method, message, status, created_at) VALUES (?, 'WhatsApp', ?, 'Sent', NOW())");
        if ($stmt) {
            $stmt->bind_param('is', $paymentId, $message);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->close();
} catch (Throwable $e) {
    error_log('Cron exception: ' . $e->getMessage());
}

// No visible output needed when run via cron
