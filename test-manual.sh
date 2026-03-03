#!/usr/bin/env bash
# Quick manual smoke test. Usage: BASE_URL=http://localhost:8090 ./test-manual.sh
set -e
BASE="${BASE_URL:-http://localhost:8090}"

echo "1. Create room..."
ROOM=$(curl -s -X POST "$BASE/api/rooms" -H "Content-Type: application/json" -d '{"name":"SmokeTest"}')
ID=$(echo "$ROOM" | grep -o '"id":[0-9]*' | cut -d: -f2)
echo "   Room id: $ID"

echo "2. Send message..."
curl -s -X POST "$BASE/api/rooms/$ID/message" -H "Content-Type: application/json" -d '{"user_id":1,"content":"Hi"}'
echo ""

echo "3. History..."
curl -s "$BASE/api/rooms/$ID/history" | head -c 200
echo ""

echo "4. Analytics..."
curl -s "$BASE/api/analytics/room/$ID"
echo ""
echo "Done."
