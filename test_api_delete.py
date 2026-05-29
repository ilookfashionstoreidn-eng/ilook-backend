import urllib.request
import urllib.error
import json

url = "http://localhost:8000/api/spk_cutting/4"
req = urllib.request.Request(url, method="DELETE")
req.add_header("Accept", "application/json")

try:
    with urllib.request.urlopen(req) as response:
        print("Success:", response.read().decode())
except urllib.error.HTTPError as e:
    print("HTTP Error:", e.code)
    print("Response:", e.read().decode())
except Exception as e:
    print("Error:", str(e))
