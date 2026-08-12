import aiohttp
import asyncio
import time
import os
from datetime import datetime
import webbrowser
from tqdm import tqdm

# =====================================
# CONFIG
# =====================================

URL = "http://smsexpert:8000/api/smsg/sms.mes"

COMMON_PARAMS = {
    "usr": "master",
    "pwd": "master",
    "from": "MYBRANDNAME",
    "type": "text",
    "route": "d",
    "txt": "Load Test Message Feb 9"
}

CONCURRENT_CONNECTIONS = 1000   # safe for heavy load


# =====================================
# LOAD NUMBERS
# =====================================
def load_numbers():
    with open("numbers.txt") as f:
        return [line.strip() for line in f if line.strip()]


# =====================================
# SEND SINGLE SMS
# =====================================
async def send_sms(session, mobile, pbar):
    params = COMMON_PARAMS.copy()
    params["to"] = mobile

    try:
        async with session.get(URL, params=params, timeout=5) as r:
            status = r.status
    except:
        status = "FAILED"

    pbar.update(1)
    return mobile, status


# =====================================
# MAIN
# =====================================
async def main():

    numbers = load_numbers()

    print(f"\n🚀 Starting Load Test")
    print(f"📱 Total numbers: {len(numbers)}\n")

    start = time.time()

    results = []

    connector = aiohttp.TCPConnector(limit=CONCURRENT_CONNECTIONS)

    async with aiohttp.ClientSession(connector=connector) as session:

        with tqdm(total=len(numbers), desc="Sending SMS", unit="sms") as pbar:

            tasks = [
                send_sms(session, num, pbar)
                for num in numbers
            ]

            for coro in asyncio.as_completed(tasks):
                res = await coro
                results.append(res)

    end = time.time()

    # =====================================
    # STATS
    # =====================================
    total_time = round(end - start, 2)
    success = sum(1 for _, r in results if r == 200)
    failed = sum(1 for _, r in results if r != 200)
    total = len(results)
    rps = round(total / total_time, 2)
    success_rate = round((success / total) * 100, 2)

    print("\n✅ Test Finished")
    print("Success:", success)
    print("Failed :", failed)
    print("Time   :", total_time, "sec")
    print("RPS    :", rps)

    # =====================================
    # CREATE REPORTS FOLDER
    # =====================================
    folder = "reports"
    os.makedirs(folder, exist_ok=True)

    filename = f"{folder}/sms_load_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"

    # =====================================
    # BUILD HTML
    # =====================================
    rows = ""

    for mobile, status in results:
        color = "#28a745" if status == 200 else "#dc3545"
        rows += f"<tr><td>{mobile}</td><td style='color:{color};font-weight:bold'>{status}</td></tr>"

    html = f"""
    <html>
    <head>
        <title>SMS Load Test Report</title>

        <style>
            body {{
                font-family: Arial;
                background:#f4f6f8;
                padding:40px;
            }}

            h1 {{
                margin-bottom:20px;
            }}

            .card {{
                background:white;
                padding:20px;
                border-radius:8px;
                box-shadow:0 2px 8px rgba(0,0,0,0.1);
                margin-bottom:25px;
            }}

            table {{
                border-collapse: collapse;
                width: 100%;
                background:white;
            }}

            th, td {{
                border:1px solid #ddd;
                padding:8px;
                text-align:center;
            }}

            th {{
                background:#222;
                color:white;
            }}
        </style>
    </head>

    <body>

    <h1>📊 SMS Load Test Report</h1>

    <div class="card">
        <b>Total Requests:</b> {total} <br><br>
        <b>Success:</b> {success} <br><br>
        <b>Failed:</b> {failed} <br><br>
        <b>Total Time:</b> {total_time} sec <br><br>
        <b>Requests / Second:</b> {rps} <br><br>
        <b>Success Rate:</b> {success_rate}%
    </div>

    <table>
        <tr>
            <th>Mobile Number</th>
            <th>Status</th>
        </tr>
        {rows}
    </table>

    </body>
    </html>
    """

    # =====================================
    # SAVE HTML
    # =====================================
    with open(filename, "w") as f:
        f.write(html)

    print(f"\n📊 Report saved → {filename}")

    # open browser automatically
    webbrowser.open(f"file://{os.path.abspath(filename)}")


# =====================================
# RUN
# =====================================
if __name__ == "__main__":
    asyncio.run(main())
