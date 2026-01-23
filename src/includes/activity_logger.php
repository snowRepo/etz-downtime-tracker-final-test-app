<?php
/**
 * Activity Logger
 * 
 * Provides comprehensive activity logging functionality for audit trails,
 * security monitoring, and user action tracking.
 * 
 * @package ETZ Downtime Tracker
 * @version 1.0.0
 */

require_once __DIR__ . '/../../config/config.php';

/**
 * Log any user activity to the database
 * 
 * @param int|null $userId User ID (null for system actions)
 * @param string $actionType Type of action (must match ENUM values)
 * @param string $description Human-readable description
 * @param string|null $entityType Type of entity affected (user, incident, etc.)
 * @param int|null $entityId ID of the affected entity
 * @param array|null $metadata Additional context data
 * @return bool Success status
 */
function logActivity($userId, $actionType, $description, $entityType = null, $entityId = null, $metadata = null) {
    global $pdo;
    
    try {
        // Get username if user ID provided
        $username = null;
        if ($userId !== null) {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $username = $user ? $user['username'] : null;
        }
        
        // Get IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Get user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Convert metadata to JSON if provided
        $metadataJson = $metadata ? json_encode($metadata) : null;
        
        // Insert log entry
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (
                user_id, username, action_type, entity_type, entity_id,
                description, ip_address, user_agent, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $username,
            $actionType,
            $entityType,
            $entityId,
            $description,
            $ipAddress,
            $userAgent,
            $metadataJson
        ]);
        
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log user login
 * 
 * @param int $userId User ID
 * @param bool $success Whether login was successful
 * @param string|null $failureReason Reason for failure if unsuccessful
 * @return bool Success status
 */
function logLogin($userId, $success = true, $failureReason = null) {
    if ($success) {
        return logActivity(
            $userId,
            'login',
            'User logged in successfully'
        );
    } else {
        return logActivity(
            $userId,
            'login_failed',
            'Login attempt failed: ' . ($failureReason ?? 'Invalid credentials'),
            null,
            null,
            ['reason' => $failureReason]
        );
    }
}

/**
 * Log user logout
 * 
 * @param int $userId User ID
 * @return bool Success status
 */
function logLogout($userId) {
    return logActivity(
        $userId,
        'logout',
        'User logged out'
    );
}

/**
 * Log user management action
 * 
 * @param int $userId User performing the action
 * @param string $action Action type (created, updated, deleted, role_changed)
 * @param int $targetUserId Target user ID
 * @param array|null $changes Changes made (for updates)
 * @return bool Success status
 */
function logUserAction($userId, $action, $targetUserId, $changes = null) {
    global $pdo;
    
    // Get target username
    $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $targetUsername = $targetUser ? $targetUser['username'] : 'Unknown';
    
    $actionMap = [
        'created' => [
            'type' => 'user_created',
            'desc' => "Created new user: {$targetUsername}"
        ],
        'updated' => [
            'type' => 'user_updated',
            'desc' => "Updated user: {$targetUsername}"
        ],
        'deleted' => [
            'type' => 'user_deleted',
            'desc' => "Deleted user: {$targetUsername}"
        ],
        'role_changed' => [
            'type' => 'user_role_changed',
            'desc' => "Changed role for user: {$targetUsername}"
        ]
    ];
    
    $actionInfo = $actionMap[$action] ?? [
        'type' => 'other',
        'desc' => "Performed action on user: {$targetUsername}"
    ];
    
    return logActivity(
        $userId,
        $actionInfo['type'],
        $actionInfo['desc'],
        'user',
        $targetUserId,
        $changes
    );
}

/**
 * Log incident-related action
 * 
 * @param int $userId User performing the action
 * @param string $action Action type (created, updated, deleted, viewed)
 * @param int $incidentId Incident ID
 * @param array|null $changes Changes made (for updates)
 * @return bool Success status
 */
function logIncidentAction($userId, $action, $incidentId, $changes = null) {
    global $pdo;
    
    // Get incident details
    $stmt = $pdo->prepare("SELECT service, impact_level FROM incidents WHERE incident_id = ?");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    $service = $incident ? $incident['service'] : 'Unknown';
    
    $actionMap = [
        'created' => [
            'type' => 'incident_created',
            'desc' => "Reported new incident for {$service}"
        ],
        'updated' => [
            'type' => 'incident_updated',
            'desc' => "Updated incident for {$service}"
        ],
        'deleted' => [
            'type' => 'incident_deleted',
            'desc' => "Deleted incident for {$service}"
        ],
        'viewed' => [
            'type' => 'incident_viewed',
            'desc' => "Viewed incident details for {$service}"
        ]
    ];
    
    $actionInfo = $actionMap[$action] ?? [
        'type' => 'other',
        'desc' => "Performed action on incident for {$service}"
    ];
    
    return logActivity(
        $userId,
        $actionInfo['type'],
        $actionInfo['desc'],
        'incident',
        $incidentId,
        $changes
    );
}

/**
 * Log export action
 * 
 * @param int $userId User performing the export
 * @param string $exportType Type of export (analytics_pdf, sla_report, etc.)
 * @param array|null $filters Filters applied to the export
 * @return bool Success status
 */
function logExport($userId, $exportType, $filters = null) {
    $typeMap = [
        'analytics_pdf' => [
            'type' => 'analytics_exported',
            'desc' => 'Exported analytics report (PDF)'
        ],
        'analytics_excel' => [
            'type' => 'analytics_exported',
            'desc' => 'Exported analytics report (Excel)'
        ],
        'sla_report_pdf' => [
            'type' => 'sla_report_exported',
            'desc' => 'Exported SLA report (PDF)'
        ],
        'sla_report_excel' => [
            'type' => 'sla_report_exported',
            'desc' => 'Exported SLA report (Excel)'
        ],
        'incidents_excel' => [
            'type' => 'incident_exported',
            'desc' => 'Exported incidents list (Excel)'
        ]
    ];
    
    $exportInfo = $typeMap[$exportType] ?? [
        'type' => 'report_exported',
        'desc' => "Exported {$exportType} report"
    ];
    
    return logActivity(
        $userId,
        $exportInfo['type'],
        $exportInfo['desc'],
        null,
        null,
        ['export_type' => $exportType, 'filters' => $filters]
    );
}

/**
 * Get activity logs with filtering and pagination
 * 
 * @param array $filters Filters (user_id, action_type, start_date, end_date, search, entity_type, entity_id)
 * @param int $limit Number of results per page
 * @param int $offset Offset for pagination
 * @return array Array of log entries
 */
function getActivityLogs($filters = [], $limit = 50, $offset = 0) {
    global $pdo;
    
    $where = [];
    $params = [];
    
    // Filter by user ID
    if (!empty($filters['user_id'])) {
        $where[] = "user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    // Filter by action type
    if (!empty($filters['action_type'])) {
        if (is_array($filters['action_type'])) {
            $placeholders = str_repeat('?,', count($filters['action_type']) - 1) . '?';
            $where[] = "action_type IN ($placeholders)";
            $params = array_merge($params, $filters['action_type']);
        } else {
            $where[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
    }
    
    // Filter by date range
    if (!empty($filters['start_date'])) {
        $where[] = "created_at >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where[] = "created_at <= ?";
        $params[] = $filters['end_date'] . ' 23:59:59';
    }
    
    // Filter by entity
    if (!empty($filters['entity_type'])) {
        $where[] = "entity_type = ?";
        $params[] = $filters['entity_type'];
    }
    
    if (!empty($filters['entity_id'])) {
        $where[] = "entity_id = ?";
        $params[] = $filters['entity_id'];
    }
    
    // Search in description
    if (!empty($filters['search'])) {
        $where[] = "(description LIKE ? OR username LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT * FROM activity_logs
        $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get total count of activity logs matching filters
 * 
 * @param array $filters Same filters as getActivityLogs
 * @return int Total count
 */
function getActivityLogsCount($filters = []) {
    global $pdo;
    
    $where = [];
    $params = [];
    
    // Same filtering logic as getActivityLogs
    if (!empty($filters['user_id'])) {
        $where[] = "user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['action_type'])) {
        if (is_array($filters['action_type'])) {
            $placeholders = str_repeat('?,', count($filters['action_type']) - 1) . '?';
            $where[] = "action_type IN ($placeholders)";
            $params = array_merge($params, $filters['action_type']);
        } else {
            $where[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
    }
    
    if (!empty($filters['start_date'])) {
        $where[] = "created_at >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where[] = "created_at <= ?";
        $params[] = $filters['end_date'] . ' 23:59:59';
    }
    
    if (!empty($filters['entity_type'])) {
        $where[] = "entity_type = ?";
        $params[] = $filters['entity_type'];
    }
    
    if (!empty($filters['entity_id'])) {
        $where[] = "entity_id = ?";
        $params[] = $filters['entity_id'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(description LIKE ? OR username LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT COUNT(*) as total FROM activity_logs $whereClause";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['total'];
}

/**
 * Get user activity summary
 * 
 * @param int $userId User ID
 * @param int $days Number of days to look back
 * @return array Summary statistics
 */
function getUserActivitySummary($userId, $days = 30) {
    global $pdo;
    
    $startDate = date('Y-m-d', strtotime("-$days days"));
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_actions,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            action_type,
            COUNT(*) as action_count
        FROM activity_logs
        WHERE user_id = ? AND created_at >= ?
        GROUP BY action_type
        ORDER BY action_count DESC
    ");
    
    $stmt->execute([$userId, $startDate]);
    $actionBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalActions = array_sum(array_column($actionBreakdown, 'action_count'));
    $activeDays = $actionBreakdown[0]['active_days'] ?? 0;
    
    return [
        'total_actions' => $totalActions,
        'active_days' => $activeDays,
        'action_breakdown' => $actionBreakdown,
        'period_days' => $days
    ];
}

/**
 * Get recent activity across all users
 * 
 * @param int $limit Number of recent entries to retrieve
 * @return array Recent log entries
 */
function getRecentActivity($limit = 20) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM activity_logs
        ORDER BY created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get activity statistics for a date range
 * 
 * @param string|null $startDate Start date (Y-m-d format)
 * @param string|null $endDate End date (Y-m-d format)
 * @return array Statistics
 */
function getActivityStats($startDate = null, $endDate = null) {
    global $pdo;
    
    $where = [];
    $params = [];
    
    if ($startDate) {
        $where[] = "created_at >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $where[] = "created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Total logs
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs $whereClause");
    $stmt->execute($params);
    $totalLogs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Unique users
    $uniqueUsersWhere = $whereClause ? "$whereClause AND user_id IS NOT NULL" : "WHERE user_id IS NOT NULL";
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as total FROM activity_logs $uniqueUsersWhere");
    $stmt->execute($params);
    $uniqueUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Most common actions
    $stmt = $pdo->prepare("
        SELECT action_type, COUNT(*) as count
        FROM activity_logs
        $whereClause
        GROUP BY action_type
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute($params);
    $topActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most active users
    $topUsersWhere = $whereClause ? "$whereClause AND user_id IS NOT NULL" : "WHERE user_id IS NOT NULL";
    $stmt = $pdo->prepare("
        SELECT username, COUNT(*) as action_count
        FROM activity_logs
        $topUsersWhere
        GROUP BY username
        ORDER BY action_count DESC
        LIMIT 5
    ");
    $stmt->execute($params);
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'total_logs' => $totalLogs,
        'unique_users' => $uniqueUsers,
        'top_actions' => $topActions,
        'top_users' => $topUsers
    ];
}

/**
 * Clean up old activity logs
 * 
 * @param int $daysToKeep Number of days to retain logs
 * @return int Number of logs deleted
 */
function cleanupOldLogs($daysToKeep = 365) {
    global $pdo;
    
    $cutoffDate = date('Y-m-d', strtotime("-$daysToKeep days"));
    
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < ?");
    $stmt->execute([$cutoffDate]);
    
    return $stmt->rowCount();
}

/**
 * Export activity logs to CSV
 * 
 * @param array $filters Same filters as getActivityLogs
 * @return string CSV content
 */
function exportActivityLogsCSV($filters = []) {
    $logs = getActivityLogs($filters, 10000, 0); // Get up to 10,000 logs
    
    $csv = "Log ID,User ID,Username,Action Type,Entity Type,Entity ID,Description,IP Address,Created At\n";
    
    foreach ($logs as $log) {
        $csv .= sprintf(
            "%d,%s,%s,%s,%s,%s,\"%s\",%s,%s\n",
            $log['log_id'],
            $log['user_id'] ?? '',
            $log['username'] ?? '',
            $log['action_type'],
            $log['entity_type'] ?? '',
            $log['entity_id'] ?? '',
            str_replace('"', '""', $log['description']),
            $log['ip_address'] ?? '',
            $log['created_at']
        );
    }
    
    return $csv;
}
