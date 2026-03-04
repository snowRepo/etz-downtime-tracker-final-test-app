<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/includes/auth.php';

header('Content-Type: application/json');

// Ensure user is authenticated
requireLogin();

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Incident ID is required']);
    exit;
}

$incidentId = intval($_GET['id']);

try {
    // Fetch incident data with all related information
    $stmt = $pdo->prepare("
        SELECT 
            i.incident_id,
            i.service_id,
            i.component_id,
            i.incident_type_id,
            i.description,
            i.impact_level,
            i.priority,
            i.actual_start_time,
            i.resolved_at,
            i.status,
            i.attachment_path,
            s.service_name
        FROM incidents i
        JOIN services s ON i.service_id = s.service_id
        WHERE i.incident_id = ?
    ");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        echo json_encode(['error' => 'Incident not found']);
        exit;
    }

    // Fetch affected companies
    $companiesStmt = $pdo->prepare("
        SELECT company_id 
        FROM incident_affected_companies 
        WHERE incident_id = ?
    ");
    $companiesStmt->execute([$incidentId]);
    $affectedCompanies = $companiesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Convert to integers
    $incident['affected_companies'] = array_map('intval', $affectedCompanies);

    // Fetch involved officers
    $officersStmt = $pdo->prepare("
        SELECT user_id, external_name 
        FROM incident_officers 
        WHERE incident_id = ?
    ");
    $officersStmt->execute([$incidentId]);
    $officers = $officersStmt->fetchAll(PDO::FETCH_ASSOC);

    $incident['involved_users'] = [];
    $externalNames = [];

    foreach ($officers as $officer) {
        if (!empty($officer['user_id'])) {
            $incident['involved_users'][] = intval($officer['user_id']);
        }
        if (!empty($officer['external_name'])) {
            $externalNames[] = $officer['external_name'];
        }
    }
    $incident['external_names'] = implode(', ', $externalNames);

    // Fetch attachments if requested
    if (isset($_GET['include_attachments']) && $_GET['include_attachments'] == '1') {
        error_log("Fetching attachments for incident ID: " . $incidentId);

        $allAttachments = [];

        // First, get attachments from incident_attachments table
        $attachmentsStmt = $pdo->prepare("
            SELECT attachment_id, file_path, file_name, file_type, file_size, uploaded_at
            FROM incident_attachments
            WHERE incident_id = ?
            ORDER BY uploaded_at ASC
        ");
        $attachmentsStmt->execute([$incidentId]);
        $newAttachments = $attachmentsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Add new attachments to the array
        foreach ($newAttachments as $attachment) {
            $allAttachments[] = $attachment;
        }

        // Then, check for legacy attachment_path
        if (!empty($incident['attachment_path'])) {
            // Check if this file path already exists in new attachments
            $filePathExists = false;
            foreach ($allAttachments as $existing) {
                if ($existing['file_path'] === $incident['attachment_path']) {
                    $filePathExists = true;
                    break;
                }
            }

            // If not already in new attachments, add it
            if (!$filePathExists) {
                $allAttachments[] = [
                    'attachment_id' => null,
                    'file_path' => $incident['attachment_path'],
                    'file_name' => basename($incident['attachment_path']),
                    'file_type' => null,
                    'file_size' => null,
                    'uploaded_at' => null
                ];
            }
        }

        $incident['attachments'] = $allAttachments;
        error_log("Attachments found: " . count($incident['attachments']));
        error_log("Attachments data: " . json_encode($incident['attachments']));
    } else {
        error_log("include_attachments parameter: " . (isset($_GET['include_attachments']) ? $_GET['include_attachments'] : 'not set'));
    }

    echo json_encode($incident);

} catch (PDOException $e) {
    error_log("Get incident error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Get incident general error: " . $e->getMessage());
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
