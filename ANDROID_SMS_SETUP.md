# Setting Up Your Own Android SMS Gateway (Free Solution)

This guide will help you turn your Android phone into an SMS Gateway, allowing the system to send text messages using your phone's SIM card and unlimited text plan.

## Step 1: Install an SMS Gateway App
You need an Android app that acts as a server to receive requests from your PC and send SMS.

**Recommended Option: "Android SMS Gateway" (Open Source)**
1.  Go to the GitHub page: https://github.com/capcom6/android-sms-gateway
2.  Download the **APK** from the "Releases" section.
3.  Install it on your Android phone.

*Alternative Option: "Traccar SMS Gateway"*
1.  Download from: https://www.traccar.org/sms-gateway/

## Step 2: Configure the App
1.  Open the app on your phone.
2.  Grant the necessary permissions (SMS, etc.).
3.  Look for the **Server** or **HTTP API** settings.
4.  Start the server. It will show you an **IP Address** and **Port**.
    *   Example: `192.168.1.5:8080`

**Critical Requirement:** Your computer (running XAMPP) and your Android phone MUST be connected to the **SAME WiFi network**.

## Step 3: Test the Connection
1.  On your computer, open a web browser.
2.  Type the IP address shown on your phone app (e.g., `http://192.168.1.5:8080`).
3.  If it loads (or gives a response), the connection is working.

## Step 4: Update Your Config
1.  Open the file: `c:\xampp\htdocs\ACSCCI-Attendance-Checker\config\sms_config.php`
2.  Locate the `'custom'` section (around line 77).
3.  Update the `'api_url'` to match your phone's IP address.

```php
'api_url' => 'http://192.168.1.XXX:8080/v1/sms', // Replace 192.168.1.XXX with your phone's IP
```

*Note: The exact URL path (`/v1/sms`) depends on which app you installed. Check the app's documentation or "Help" screen for the correct API Endpoint.*

## Step 5: Test
1.  Go to the Admin Panel > SMS Logs.
2.  Click "Test SMS".
3.  Check your phone to see if it sends the message.
