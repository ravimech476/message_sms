import random

TOTAL = 50000

FIRST  = "917094514970"
SECOND = "919025603512"
THIRD  = "919003096885"


def random_mobile():
    # random Indian format
    return "91" + str(random.randint(6000000000, 9999999999))


numbers = []

# ---------------------------------
# First 3 fixed
# ---------------------------------
numbers.append(FIRST)
numbers.append(SECOND)
numbers.append(THIRD)

# ---------------------------------
# Random middle numbers
# ---------------------------------
for _ in range(TOTAL - 6):   # 3 first + 3 last
    numbers.append(random_mobile())

# ---------------------------------
# Last 3 same again
# ---------------------------------
numbers.append(FIRST)
numbers.append(SECOND)
numbers.append(THIRD)

# ---------------------------------
# Save to numbers.txt
# ---------------------------------
with open("numbers.txt", "w") as f:
    for n in numbers:
        f.write(n + "\n")

print("numbers.txt created successfully")
print("Total numbers:", len(numbers))
