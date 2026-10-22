<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Commission Earned</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .commission-details {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            text-align: center;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Congratulations!</h1>
        <p>You've earned a new commission!</p>
    </div>

    <div class="commission-details">
        <h2>Commission Details</h2>
        <p><strong>Commission Amount:</strong></p>
        <div class="amount">${{ number_format($commission->commission_amount, 2) }}</div>
        
        <p><strong>Order ID:</strong> #{{ $commission->order_id }}</p>
        <p><strong>Commission Rate:</strong> {{ $commission->commission_rate * 100 }}%</p>
        <p><strong>Date Earned:</strong> {{ $commission->created_at->format('F j, Y g:i A') }}</p>
        
        @if($commission->coupon_code)
            <p><strong>Coupon Used:</strong> {{ $commission->coupon_code }}</p>
        @endif
    </div>

    <div class="footer">
        <p>Thank you for being a valued affiliate partner!</p>
        <p>If you have any questions, please contact our support team.</p>
    </div>
</body>
</html>
