#!/bin/bash

# SmartRecruiters Webhook API Test Script
# Usage: ./test-webhook.sh

# ============================================
# CONFIGURATION - এখানে আপনার credentials বসান
# ============================================
API_URL="https://api.sandbox.smartrecruiters.com"
CLIENT_ID="YOUR_CLIENT_ID"
CLIENT_SECRET="YOUR_CLIENT_SECRET"
WEBHOOK_CALLBACK_URL="https://your-website.com/smartrecruiters-webhook/"

# ============================================
# Step 1: Get Access Token
# ============================================
echo "Step 1: Getting Access Token..."
TOKEN_RESPONSE=$(curl -s --location "${API_URL}/identity/oauth/token" \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "grant_type=client_credentials" \
  --data-urlencode "client_id=${CLIENT_ID}" \
  --data-urlencode "client_secret=${CLIENT_SECRET}")

ACCESS_TOKEN=$(echo $TOKEN_RESPONSE | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)

if [ -z "$ACCESS_TOKEN" ]; then
  echo "❌ Error: Failed to get access token"
  echo "Response: $TOKEN_RESPONSE"
  exit 1
fi

echo "✅ Access Token: ${ACCESS_TOKEN:0:50}..."
echo ""

# ============================================
# Step 2: Create Webhook Subscription
# ============================================
echo "Step 2: Creating Webhook Subscription..."
WEBHOOK_RESPONSE=$(curl -s --location "${API_URL}/webhooks-api/v201907/subscriptions" \
  --header "Authorization: Bearer ${ACCESS_TOKEN}" \
  --header 'Content-Type: application/json' \
  --data "{
    \"callbackUrl\": \"${WEBHOOK_CALLBACK_URL}\",
    \"events\": [
        \"job.created\",
        \"job.updated\",
        \"job.status.updated\",
        \"position.created\",
        \"position.updated\",
        \"position.deleted\"
    ]
}")

WEBHOOK_ID=$(echo $WEBHOOK_RESPONSE | grep -o '"id":"[^"]*' | cut -d'"' -f4)

if [ -z "$WEBHOOK_ID" ]; then
  echo "❌ Error: Failed to create webhook subscription"
  echo "Response: $WEBHOOK_RESPONSE"
  exit 1
fi

echo "✅ Webhook Created!"
echo "Webhook ID: $WEBHOOK_ID"
echo "Response: $WEBHOOK_RESPONSE"
echo ""

# ============================================
# Step 3: Activate Webhook Subscription
# ============================================
echo "Step 3: Activating Webhook Subscription..."
ACTIVATE_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" --location --request PUT \
  "${API_URL}/webhooks-api/v201907/subscriptions/${WEBHOOK_ID}/activation" \
  --header "Authorization: Bearer ${ACCESS_TOKEN}")

HTTP_CODE=$(echo "$ACTIVATE_RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "204" ]; then
  echo "✅ Webhook Activated Successfully!"
else
  echo "⚠️  Warning: Activation may have failed (HTTP $HTTP_CODE)"
  echo "Response: $ACTIVATE_RESPONSE"
fi
echo ""

# ============================================
# Step 4: List All Webhooks (Verification)
# ============================================
echo "Step 4: Listing All Webhook Subscriptions..."
LIST_RESPONSE=$(curl -s --location \
  "${API_URL}/webhooks-api/v201907/subscriptions" \
  --header "Authorization: Bearer ${ACCESS_TOKEN}")

echo "All Webhooks:"
echo "$LIST_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$LIST_RESPONSE"
echo ""

echo "============================================"
echo "✅ Webhook Setup Complete!"
echo "Webhook ID: $WEBHOOK_ID"
echo "Callback URL: $WEBHOOK_CALLBACK_URL"
echo "============================================"
