<?php
/**
 * API Endpoint: Get Incident Templates
 * Returns active templates optionally filtered by service
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/includes/auth.php';

// Require authentication
requireLogin();

header('Content-Type: application/json');

try {
    $serviceId = $_GET['service_id'] ?? null;

    // Build query based on service filter
    if ($serviceId && $serviceId !== '') {
        // Get templates specific to this service OR generic templates (service_id IS NULL)
        $stmt = $pdo->prepare("
            SELECT 
                template_id,
                template_name,
                service_id,
                component_id,
                incident_type_id,
                impact_level,
                description,
                root_cause
            FROM incident_templates
            WHERE is_active = 1 
              AND (service_id IS NULL OR service_id = ?)
            ORDER BY usage_count DESC, template_name ASC
        ");
        $stmt->execute([$serviceId]);
    } else {
        // Get all active templates
        $stmt = $pdo->prepare("
            SELECT 
                template_id,
                template_name,
                service_id,
                component_id,
                incident_type_id,
                impact_level,
                description,
                root_cause
            FROM incident_templates
            WHERE is_active = 1
            ORDER BY usage_count DESC, template_name ASC
        ");
        $stmt->execute();
    }

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
