import aiohttp
import asyncio
import time

URL = "http://smsexpert:8000/api/smsg/sms.mes"

COMMON_PARAMS = {
    "usr": "master",
    "pwd": "master",
    "from": "MYBRANDNAME",
    "type": "text",
    "route": "d",
    "txt": "Load Test Message Feb 9"
}

# =====================
# LOAD NUMBERS
# =====================

def load_numbers():
    with open("numbers_bulk.txt") as f:
        return [line.strip() for line in f if line.strip()]


# =====================
# SEND SMS (ASYNC)
# =====================

async def send_sms(session, mobile):
    params = COMMON_PARAMS.copy()
    params["to"] = mobile

    try:
        async with session.get(URL, params=params, timeout=5) as r:
            return r.status
    except:
        return "FAILED"


# =====================
# MAIN
# =====================

async def main():
    numbers = load_numbers()

    print("Starting FULL load test...")
    print("Total numbers:", len(numbers))

    start = time.time()

    async with aiohttp.ClientSession() as session:
        tasks = [send_sms(session, num) for num in numbers]

        #THIS SENDS ALL AT SAME TIME
        results = await asyncio.gather(*tasks)

    end = time.time()

    print("\n===== RESULT =====")
    print("Success:", results.count(200))
    print("Failed :", results.count("FAILED"))
    print("Time   :", round(end-start, 2), "sec")
    print("RPS    :", round(len(numbers)/(end-start), 2))


if __name__ == "__main__":
    asyncio.run(main())
