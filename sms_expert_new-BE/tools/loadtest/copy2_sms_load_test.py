import requests
import concurrent.futures
import time

# =====================
# CONFIG
# =====================

URL = "http://smsexpert:8000/api/smsg/sms.mes"

COMMON_PARAMS = {
    "usr": "master",
    "pwd": "master",
    "from": "MYBRANDNAME",
    "type": "text",
    "route": "d",
    "txt": "Load Test Message"
}

THREADS = 20


# =====================
# LOAD NUMBERS
# =====================

def load_numbers():
    with open("numbers.txt") as f:
        return [line.strip() for line in f if line.strip()]


# =====================
# SEND SMS
# =====================

def send_sms(mobile):
    params = COMMON_PARAMS.copy()
    params["to"] = mobile

    try:
        r = requests.get(URL, params=params, timeout=5)
        return r.status_code
    except:
        return "FAILED"


# =====================
# MAIN
# =====================

def main():
    numbers = load_numbers()

    print("Starting Load Test...")
    print("Total numbers:", len(numbers))

    start = time.time()

    with concurrent.futures.ThreadPoolExecutor(max_workers=THREADS) as executor:
        results = list(executor.map(send_sms, numbers))

    end = time.time()

    success = results.count(200)
    failed = results.count("FAILED")

    print("\n===== RESULT =====")
    print("Success:", success)
    print("Failed :", failed)
    print("Time   :", round(end-start, 2), "sec")
    print("RPS    :", round(len(numbers)/(end-start), 2))


if __name__ == "__main__":
    main()
