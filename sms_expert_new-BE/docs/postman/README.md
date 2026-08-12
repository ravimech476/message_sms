# SMS Expert - Mobile API Postman Collection

## Files

| File | Description |
|------|-------------|
| `SMS_Expert_Mobile_API.postman_collection.json` | Main API collection with all endpoints |
| `SMS_Expert_Mobile_API_Local.postman_environment.json` | Environment for local development |
| `SMS_Expert_Mobile_API_Production.postman_environment.json` | Environment for production |

---

## How to Import

### Step 1: Import Collection
1. Open Postman
2. Click **Import** button (top left)
3. Drag and drop `SMS_Expert_Mobile_API.postman_collection.json`
4. Click **Import**

### Step 2: Import Environment
1. Click **Import** button
2. Drag and drop `SMS_Expert_Mobile_API_Local.postman_environment.json`
3. Click **Import**

### Step 3: Select Environment
1. Click the environment dropdown (top right, shows "No Environment")
2. Select **SMS Expert - Mobile API (Local)**

---

## How to Use

### Step 1: Configure Environment
1. Click the **eye icon** next to environment dropdown
2. Click **Edit** on the environment
3. Update these values:
   - `username`: Your actual username
   - `password`: Your actual password
4. Click **Save**

### Step 2: Login First
1. Open **Authentication > Login** request
2. Update the body with your credentials (or use environment variables):
```json
{
    "userName": "{{username}}",
    "password": "{{password}}",
    "device_name": "Postman Test",
    "device_id": "postman-001"
}
```
3. Click **Send**
4. ✅ Token is **automatically saved** to collection variables!

### Step 3: Use Other Endpoints
All other endpoints will automatically use the saved token.

---

## Collection Features

### Auto Token Save
The Login request has a test script that automatically saves the token:
```javascript
var jsonData = pm.response.json();
if (jsonData.status === true && jsonData.data.token) {
    pm.collectionVariables.set('token', jsonData.data.token);
}
```

### Automatic Authentication
The collection has Bearer Token authentication configured at the root level:
- All requests inherit this authentication
- Token uses `{{token}}` variable
- Login request has "No Auth" to avoid circular dependency

---

## Endpoints Overview

### Authentication (No token required for Login)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/login` | Login and get token |
| POST | `/auth/logout` | Logout current device |
| POST | `/auth/logout-all` | Logout all devices |
| GET | `/auth/check` | Check auth status |
| POST | `/auth/refresh-token` | Get new token |
| POST | `/auth/change-password` | Change password |
| POST | `/auth/push-token` | Update FCM token |

### User Profile
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/user/profile` | Get user profile |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Dashboard overview |
| GET | `/dashboard/wallet` | Wallet balance |
| GET | `/dashboard/stats` | SMS statistics |
| GET | `/dashboard/monthly-counts` | Monthly chart data |
| GET | `/dashboard/daily-counts` | Daily chart data |

### SMS
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/sms/send` | Send SMS |
| GET | `/sms/history` | SMS history |
| GET | `/sms/{id}` | SMS details |

### Contacts
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/contacts` | List contacts |
| GET | `/contacts/{id}` | Get contact |
| POST | `/contacts` | Create contact |
| PUT | `/contacts/{id}` | Update contact |
| DELETE | `/contacts/{id}` | Delete contact |

### Notifications
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notifications` | List notifications |
| GET | `/notifications/unread-count` | Unread count |
| POST | `/notifications/{id}/read` | Mark as read |
| POST | `/notifications/mark-all-read` | Mark all read |

### Token Test (Development)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/token-test/user` | Get user from token |
| GET | `/token-test/user-by-bigid` | Get user by bigid |
| GET | `/token-test/current-token` | Token info |
| GET | `/token-test/validate` | Validate permissions |
| POST | `/validate-token` | Manual validation |

---

## Testing Flow

### Quick Test
1. **Login** → Get token
2. **Get Dashboard** → Verify authentication works
3. **Get Profile** → See user details
4. **Get Wallet** → Check balance

### Full Test
1. Login
2. Check Auth Status
3. Get Dashboard
4. Get Profile
5. Get SMS History
6. Send SMS (test)
7. Get Contacts
8. Create Contact
9. Update Contact
10. Delete Contact
11. Get Notifications
12. Logout

---

## Troubleshooting

### "Unauthenticated" Error
1. Check if token is set: Click eye icon → Check `token` value
2. Re-run Login request
3. Verify credentials are correct

### "404 Not Found"
1. Verify `base_url` is correct
2. Check if Laravel server is running: `php artisan serve`

### Token Not Saving
1. Check Login response has `data.token`
2. Open Postman Console (View > Show Postman Console)
3. Look for "Token saved successfully!" message

---

## Environment Variables

| Variable | Description |
|----------|-------------|
| `base_url` | API base URL |
| `token` | Bearer token (auto-saved) |
| `username` | Login username |
| `password` | Login password |

---

## Response Format

### Success
```json
{
    "status": true,
    "message": "Success message",
    "data": { ... }
}
```

### Error
```json
{
    "status": false,
    "message": "Error message",
    "errors": {
        "field": ["Error details"]
    }
}
```

---

*Last Updated: December 2024*
