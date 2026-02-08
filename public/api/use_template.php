<?php
/**
 * API Endpoint: Record Template Usage
 * Increments usage count when template is used
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/includes/auth.php';

// Require authentication
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $templateId = $data['template_id'] ?? null;
    
    if (!$templateId) {
        throw new Exception('Template ID is required');
    }
    
    // Increment usage count
    $stmt = $pdo->prepare("
        UPDATE incident_templates 
        SET usage_count = usage_count + 1 
        WHERE template_id = ?
    ");
    $stmt->execute([$templateId]);
    
    // Log activity
    $stmt = $pdo->prepare("SELECT template_name FROM incident_templates WHERE template_id = ?");
    $stmt->execute([$templateId]);
    $templateName = $stmt->fetchColumn();
    
    logActivity($_SESSION['user_id'], 'used_template', "Used incident template: $templateName");
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
