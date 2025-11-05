# SmartRecruiters Webhook API cURL Commands

এই commands গুলো Postman এ test করার জন্য।

## 1. Access Token পাওয়ার জন্য (প্রথমে এটা চালান)

```bash
curl --location 'https://api.sandbox.smartrecruiters.com/identity/oauth/token' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'grant_type=client_credentials' \
--data-urlencode 'client_id=YOUR_CLIENT_ID' \
--data-urlencode 'client_secret=YOUR_CLIENT_SECRET'
```

**Response Example:**
```json
{
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
}
```

---

## 2. Webhook Subscription তৈরি করার জন্য

**Note:** আগে access token নিয়ে নিন (step 1), তারপর `YOUR_ACCESS_TOKEN` এর জায়গায় token বসান।

```bash
curl --location 'https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions' \
--header 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
--header 'Content-Type: application/json' \
--data '{
    "callbackUrl": "https://your-website.com/smartrecruiters-webhook/",
    "events": [
        "job.created",
        "job.updated",
        "job.status.updated",
        "position.created",
        "position.updated",
        "position.deleted"
    ]
}'
```

**Response Example (201 Created):**
```json
{
    "id": "12345678-1234-1234-1234-123456789012",
    "callbackUrl": "https://your-website.com/smartrecruiters-webhook/",
    "events": [
        "job.created",
        "job.updated",
        "job.status.updated",
        "position.created",
        "position.updated",
        "position.deleted"
    ],
    "active": false
}
```

**Important:** Response থেকে `id` টা save করুন, পরবর্তী steps এ লাগবে।

---

## 3. Webhook Subscription Activate করার জন্য

**Note:** `WEBHOOK_ID` এর জায়গায় step 2 থেকে পাওয়া `id` বসান।

```bash
curl --location --request PUT 'https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions/WEBHOOK_ID/activate' \
--header 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

**Response:** 200 OK বা 204 No Content (success মানে)

---

## 4. সব Webhook Subscriptions দেখার জন্য

```bash
curl --location 'https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions' \
--header 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

---

## 5. একটি নির্দিষ্ট Webhook Subscription দেখার জন্য

```bash
curl --location 'https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions/WEBHOOK_ID' \
--header 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

---

## 6. Webhook Subscription Delete করার জন্য

```bash
curl --location --request DELETE 'https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions/WEBHOOK_ID' \
--header 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

**Response:** 204 No Content (success মানে)

---

## Postman এ Import করার জন্য

### Step 1: Access Token
- **Method:** POST
- **URL:** `https://api.sandbox.smartrecruiters.com/identity/oauth/token`
- **Headers:**
  - `Content-Type: application/x-www-form-urlencoded`
- **Body (x-www-form-urlencoded):**
  - `grant_type`: `client_credentials`
  - `client_id`: `YOUR_CLIENT_ID`
  - `client_secret`: `YOUR_CLIENT_SECRET`

### Step 2: Create Webhook
- **Method:** POST
- **URL:** `https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions`
- **Headers:**
  - `Authorization: Bearer {{access_token}}`
  - `Content-Type: application/json`
- **Body (raw JSON):**
```json
{
    "callbackUrl": "https://your-website.com/smartrecruiters-webhook/",
    "events": [
        "job.created",
        "job.updated",
        "job.status.updated",
        "position.created",
        "position.updated",
        "position.deleted"
    ]
}
```

### Step 3: Activate Webhook
- **Method:** PUT
- **URL:** `https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions/{{webhook_id}}/activate`
- **Headers:**
  - `Authorization: Bearer {{access_token}}`

### Step 4: Delete Webhook
- **Method:** DELETE
- **URL:** `https://api.sandbox.smartrecruiters.com/webhooks-api/v201907/subscriptions/{{webhook_id}}`
- **Headers:**
  - `Authorization: Bearer {{access_token}}`

---

## Important Notes:

1. **Access Token** expire হয় 1 hour পরে, তাই নতুন করে নিতে হবে
2. **Webhook URL** public accessible হতে হবে (HTTPS recommended)
3. **Events** array এ আপনি যেগুলো event চান সেগুলো রাখতে পারেন
4. Subscription create করার পর **activate** করা mandatory (step 3)
5. আপনার actual website URL ব্যবহার করুন `callbackUrl` এ

---

## Event Types:

- `job.created` - নতুন job তৈরি হলে
- `job.updated` - job update হলে
- `job.status.updated` - job status পরিবর্তন হলে
- `position.created` - নতুন position তৈরি হলে
- `position.updated` - position update হলে
- `position.deleted` - position delete হলে

---

## Testing Webhook:

Webhook receive হচ্ছে কিনা test করতে, আপনি SmartRecruiters dashboard এ গিয়ে:
1. একটি job create করুন
2. বা একটি job update করুন

তারপর আপনার WordPress site এর logs check করুন (error_log) বা webhook endpoint এ request এসেছে কিনা দেখুন।
