<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
h1 { font-size: 18px; margin-bottom: 4px; }
p { color: #666; margin: 0 0 16px; font-size: 11px; }
table { width: 100%; border-collapse: collapse; }
th { background: #1a1a2e; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
td { padding: 7px 10px; border-bottom: 1px solid #eee; font-size: 11px; }
tr:nth-child(even) td { background: #f9f9f9; }
.badge { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
.badge-done { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }
</style>
</head>
<body>
<h1>Interview Report</h1>
<p>Generated on {{ now()->format('d M Y, h:i A') }}</p>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Interview</th>
      <th>Email</th>
      <th>Sent On</th>
      <th>Status</th>
      <th>Completed On</th>
      <th>Answered</th>
      <th>Score</th>
    </tr>
  </thead>
  <tbody>
    @foreach($data as $i => $row)
    <tr>
      <td>{{ $i + 1 }}</td>
      <td>{{ $row['interview_name'] }}</td>
      <td>{{ $row['email'] }}</td>
      <td>{{ $row['sent_on'] }}</td>
      <td>
        <span class="badge {{ $row['status'] === 'Completed' ? 'badge-done' : 'badge-pending' }}">
          {{ $row['status'] }}
        </span>
      </td>
      <td>{{ $row['completed_on'] }}</td>
      <td>{{ $row['answered'] }}</td>
      <td>{{ $row['pct'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
</body>
</html>