import urllib.request
import json

urls = [
    "http://localhost:8000/api/hutang-cutting",
    "http://localhost:8000/api/cashboan-cutting",
    "http://localhost:8000/api/pendapatan-cutting"
]

for url in urls:
    try:
        req = urllib.request.Request(url, headers={'Accept': 'application/json'})
        with urllib.request.urlopen(req) as response:
            data = json.loads(response.read())
            print(f"--- {url} ---")
            if isinstance(data, list):
                for item in data:
                    print(item)
            elif 'data' in data:
                for item in data['data']:
                    print(item)
            else:
                print(data)
    except Exception as e:
        print(f"Error fetching {url}: {e}")
