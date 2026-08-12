#!/usr/bin/env python3
"""
SMS API Load Testing Script
Sends 5000 requests and generates detailed HTML performance report
"""

import asyncio
import aiohttp
import random
import time
from datetime import datetime
from collections import defaultdict
import json
import os

# Configuration
API_URL = "https://smsexpert.nedtechnology.co.in/api/smsg/sms.mes"

TOTAL_REQUESTS = 5000
CONCURRENT_REQUESTS = 5000

FIRST_NUMBER = "917094514970"
LAST_NUMBER = "919003096885"

# API Parameters
API_PARAMS = {
    "usr": "master",
    "pwd": "master",
    "from": "MYBRANDNAME",
    "type": "text",
    "route": "d",
    "txt": "Load Testing 5000 Request"
}

class LoadTestMetrics:
    def __init__(self):
        self.start_time = None
        self.end_time = None
        self.total_requests = 0
        self.successful_requests = 0
        self.failed_requests = 0
        self.response_times = []
        self.status_codes = defaultdict(int)
        self.errors = defaultdict(int)
        self.requests_per_second = []
        self.second_buckets = defaultdict(lambda: {"success": 0, "failed": 0, "total": 0})
        self.numbers_sent = []
        self.request_details = []

    def record_request(self, number, status_code, response_time, success, error=None, response_text=None):
        self.total_requests += 1
        self.response_times.append(response_time)
        self.status_codes[status_code] += 1
        self.numbers_sent.append(number)

        # Calculate which second this request belongs to
        elapsed = time.time() - self.start_time
        second_bucket = int(elapsed)
        self.second_buckets[second_bucket]["total"] += 1

        if success:
            self.successful_requests += 1
            self.second_buckets[second_bucket]["success"] += 1
        else:
            self.failed_requests += 1
            self.second_buckets[second_bucket]["failed"] += 1
            if error:
                self.errors[str(error)] += 1

        # Store request details (limited to avoid memory issues)
        if len(self.request_details) < 1000 or not success:
            self.request_details.append({
                "number": number,
                "status_code": status_code,
                "response_time": response_time,
                "success": success,
                "error": str(error) if error else None,
                "response": response_text[:200] if response_text else None,
                "timestamp": datetime.now().isoformat()
            })

def generate_random_indian_mobile():
    """Generate random Indian mobile number starting with 91"""
    # Indian mobile numbers start with 6, 7, 8, or 9 after country code
    first_digit = random.choice(['6', '7', '8', '9'])
    remaining_digits = ''.join([str(random.randint(0, 9)) for _ in range(9)])
    return f"91{first_digit}{remaining_digits}"

def generate_phone_numbers(total):
    """Generate list of phone numbers with first and last being specific numbers"""
    numbers = []
    numbers.append(FIRST_NUMBER)  # First number

    # Generate random numbers for middle
    for _ in range(total - 2):
        numbers.append(generate_random_indian_mobile())

    numbers.append(LAST_NUMBER)  # Last number
    return numbers

async def send_sms_request(session, number, metrics, semaphore):
    """Send a single SMS request"""
    async with semaphore:
        params = API_PARAMS.copy()
        params["to"] = number

        start_time = time.time()
        status_code = 0
        success = False
        error = None
        response_text = None

        try:
            async with session.get(API_URL, params=params, timeout=aiohttp.ClientTimeout(total=30)) as response:
                status_code = response.status
                response_text = await response.text()
                success = status_code == 200

        except aiohttp.ClientError as e:
            error = f"ClientError: {str(e)}"
        except asyncio.TimeoutError:
            error = "Timeout"
        except Exception as e:
            error = f"Error: {str(e)}"

        response_time = (time.time() - start_time) * 1000  # Convert to ms
        metrics.record_request(number, status_code, response_time, success, error, response_text)

        return success

async def run_load_test():
    """Main load test runner"""
    print("=" * 60)
    print("SMS API Load Testing Tool")
    print("=" * 60)
    print(f"\nConfiguration:")
    print(f"  API URL: {API_URL}")
    print(f"  Total Requests: {TOTAL_REQUESTS:,}")
    print(f"  Concurrent Requests: {CONCURRENT_REQUESTS}")
    print(f"  First Number: {FIRST_NUMBER}")
    print(f"  Last Number: {LAST_NUMBER}")
    print()

    # Generate phone numbers
    print("Generating phone numbers...")
    phone_numbers = generate_phone_numbers(TOTAL_REQUESTS)
    print(f"Generated {len(phone_numbers):,} phone numbers")

    # Initialize metrics
    metrics = LoadTestMetrics()

    # Create semaphore for concurrent request limiting
    semaphore = asyncio.Semaphore(CONCURRENT_REQUESTS)

    # Create session with connection pooling
    connector = aiohttp.TCPConnector(limit=CONCURRENT_REQUESTS, limit_per_host=CONCURRENT_REQUESTS)

    print(f"\nStarting load test at {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("-" * 60)

    metrics.start_time = time.time()

    async with aiohttp.ClientSession(connector=connector) as session:
        # Create all tasks
        tasks = []
        for number in phone_numbers:
            task = send_sms_request(session, number, metrics, semaphore)
            tasks.append(task)

        # Progress tracking
        completed = 0
        total = len(tasks)

        # Process in batches for progress reporting
        batch_size = 5000
        for i in range(0, total, batch_size):
            batch = tasks[i:i + batch_size]
            await asyncio.gather(*batch)
            completed += len(batch)

            # Progress report
            elapsed = time.time() - metrics.start_time
            rps = completed / elapsed if elapsed > 0 else 0
            success_rate = (metrics.successful_requests / completed * 100) if completed > 0 else 0

            print(f"Progress: {completed:,}/{total:,} ({completed/total*100:.1f}%) | "
                  f"RPS: {rps:.1f} | "
                  f"Success: {metrics.successful_requests:,} | "
                  f"Failed: {metrics.failed_requests:,} | "
                  f"Success Rate: {success_rate:.1f}%")

    metrics.end_time = time.time()

    print("-" * 60)
    print(f"Load test completed at {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

    return metrics

def generate_html_report(metrics):
    """Generate detailed HTML performance report"""

    total_time = metrics.end_time - metrics.start_time
    avg_rps = metrics.total_requests / total_time if total_time > 0 else 0

    # Calculate response time statistics
    if metrics.response_times:
        avg_response_time = sum(metrics.response_times) / len(metrics.response_times)
        min_response_time = min(metrics.response_times)
        max_response_time = max(metrics.response_times)
        sorted_times = sorted(metrics.response_times)
        p50 = sorted_times[int(len(sorted_times) * 0.5)]
        p90 = sorted_times[int(len(sorted_times) * 0.9)]
        p95 = sorted_times[int(len(sorted_times) * 0.95)]
        p99 = sorted_times[int(len(sorted_times) * 0.99)]
    else:
        avg_response_time = min_response_time = max_response_time = 0
        p50 = p90 = p95 = p99 = 0

    success_rate = (metrics.successful_requests / metrics.total_requests * 100) if metrics.total_requests > 0 else 0

    # Prepare RPS data for chart
    rps_data = []
    for second in sorted(metrics.second_buckets.keys()):
        bucket = metrics.second_buckets[second]
        rps_data.append({
            "second": second,
            "total": bucket["total"],
            "success": bucket["success"],
            "failed": bucket["failed"]
        })

    # Status code distribution
    status_codes_html = ""
    for code, count in sorted(metrics.status_codes.items()):
        percentage = (count / metrics.total_requests * 100) if metrics.total_requests > 0 else 0
        color = "#28a745" if code == 200 else "#dc3545"
        status_codes_html += f"""
        <tr>
            <td><span class="badge" style="background-color: {color}">{code}</span></td>
            <td>{count:,}</td>
            <td>{percentage:.2f}%</td>
        </tr>
        """

    # Error distribution
    errors_html = ""
    for error, count in sorted(metrics.errors.items(), key=lambda x: -x[1])[:20]:
        errors_html += f"""
        <tr>
            <td>{error[:100]}</td>
            <td>{count:,}</td>
        </tr>
        """

    # Failed requests details
    failed_requests_html = ""
    failed_count = 0
    for detail in metrics.request_details:
        if not detail["success"] and failed_count < 100:
            failed_requests_html += f"""
            <tr>
                <td>{detail["number"]}</td>
                <td>{detail["status_code"]}</td>
                <td>{detail["response_time"]:.2f} ms</td>
                <td>{detail["error"] or "N/A"}</td>
                <td>{detail["timestamp"]}</td>
            </tr>
            """
            failed_count += 1

    html_content = f"""
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS API Load Test Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }}
        body {{
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #eee;
            min-height: 100vh;
            padding: 20px;
        }}
        .container {{
            max-width: 1400px;
            margin: 0 auto;
        }}
        h1 {{
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(90deg, #00d4ff, #7b2cbf);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }}
        .subtitle {{
            text-align: center;
            color: #888;
            margin-bottom: 30px;
        }}
        .summary-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }}
        .summary-card {{
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }}
        .summary-card:hover {{
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }}
        .summary-card .value {{
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }}
        .summary-card .label {{
            color: #888;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }}
        .success {{ color: #28a745; }}
        .danger {{ color: #dc3545; }}
        .warning {{ color: #ffc107; }}
        .info {{ color: #17a2b8; }}
        .primary {{ color: #007bff; }}
        .purple {{ color: #7b2cbf; }}

        .section {{
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }}
        .section h2 {{
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            color: #00d4ff;
        }}

        .metrics-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }}
        .metric-item {{
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }}
        .metric-item .value {{
            font-size: 1.5rem;
            font-weight: bold;
            color: #00d4ff;
        }}
        .metric-item .label {{
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }}

        table {{
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }}
        th, td {{
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }}
        th {{
            background: rgba(0, 0, 0, 0.3);
            color: #00d4ff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }}
        tr:hover {{
            background: rgba(255, 255, 255, 0.05);
        }}

        .badge {{
            padding: 5px 12px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
            font-size: 0.85rem;
        }}

        .chart-container {{
            position: relative;
            height: 400px;
            margin-top: 20px;
        }}

        .progress-bar {{
            height: 30px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }}
        .progress-fill {{
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: width 0.5s;
        }}
        .progress-success {{
            background: linear-gradient(90deg, #28a745, #20c997);
        }}
        .progress-danger {{
            background: linear-gradient(90deg, #dc3545, #fd7e14);
        }}

        .two-column {{
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }}

        @media (max-width: 768px) {{
            .two-column {{
                grid-template-columns: 1fr;
            }}
            h1 {{
                font-size: 1.8rem;
            }}
            .summary-card .value {{
                font-size: 1.8rem;
            }}
        }}

        .config-list {{
            list-style: none;
            padding: 0;
        }}
        .config-list li {{
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.2);
            margin-bottom: 8px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
        }}
        .config-list li span:first-child {{
            color: #888;
        }}
        .config-list li span:last-child {{
            color: #00d4ff;
            font-weight: bold;
        }}

        .timestamp {{
            text-align: center;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }}
    </style>
</head>
<body>
    <div class="container">
        <h1>SMS API Load Test Report</h1>
        <p class="subtitle">Performance Analysis & Metrics Dashboard</p>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="value info">{metrics.total_requests:,}</div>
                <div class="label">Total Requests</div>
            </div>
            <div class="summary-card">
                <div class="value success">{metrics.successful_requests:,}</div>
                <div class="label">Successful</div>
            </div>
            <div class="summary-card">
                <div class="value danger">{metrics.failed_requests:,}</div>
                <div class="label">Failed</div>
            </div>
            <div class="summary-card">
                <div class="value {'success' if success_rate >= 95 else 'warning' if success_rate >= 80 else 'danger'}">{success_rate:.2f}%</div>
                <div class="label">Success Rate</div>
            </div>
            <div class="summary-card">
                <div class="value primary">{avg_rps:.2f}</div>
                <div class="label">Avg Requests/Sec</div>
            </div>
            <div class="summary-card">
                <div class="value purple">{total_time:.2f}s</div>
                <div class="label">Total Duration</div>
            </div>
        </div>

        <!-- Success Rate Progress Bar -->
        <div class="section">
            <h2>Success Rate Overview</h2>
            <div class="progress-bar">
                <div class="progress-fill progress-success" style="width: {success_rate}%">
                    {success_rate:.2f}% Success
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                <span><span class="success">●</span> Success: {metrics.successful_requests:,}</span>
                <span><span class="danger">●</span> Failed: {metrics.failed_requests:,}</span>
            </div>
        </div>

        <!-- Test Configuration & Timing -->
        <div class="two-column">
            <div class="section">
                <h2>Test Configuration</h2>
                <ul class="config-list">
                    <li><span>API URL</span><span>{API_URL}</span></li>
                    <li><span>Total Requests</span><span>{TOTAL_REQUESTS:,}</span></li>
                    <li><span>Concurrent Requests</span><span>{CONCURRENT_REQUESTS}</span></li>
                    <li><span>First Number</span><span>{FIRST_NUMBER}</span></li>
                    <li><span>Last Number</span><span>{LAST_NUMBER}</span></li>
                    <li><span>Message Text</span><span>{API_PARAMS['txt']}</span></li>
                    <li><span>Route</span><span>{API_PARAMS['route']}</span></li>
                </ul>
            </div>
            <div class="section">
                <h2>Timing Information</h2>
                <ul class="config-list">
                    <li><span>Start Time</span><span>{datetime.fromtimestamp(metrics.start_time).strftime('%Y-%m-%d %H:%M:%S')}</span></li>
                    <li><span>End Time</span><span>{datetime.fromtimestamp(metrics.end_time).strftime('%Y-%m-%d %H:%M:%S')}</span></li>
                    <li><span>Total Duration</span><span>{total_time:.2f} seconds</span></li>
                    <li><span>Avg Requests/Second</span><span>{avg_rps:.2f} RPS</span></li>
                    <li><span>Peak RPS</span><span>{max([b['total'] for b in metrics.second_buckets.values()]) if metrics.second_buckets else 0}</span></li>
                </ul>
            </div>
        </div>

        <!-- Response Time Statistics -->
        <div class="section">
            <h2>Response Time Statistics</h2>
            <div class="metrics-grid">
                <div class="metric-item">
                    <div class="value">{avg_response_time:.2f} ms</div>
                    <div class="label">Average</div>
                </div>
                <div class="metric-item">
                    <div class="value">{min_response_time:.2f} ms</div>
                    <div class="label">Minimum</div>
                </div>
                <div class="metric-item">
                    <div class="value">{max_response_time:.2f} ms</div>
                    <div class="label">Maximum</div>
                </div>
                <div class="metric-item">
                    <div class="value">{p50:.2f} ms</div>
                    <div class="label">P50 (Median)</div>
                </div>
                <div class="metric-item">
                    <div class="value">{p90:.2f} ms</div>
                    <div class="label">P90</div>
                </div>
                <div class="metric-item">
                    <div class="value">{p95:.2f} ms</div>
                    <div class="label">P95</div>
                </div>
                <div class="metric-item">
                    <div class="value">{p99:.2f} ms</div>
                    <div class="label">P99</div>
                </div>
            </div>
        </div>

        <!-- Requests Per Second Chart -->
        <div class="section">
            <h2>Requests Per Second Over Time</h2>
            <div class="chart-container">
                <canvas id="rpsChart"></canvas>
            </div>
        </div>

        <!-- Response Time Distribution Chart -->
        <div class="section">
            <h2>Response Time Distribution</h2>
            <div class="chart-container">
                <canvas id="responseTimeChart"></canvas>
            </div>
        </div>

        <!-- Status Codes & Errors -->
        <div class="two-column">
            <div class="section">
                <h2>HTTP Status Code Distribution</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Status Code</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        {status_codes_html if status_codes_html else '<tr><td colspan="3" style="text-align:center">No data</td></tr>'}
                    </tbody>
                </table>
            </div>
            <div class="section">
                <h2>Error Distribution (Top 20)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Error Type</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        {errors_html if errors_html else '<tr><td colspan="2" style="text-align:center">No errors recorded</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Failed Requests Details -->
        <div class="section">
            <h2>Failed Requests Details (First 100)</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Phone Number</th>
                            <th>Status Code</th>
                            <th>Response Time</th>
                            <th>Error</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        {failed_requests_html if failed_requests_html else '<tr><td colspan="5" style="text-align:center">No failed requests</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>

        <p class="timestamp">Report generated at {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</p>
    </div>

    <script>
        // RPS Chart Data
        const rpsData = {json.dumps(rps_data)};

        // RPS Chart
        const rpsCtx = document.getElementById('rpsChart').getContext('2d');
        new Chart(rpsCtx, {{
            type: 'line',
            data: {{
                labels: rpsData.map(d => d.second + 's'),
                datasets: [
                    {{
                        label: 'Total RPS',
                        data: rpsData.map(d => d.total),
                        borderColor: '#00d4ff',
                        backgroundColor: 'rgba(0, 212, 255, 0.1)',
                        fill: true,
                        tension: 0.4
                    }},
                    {{
                        label: 'Successful',
                        data: rpsData.map(d => d.success),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    }},
                    {{
                        label: 'Failed',
                        data: rpsData.map(d => d.failed),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    }}
                ]
            }},
            options: {{
                responsive: true,
                maintainAspectRatio: false,
                plugins: {{
                    legend: {{
                        labels: {{ color: '#eee' }}
                    }}
                }},
                scales: {{
                    x: {{
                        ticks: {{ color: '#888' }},
                        grid: {{ color: 'rgba(255,255,255,0.1)' }}
                    }},
                    y: {{
                        ticks: {{ color: '#888' }},
                        grid: {{ color: 'rgba(255,255,255,0.1)' }}
                    }}
                }}
            }}
        }});

        // Response Time Distribution
        const responseTimes = {json.dumps(metrics.response_times[:10000])};  // Limit for performance
        const buckets = {{}};
        const bucketSize = 50; // 50ms buckets

        responseTimes.forEach(time => {{
            const bucket = Math.floor(time / bucketSize) * bucketSize;
            buckets[bucket] = (buckets[bucket] || 0) + 1;
        }});

        const sortedBuckets = Object.keys(buckets).map(Number).sort((a, b) => a - b);

        const rtCtx = document.getElementById('responseTimeChart').getContext('2d');
        new Chart(rtCtx, {{
            type: 'bar',
            data: {{
                labels: sortedBuckets.map(b => b + '-' + (b + bucketSize) + 'ms'),
                datasets: [{{
                    label: 'Request Count',
                    data: sortedBuckets.map(b => buckets[b]),
                    backgroundColor: 'rgba(123, 44, 191, 0.7)',
                    borderColor: '#7b2cbf',
                    borderWidth: 1
                }}]
            }},
            options: {{
                responsive: true,
                maintainAspectRatio: false,
                plugins: {{
                    legend: {{
                        labels: {{ color: '#eee' }}
                    }}
                }},
                scales: {{
                    x: {{
                        ticks: {{ color: '#888', maxRotation: 45 }},
                        grid: {{ color: 'rgba(255,255,255,0.1)' }}
                    }},
                    y: {{
                        ticks: {{ color: '#888' }},
                        grid: {{ color: 'rgba(255,255,255,0.1)' }}
                    }}
                }}
            }}
        }});
    </script>
</body>
</html>
"""

    return html_content

async def main():
    """Main entry point"""
    try:
        # Run load test
        metrics = await run_load_test()

        # Generate HTML report
        print("\nGenerating HTML report...")
        html_report = generate_html_report(metrics)

        # Get the project root directory
        project_root = os.path.dirname(os.path.abspath(__file__))
        public_folder = os.path.join(project_root, "public")

        # Ensure public folder exists
        if not os.path.exists(public_folder):
            os.makedirs(public_folder)

        # Save report with timestamp in public folder
        report_filename_timestamp = f"load_test_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"
        report_path_timestamp = os.path.join(public_folder, report_filename_timestamp)

        # Also save as a fixed filename for easy access
        report_filename_latest = "load_test_report.html"
        report_path_latest = os.path.join(public_folder, report_filename_latest)

        # Write both files
        with open(report_path_timestamp, 'w', encoding='utf-8') as f:
            f.write(html_report)

        with open(report_path_latest, 'w', encoding='utf-8') as f:
            f.write(html_report)

        print(f"\nReports saved to:")
        print(f"  - {report_path_timestamp}")
        print(f"  - {report_path_latest}")

        # Print URL access info
        base_url = API_URL.rsplit('/api/', 1)[0]  # Extract base URL
        print(f"\nAccess report via URL:")
        print(f"  - {base_url}/{report_filename_latest}")
        print(f"  - {base_url}/{report_filename_timestamp}")

        # Print summary
        total_time = metrics.end_time - metrics.start_time
        print("\n" + "=" * 60)
        print("LOAD TEST SUMMARY")
        print("=" * 60)
        print(f"Total Requests:      {metrics.total_requests:,}")
        print(f"Successful:          {metrics.successful_requests:,}")
        print(f"Failed:              {metrics.failed_requests:,}")
        print(f"Success Rate:        {(metrics.successful_requests / metrics.total_requests * 100):.2f}%")
        print(f"Total Duration:      {total_time:.2f} seconds")
        print(f"Avg Requests/Sec:    {metrics.total_requests / total_time:.2f}")
        print(f"Avg Response Time:   {sum(metrics.response_times) / len(metrics.response_times):.2f} ms")
        print("=" * 60)

        # Open report in browser using the URL
        print(f"\nOpening report in browser...")
        import webbrowser
        webbrowser.open(f"{base_url}/{report_filename_latest}")

    except KeyboardInterrupt:
        print("\n\nLoad test interrupted by user.")
    except Exception as e:
        print(f"\nError during load test: {e}")
        raise

if __name__ == "__main__":
    asyncio.run(main())
