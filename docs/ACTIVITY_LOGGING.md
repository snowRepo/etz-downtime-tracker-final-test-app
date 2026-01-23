# Activity Logging System Documentation

## Overview

The Activity Logging System provides comprehensive audit trail capabilities for the eTranzact Downtime Tracker application. It automatically tracks all user actions and system events, providing administrators with powerful tools for security monitoring, compliance, troubleshooting, and user accountability.

---

## Features

### 📊 Statistics Dashboard

- **Total Logs**: View the total number of activity logs
- **Unique Users**: Count of distinct users with logged activity
- **Top Action**: Most frequently performed action type
- **Most Active User**: User with the highest activity count

### 🔍 Advanced Filtering

- **Date Range**: Filter logs by start and end dates
- **User Filter**: View logs for specific users
- **Action Types**: Multi-select from 22 different action types
- **Search**: Full-text search in descriptions and usernames
- **Collapsible Panel**: Expand/collapse filter section

### 📋 Activity Logs Table

- **Color-Coded Actions**: Visual distinction between action types
- **User Avatars**: Quick user identification with initials
- **Relative Timestamps**: Human-readable time (e.g., "5m ago", "2h ago")
- **IP Address Tracking**: Monitor user locations
- **Detail View**: Click to see complete log information

### 📄 Detail Modal

View comprehensive information for each log entry:

- Log ID and User information
- Action Type and Description
- IP Address and User Agent
- Timestamp
- **Metadata**: JSON-formatted context data (changes made, filters applied, etc.)

### 💾 Export Functionality

- **CSV Export**: Download filtered logs for external analysis
- Preserves all applied filters
- Includes all log fields

### 📄 Pagination

- Configurable results per page (25/50/100)
- Page navigation with current page indicator
- Shows "X to Y of Z results"

---

## Logged Actions

### Authentication (3 types)

- `login` - Successful user login
- `logout` - User logout
- `login_failed` - Failed login attempt with reason

### User Management (4 types)

- `user_created` - New user account created
- `user_updated` - User account modified (tracks all changes)
- `user_deleted` - User account deleted (preserves user data)
- `user_role_changed` - User role modified

### Incident Operations (6 types)

- `incident_created` - New incident reported
- `incident_updated` - Incident modified
- `incident_deleted` - Incident removed
- `incident_viewed` - Incident details accessed
- `incident_exported` - Incident data exported

### Reports & Analytics (5 types)

- `report_generated` - Report created
- `report_exported` - Report exported
- `analytics_viewed` - Analytics page accessed
- `analytics_exported` - Analytics data exported
- `sla_report_viewed` - SLA report accessed
- `sla_report_exported` - SLA report exported

### System (4 types)

- `password_changed` - User password updated
- `profile_updated` - User profile modified
- `settings_changed` - System settings changed
- `other` - Miscellaneous system actions

---

## Database Schema

### Table: `activity_logs`

```sql
CREATE TABLE activity_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action_type ENUM(...) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_username (username),

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);
```

### Field Descriptions

- **log_id**: Unique identifier for each log entry (BIGINT for scalability)
- **user_id**: Foreign key to users table (NULL for system actions)
- **username**: Username preserved even if user is deleted
- **action_type**: Type of action performed (ENUM with 22 values)
- **entity_type**: Type of entity affected (user, incident, etc.)
- **entity_id**: ID of the affected entity
- **description**: Human-readable description of the action
- **ip_address**: User's IP address (supports IPv4 and IPv6)
- **user_agent**: Browser/client user agent string
- **metadata**: JSON field for additional context data
- **created_at**: Timestamp when the action occurred

---

## API Functions

### Logging Functions

#### `logActivity($userId, $actionType, $description, $entityType = null, $entityId = null, $metadata = null)`

Main logging function with full parameter support.

**Parameters:**

- `$userId` (int|null): User ID (null for system actions)
- `$actionType` (string): Type of action (must match ENUM values)
- `$description` (string): Human-readable description
- `$entityType` (string|null): Type of entity affected
- `$entityId` (int|null): ID of the affected entity
- `$metadata` (array|null): Additional context data

**Returns:** `bool` - Success status

**Example:**

```php
logActivity(
    1,
    'user_created',
    'Created new user: john.doe',
    'user',
    5,
    ['username' => 'john.doe', 'role' => 'user']
);
```

#### `logLogin($userId, $success = true, $failureReason = null)`

Convenience function for login events.

#### `logLogout($userId)`

Convenience function for logout events.

#### `logUserAction($userId, $action, $targetUserId, $changes = null)`

Logs user management actions with automatic change tracking.

**Example:**

```php
logUserAction(1, 'updated', 3, [
    'email' => ['from' => 'old@example.com', 'to' => 'new@example.com'],
    'role' => ['from' => 'user', 'to' => 'admin']
]);
```

#### `logIncidentAction($userId, $action, $incidentId, $changes = null)`

Logs incident-related actions.

#### `logExport($userId, $exportType, $filters = null)`

Logs report export actions with applied filters.

### Retrieval Functions

#### `getActivityLogs($filters = [], $limit = 50, $offset = 0)`

Retrieve activity logs with filtering and pagination.

**Filters:**

- `user_id`: Filter by specific user
- `action_type`: Filter by action type (string or array)
- `start_date`: Filter by start date (Y-m-d format)
- `end_date`: Filter by end date (Y-m-d format)
- `entity_type`: Filter by entity type
- `entity_id`: Filter by entity ID
- `search`: Search in description and username

**Returns:** `array` - Array of log entries

#### `getActivityLogsCount($filters = [])`

Get total count of logs matching filters (for pagination).

#### `getUserActivitySummary($userId, $days = 30)`

Get activity summary for a specific user.

**Returns:** `array` - Statistics including total actions, active days, and action breakdown

#### `getRecentActivity($limit = 20)`

Get recent activity across all users.

#### `getActivityStats($startDate = null, $endDate = null)`

Get activity statistics for dashboard cards.

**Returns:** `array` - Total logs, unique users, top actions, and top users

### Maintenance Functions

#### `cleanupOldLogs($daysToKeep = 365)`

Remove logs older than specified days.

**Returns:** `int` - Number of logs deleted

#### `exportActivityLogsCSV($filters = [])`

Export activity logs to CSV format.

**Returns:** `string` - CSV content

---

## Usage Examples

### Example 1: Logging User Creation

```php
require_once 'src/includes/activity_logger.php';

$newUserId = 5;
$currentUserId = $_SESSION['user_id'];

logUserAction($currentUserId, 'created', $newUserId, [
    'username' => 'john.doe',
    'email' => 'john@example.com',
    'role' => 'user'
]);
```

### Example 2: Logging Incident Creation

```php
$userId = $_SESSION['user_id'];
$incidentId = $pdo->lastInsertId();

logIncidentAction($userId, 'created', $incidentId, [
    'service' => 'Mobile Money',
    'impact_level' => 'High',
    'companies_count' => 3
]);
```

### Example 3: Retrieving Filtered Logs

```php
$filters = [
    'user_id' => 1,
    'action_type' => ['login', 'logout'],
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31'
];

$logs = getActivityLogs($filters, 50, 0);
$totalLogs = getActivityLogsCount($filters);
```

### Example 4: Getting Activity Statistics

```php
$stats = getActivityStats('2026-01-01', '2026-01-31');

echo "Total Logs: " . $stats['total_logs'];
echo "Unique Users: " . $stats['unique_users'];
echo "Top Action: " . $stats['top_actions'][0]['action_type'];
```

---

## Configuration

### Settings in `config/config.php`

```php
// Activity Log Settings
define('ACTIVITY_LOG_RETENTION_DAYS', 365); // Keep logs for 1 year
define('ACTIVITY_LOG_CLEANUP_ENABLED', true); // Auto-cleanup old logs
```

### Customization

**Change Retention Period:**

```php
define('ACTIVITY_LOG_RETENTION_DAYS', 180); // Keep for 6 months
```

**Disable Auto-Cleanup:**

```php
define('ACTIVITY_LOG_CLEANUP_ENABLED', false);
```

---

## Security Considerations

### Data Protection

- **IP Addresses**: Logged for security monitoring but consider privacy regulations
- **User Agents**: Helps identify suspicious activity patterns
- **Metadata**: Never log sensitive data (passwords, tokens, etc.)

### Access Control

- Activity logs page requires admin role
- Only administrators can view and export logs
- User data is preserved even after account deletion

### Performance

- Indexes on frequently queried fields (user_id, action_type, created_at)
- BIGINT for log_id to support millions of entries
- JSON metadata for flexible data storage

### Compliance

- Audit trail meets common compliance requirements (SOX, HIPAA, GDPR)
- Logs are append-only (no updates or deletes except cleanup)
- Retention policy configurable per organization needs

---

## Troubleshooting

### Logs Not Appearing

**Check:**

1. Verify `activity_logs` table exists in database
2. Ensure `activity_logger.php` is included in files
3. Check error logs for PDO exceptions
4. Verify user has permission to insert into `activity_logs`

### Performance Issues

**Solutions:**

1. Run cleanup to remove old logs: `cleanupOldLogs(90)`
2. Add more specific filters when querying
3. Reduce pagination limit
4. Consider archiving old logs to separate table

### SQL Errors

**Common Issues:**

- Invalid action_type: Must match ENUM values
- NULL user_id: Acceptable for system actions
- Large metadata: Keep JSON data concise

---

## Best Practices

### When to Log

✅ **DO log:**

- Authentication events (login/logout)
- Data modifications (create/update/delete)
- Access to sensitive information
- Export operations
- Administrative actions

❌ **DON'T log:**

- Page views (too verbose)
- Read-only operations (unless sensitive)
- Automated system processes (unless critical)

### Metadata Guidelines

- Keep metadata concise and relevant
- Use structured data (arrays/objects)
- Never include sensitive information
- Include context that helps troubleshooting

### Performance Tips

- Use batch logging for bulk operations
- Run cleanup during off-peak hours
- Archive old logs before deletion
- Monitor table size regularly

---

## Migration Guide

### Creating the Table

Run the migration:

```bash
php config/migrations/run_migrations.php
```

Or manually execute:

```sql
-- See config/migrations/002_create_activity_logs.sql
```

### Verifying Installation

```php
// Test basic logging
require_once 'src/includes/activity_logger.php';

$result = logActivity(
    null,
    'other',
    'Activity logging system test',
    null,
    null,
    ['test' => true]
);

if ($result) {
    echo "Activity logging is working!";
}
```

---

## Future Enhancements

Potential improvements for future versions:

- Real-time log streaming with WebSockets
- Advanced analytics and visualizations
- Automated alerts for suspicious activity
- Log archival to external storage (S3, etc.)
- Integration with SIEM systems
- Role-based log access controls
- Log retention policies per action type
- Audit report generation

---

## Support

For issues or questions about the activity logging system:

1. Check the [walkthrough documentation](../brain/activity_logging_walkthrough.md)
2. Review the [implementation plan](../brain/activity_logging_plan.md)
3. Examine sample logs in the admin panel
4. Contact the development team

---

**Last Updated:** January 23, 2026  
**Version:** 1.0.0
