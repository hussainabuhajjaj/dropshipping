@php
    $report = is_array($report ?? null) ? $report : [];
    $analysis = is_array($report['analysis'] ?? null) ? $report['analysis'] : [];
    $topQueues = is_array($report['top_queues'] ?? null) ? $report['top_queues'] : [];
    $topFailedJobs = is_array($report['top_failed_jobs'] ?? null) ? $report['top_failed_jobs'] : [];
    $recentFailures = is_array($report['recent_failures'] ?? null) ? $report['recent_failures'] : [];
@endphp

<p style="margin:0 0 12px 0;">
    <strong>Period:</strong> {{ $report['period_label'] ?? 'N/A' }}
</p>

<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin-bottom:14px; border:1px solid #e5e7eb;">
    <tr style="background:#f8fafc;">
        <th align="left">Metric</th>
        <th align="right">Value</th>
    </tr>
    <tr>
        <td>Total processed</td>
        <td align="right">{{ $report['total_processed'] ?? 0 }}</td>
    </tr>
    <tr>
        <td>Success</td>
        <td align="right">{{ $report['success_count'] ?? 0 }} ({{ $report['success_rate'] ?? 0 }}%)</td>
    </tr>
    <tr>
        <td>Failed</td>
        <td align="right">{{ $report['failed_count'] ?? 0 }} ({{ $report['failure_rate'] ?? 0 }}%)</td>
    </tr>
</table>

@if($analysis !== [])
    <p style="margin:0 0 8px 0;"><strong>Analysis</strong></p>
    <ul style="margin:0 0 14px 18px; padding:0;">
        @foreach($analysis as $line)
            <li style="margin-bottom:4px;">{{ $line }}</li>
        @endforeach
    </ul>
@endif

@if($topQueues !== [])
    <p style="margin:0 0 8px 0;"><strong>Top queues</strong></p>
    <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin-bottom:14px; border:1px solid #e5e7eb;">
        <tr style="background:#f8fafc;">
            <th align="left">Queue</th>
            <th align="right">Total</th>
            <th align="right">Success</th>
            <th align="right">Failed</th>
        </tr>
        @foreach($topQueues as $row)
            <tr>
                <td>{{ $row['queue'] ?? 'unknown' }}</td>
                <td align="right">{{ $row['total'] ?? 0 }}</td>
                <td align="right">{{ $row['success'] ?? 0 }}</td>
                <td align="right">{{ $row['failed'] ?? 0 }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if($topFailedJobs !== [])
    <p style="margin:0 0 8px 0;"><strong>Top failed jobs</strong></p>
    <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin-bottom:14px; border:1px solid #e5e7eb;">
        <tr style="background:#f8fafc;">
            <th align="left">Job</th>
            <th align="right">Failed</th>
            <th align="right">Success</th>
        </tr>
        @foreach($topFailedJobs as $row)
            <tr>
                <td style="word-break:break-word;">{{ $row['job'] ?? 'unknown' }}</td>
                <td align="right">{{ $row['failed'] ?? 0 }}</td>
                <td align="right">{{ $row['success'] ?? 0 }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if($recentFailures !== [])
    <p style="margin:0 0 8px 0;"><strong>Recent failures</strong></p>
    <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; border:1px solid #e5e7eb;">
        <tr style="background:#f8fafc;">
            <th align="left">Time (UTC)</th>
            <th align="left">Queue</th>
            <th align="left">Job</th>
            <th align="left">Error</th>
        </tr>
        @foreach($recentFailures as $row)
            <tr>
                <td>{{ $row['at'] ?? '' }}</td>
                <td>{{ $row['queue'] ?? '' }}</td>
                <td style="word-break:break-word;">{{ $row['job'] ?? '' }}</td>
                <td style="word-break:break-word;">{{ $row['error'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif

