# Ngrok Setup Guide for XAMPP

This guide will help you expose your local XAMPP application to the internet using ngrok.

## Prerequisites

✅ XAMPP is installed and running
✅ Ngrok is installed (version 3.24.0-msix detected)
✅ Your application is in `c:\xampp\htdocs\etz-downtime-tracker-final-test-app`

## Quick Start

### 1. Start XAMPP

Make sure Apache and MySQL are running in XAMPP Control Panel.

### 2. Start Ngrok Tunnel

Open a terminal and run:

```bash
ngrok http 80
```

Or if you want to specify the full path to your app:

```bash
ngrok http 80 --host-header="localhost"
```

### 3. Access Your Application

After running ngrok, you'll see output like:

```
Forwarding    https://xxxx-xx-xx-xx-xx.ngrok-free.app -> http://localhost:80
```

Your application will be accessible at:

- **Public URL**: `https://xxxx-xx-xx-xx-xx.ngrok-free.app/etz-downtime-tracker-final-test-app/`
- **Local URL**: `http://localhost/etz-downtime-tracker-final-test-app/`

## Important Notes

### Database Configuration

Your `config.php` uses localhost for database connections. This will work fine because:

- Ngrok tunnels HTTP requests to your local machine
- PHP still connects to MySQL locally
- Only the web interface is exposed publicly

### Security Considerations

> [!WARNING]
> Your application will be publicly accessible on the internet. Consider:
>
> - Adding authentication if not already present
> - Using ngrok's authentication features
> - Not sharing sensitive data
> - Monitoring access logs

### Free vs Paid Ngrok

**Free tier limitations:**

- Random URL each time (e.g., `random-name-1234.ngrok-free.app`)
- Session expires after 2 hours
- Limited connections

**Paid tier benefits:**

- Custom subdomain (e.g., `myapp.ngrok.io`)
- No time limits
- More concurrent connections

## Advanced Options

### Use a Custom Subdomain (Requires Paid Plan)

```bash
ngrok http 80 --subdomain=my-downtime-tracker
```

### Add Basic Authentication

```bash
ngrok http 80 --basic-auth="username:password"
```

### Run in Background

```bash
ngrok http 80 > ngrok.log 2>&1 &
```

### View Ngrok Dashboard

While ngrok is running, visit:

- **Web Interface**: http://localhost:4040
- View all requests, responses, and replay them
- Inspect traffic in real-time

## Troubleshooting

### Port 80 Already in Use

If you get a port conflict, check what's using port 80:

```bash
netstat -ano | findstr :80
```

### Application Not Loading

Make sure to include the full path in the URL:

```
https://your-ngrok-url.ngrok-free.app/etz-downtime-tracker-final-test-app/
```

### Database Connection Issues

Verify your `config.php` has correct database credentials:

- Host: `localhost` (not the ngrok URL)
- Database: Your database name
- Username/Password: Your XAMPP MySQL credentials

## Stopping Ngrok

Press `Ctrl+C` in the terminal where ngrok is running.

## Alternative: Ngrok Configuration File

Create a config file at `%USERPROFILE%\.ngrok2\ngrok.yml`:

```yaml
version: "2"
authtoken: YOUR_AUTH_TOKEN_HERE
tunnels:
  downtime-tracker:
    proto: http
    addr: 80
    host_header: localhost
```

Then run:

```bash
ngrok start downtime-tracker
```

## Next Steps

1. **Get an Auth Token** (Optional but recommended):

   - Sign up at https://ngrok.com
   - Get your auth token from the dashboard
   - Run: `ngrok config add-authtoken YOUR_TOKEN`

2. **Test Your Application**:

   - Access the ngrok URL
   - Test all features (login, database operations, etc.)
   - Check the ngrok web interface at http://localhost:4040

3. **Share the URL**:
   - Copy the HTTPS URL from ngrok output
   - Share with team members or clients
   - Remember: URL changes each time with free tier

## Useful Commands

```bash
# Basic tunnel
ngrok http 80

# With host header (recommended for XAMPP)
ngrok http 80 --host-header="localhost"

# Check ngrok version
ngrok version

# View help
ngrok http --help
```
