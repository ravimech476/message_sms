import aiohttp
import asyncio
import os
import pymysql
import webbrowser
from datetime import datetime
from tqdm import tqdm
from dotenv import load_dotenv

# =====================================================
# LOAD ENV
# =====================================================
load_dotenv()

# Local URL 
# URL = "http://smsexpert:8000/api/smsg/sms.mes"

# Dev URL 
URL = "https://smsexpert.nedtechnology.co.in/api/smsg/sms.mes"

TOTAL_RECORDS = 50000
BATCH_SIZE = 10000

# SAFE VALUE (DON'T USE 1000+)
CONCURRENT_CONNECTIONS = 500

COMMON_PARAMS = {
    "usr": "master",
    "pwd": "master",
    "from": "MYBRANDNAME",
    "type": "text",
    "route": "d",
    "txt": "Load Test Message"
}

DB_CONFIG = {
    "host": os.getenv("DB_HOST"),
    "port": int(os.getenv("DB_PORT")),
    "user": os.getenv("DB_USERNAME"),
    "password": os.getenv("DB_PASSWORD"),
    "database": os.getenv("DB_DATABASE"),
    "cursorclass": pymysql.cursors.DictCursor
}


# =====================================================
# LOAD NUMBERS
# =====================================================
def load_numbers():
    with open("numbers_bulk.txt") as f:
        return [x.strip() for x in f if x.strip()]


# =====================================================
# SAFE SEND WITH SUCCESS/FAIL COUNT
# =====================================================
sem = asyncio.Semaphore(CONCURRENT_CONNECTIONS)

async def send_sms(session, mobile, pbar):

    async with sem:
        params = COMMON_PARAMS.copy()
        params["to"] = mobile

        try:
            async with session.get(URL, params=params, timeout=10) as r:
                if r.status == 200:
                    pbar.update(1)
                    return True
                else:
                    return False
        except:
            return False


# =====================================================
# FETCH LAST STORED RECORDS FROM DB
# =====================================================
def fetch_records():

    conn = pymysql.connect(**DB_CONFIG)

    with conn.cursor() as cur:
        cur.execute(f"""
            SELECT id, mobnum, timesubmitted
            FROM smsg_log
            ORDER BY id DESC
            LIMIT {TOTAL_RECORDS}
        """)

        rows = cur.fetchall()

    conn.close()

    return list(reversed(rows))


# =====================================================
# BUILD HTML REPORT
# =====================================================
def build_report(rows, sent, success_api, failed_api, filename):

    def to_dt(t):
        return datetime.strptime(t, "%Y%m%d%H%M%S")

    def format_time(seconds):
        mins = int(seconds // 60)
        secs = int(seconds % 60)
        return f"{mins}m {secs}s"

    stored = len(rows)
    missing = sent - stored

    start_dt = to_dt(rows[0]["timesubmitted"])
    end_dt = to_dt(rows[-1]["timesubmitted"])
    total_time = (end_dt - start_dt).total_seconds()

    overall_rps = round(stored / total_time, 2) if total_time else 0

    html_rows = ""
    batch_no = 1

    for i in range(0, stored, BATCH_SIZE):
        batch = rows[i:i+BATCH_SIZE]

        b_start = to_dt(batch[0]["timesubmitted"])
        b_end = to_dt(batch[-1]["timesubmitted"])

        duration = (b_end - b_start).total_seconds()
        rps = round(len(batch)/duration,2) if duration else 0

        html_rows += f"""
        <tr>
            <td>{batch_no}</td>
            <td>{len(batch)}</td>
            <td>{format_time(duration)}</td>
            <td style="display:none;">{rps}</td>
        </tr>
        """

        batch_no += 1

    html = f"""
    <html>
    <head>
    <style>
        body {{
            font-family: Arial;
            padding: 40px;
            background-color: #f4f6f9;
        }}
        h2 {{
            color: #2c3e50;
        }}
        .card {{
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }}
        .success {{ color: green; font-weight: bold; }}
        .fail {{ color: red; font-weight: bold; }}
        .warn {{ color: orange; font-weight: bold; }}
        table {{
            border-collapse: collapse;
            width: 60%;
            background: white;
        }}
        th {{
            background: #34495e;
            color: white;
        }}
        th, td {{
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }}
        tr:nth-child(even) {{
            background-color: #f2f2f2;
        }}
    </style>
    </head>

    <body>

    <h2>📊 SMS Load Test Report</h2>

    <div class="card">
    <h3>Summary</h3>

    <p>Sent Requests: <b>{sent}</b></p>
    <p style="display:none;">Success API: <span class="success">{success_api}</span></p>
    <p style="display:none;">Failed API: <span class="fail">{failed_api}</span></p>
    <p>Stored in DB: <b>{stored}</b></p>
    <p>Missing: <span class="warn">{missing}</span></p>
    <p>Total Time: <b>{format_time(total_time)}</b></p>
    <p style="display:none;">Overall RPS: <b>{overall_rps}</b></p>
    </div>

    <div class="card">
    <h3>Batch (10k each)</h3>

    <table>
        <tr>
            <th>Batch</th>
            <th>Count</th>
            <th>Time (min)</th>
            <th style="display:none;">RPS</th>
        </tr>
        {html_rows}
    </table>
    </div>

    </body>
    </html>
    """

    with open(filename, "w") as f:
        f.write(html)

# =====================================================
# MAIN
# =====================================================
async def main():

    numbers = load_numbers()
    sent = len(numbers)

    print(f"Sending {sent} SMS (max {CONCURRENT_CONNECTIONS} concurrent)")

    connector = aiohttp.TCPConnector(limit=CONCURRENT_CONNECTIONS)

    async with aiohttp.ClientSession(connector=connector) as session:

        with tqdm(total=sent, desc="Success Only") as pbar:

            results = await asyncio.gather(
                *[send_sms(session, n, pbar) for n in numbers]
            )

    success_api = sum(results)
    failed_api = sent - success_api

    print("Fetching DB...")

    rows = fetch_records()

    os.makedirs("reports", exist_ok=True)
    filename = f"reports/load_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"

    build_report(rows, sent, success_api, failed_api, filename)

    webbrowser.open("file://" + os.path.abspath(filename))

    print("Report saved:", filename)


# =====================================================
if __name__ == "__main__":
    asyncio.run(main())
