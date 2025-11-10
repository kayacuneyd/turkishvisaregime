#!/bin/bash
# Quick manual test via curl and grep to verify the page returns correct language

echo "🔄 Test 1: Loading page with German language parameter..."
curl -s "http://localhost:8000/?lang=de" | grep -q '"de":' && echo "✅ German translations present in HTML" || echo "❌ German translations NOT found"

echo ""
echo "🔄 Test 2: Checking if Germany country has German description..."
curl -s "http://localhost:8000/data/data.json" | jq '.[] | select(.country=="Germany") | .descriptions.de' | head -1

echo ""
echo "🔄 Test 3: Checking if Afghanistan has all 3 language versions..."
curl -s "http://localhost:8000/data/data.json" | jq '.[] | select(.country=="Afghanistan") | {en: .descriptions.en, tr: .descriptions.tr, de: .descriptions.de} | keys' 

echo ""
echo "✅ All manual tests completed. Check browser console (F12) for JavaScript errors."
