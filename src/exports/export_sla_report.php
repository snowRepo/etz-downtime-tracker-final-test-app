<?php
// Suppress all noise so nothing contaminates the binary stream
error_reporting(0);
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../../config/config.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ── Parameters ────────────────────────────────────────────────────────────────
$companyId = $_GET['company_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// ── Period ────────────────────────────────────────────────────────────────────
$periodDays = (int) (new DateTime($startDate))->diff((new DateTime($endDate))->modify('+1 day'))->days;
$totalMinutes = $periodDays * 24 * 60;

// ── Helper: aggregate downtime for one company (mirrors sla_report.php) ───────
function companyRow(PDO $pdo, int $cid, string $startDate, string $endDate, int $totalMinutes): array
{
    $startDT = $startDate . ' 00:00:00';
    $endDT = $endDate . ' 23:59:59';

    // company name
    $s = $pdo->prepare("SELECT company_name FROM companies WHERE company_id = ?");
    $s->execute([$cid]);
    $companyName = $s->fetchColumn() ?: 'Unknown';

    // SLA target
    $s = $pdo->prepare("SELECT target_uptime FROM sla_targets WHERE company_id = ? LIMIT 1");
    $s->execute([$cid]);
    $slaTarget = (float) ($s->fetchColumn() ?: 99.99);

    // Get the "All" company ID so All-company incidents also count toward this company's SLA
    $allCoStmt = $pdo->query("SELECT company_id FROM companies WHERE LOWER(company_name) = 'all' LIMIT 1");
    $allCompanyId = $allCoStmt ? $allCoStmt->fetchColumn() : null;

    $companyFilter = $allCompanyId ? 'iac.company_id IN (?, ?)' : 'iac.company_id = ?';

    // incidents
    $stmt = $pdo->prepare("
        SELECT i.incident_id, i.status, i.created_at, i.resolved_at,
               s.service_name,
               COALESCE(di.actual_start_time, i.created_at) AS actual_start,
               di.actual_end_time,
               di.incident_id AS has_downtime
        FROM incidents i
        JOIN incident_affected_companies iac ON i.incident_id = iac.incident_id
        LEFT JOIN services s   ON i.service_id = s.service_id
        LEFT JOIN downtime_incidents di ON i.incident_id = di.incident_id
        WHERE $companyFilter
          AND (
              (i.created_at BETWEEN ? AND ?)
              OR (i.status = 'resolved' AND i.updated_at BETWEEN ? AND ?)
              OR (i.created_at <= ? AND (i.status = 'pending' OR i.updated_at >= ?))
          )
        GROUP BY i.incident_id
        ORDER BY i.created_at DESC
    ");

    $bindParams = $allCompanyId
        ? [$cid, $allCompanyId, $startDT, $endDT, $startDT, $endDT, $endDT, $startDT]
        : [$cid, $startDT, $endDT, $startDT, $endDT, $endDT, $startDT];

    $stmt->execute($bindParams);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $periodStart = new DateTime($startDate . ' 00:00:00');
    $periodEnd = new DateTime($endDate . ' 23:59:59');
    $totalDowntime = 0;
    $incidentCount = count($incidents);
    $services = [];

    $pendingCount = 0;

    foreach ($incidents as $inc) {
        if ($inc['status'] === 'pending') {
            $pendingCount++;
        }

        if (empty($inc['has_downtime']))
            continue;

        $iStart = new DateTime($inc['actual_start']);
        $iEnd = ($inc['status'] === 'resolved' && !empty($inc['resolved_at']))
            ? new DateTime($inc['resolved_at'])
            : new DateTime('now');

        $effStart = max($iStart, $periodStart);
        $effEnd = min($iEnd, $periodEnd);

        if ($effEnd > $effStart) {
            $diff = $effStart->diff($effEnd);
            $mins = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            $totalDowntime += $mins;
        }

        if (!empty($inc['service_name'])) {
            $services[$inc['service_name']] = true;
        }
    }

    // SLA: target minus downtime percentage (floor 0)
    $downtimePct = $totalMinutes > 0 ? ($totalDowntime / $totalMinutes) * 100 : 0;
    $uptimePct = max(0, $slaTarget - $downtimePct);

    return [
        'company' => $companyName,
        'sla_target' => $slaTarget,
        'total_minutes' => $totalMinutes,
        'downtime_mins' => $totalDowntime,
        'uptime_pct' => $uptimePct,
        'incident_count' => $incidentCount,
        'pending_count' => $pendingCount,
        'services' => implode(', ', array_keys($services)) ?: '—',
    ];
}

try {
    // ── Build rows ────────────────────────────────────────────────────────────
    if ($companyId) {
        $rows = [companyRow($pdo, (int) $companyId, $startDate, $endDate, $totalMinutes)];
    } else {
        $all = $pdo->query("SELECT company_id FROM companies WHERE company_name != 'All' ORDER BY company_name")->fetchAll(PDO::FETCH_COLUMN);
        $rows = [];
        foreach ($all as $cid) {
            $rows[] = companyRow($pdo, (int) $cid, $startDate, $endDate, $totalMinutes);
        }
    }

    // ── Spreadsheet ───────────────────────────────────────────────────────────
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet()->setTitle('SLA Report');
    $sheet = $spreadsheet->getActiveSheet();

    $titleLabel = $companyId ? ($rows[0]['company'] ?? 'Company') : 'All Companies';
    $spreadsheet->getProperties()
        ->setCreator('eTranzact Downtime Tracker')
        ->setTitle("SLA Uptime Report – $titleLabel – $startDate to $endDate");

    // ── Styles ────────────────────────────────────────────────────────────────
    $darkBlue = ['type' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']];
    $midBlue = ['type' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']];
    $altRow = ['type' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF4FB']];
    $border = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]];

    $row = 1;

    // ── Title ─────────────────────────────────────────────────────────────────
    $sheet->setCellValue('A1', 'SLA UPTIME REPORT – ' . strtoupper($titleLabel));
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => $darkBlue,
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(24);
    $row = 2;

    $sheet->setCellValue('A2', 'Period: ' . date('M j, Y', strtotime($startDate)) . '  –  ' . date('M j, Y', strtotime($endDate)) . '   |   Generated: ' . date('Y-m-d H:i'));
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
        'fill' => ['type' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $row = 4; // blank spacer at row 3

    // ── Column headers ────────────────────────────────────────────────────────
    // A Company | B Services Affected | C SLA Target % | D Period (min)
    // E Downtime (min) | F Uptime % | G No. of Incidents | H Pending Incidents
    $headers = ['Company', 'Services Affected', 'SLA Target %', 'Period (min)', 'Downtime (min)', 'Uptime %', 'No. of Incidents', 'Pending Incidents'];
    $sheet->fromArray($headers, null, 'A' . $row);
    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
        'fill' => ['type' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => $border,
    ]);
    $sheet->getRowDimension($row)->setRowHeight(18);
    $row++;

    // ── Data rows ─────────────────────────────────────────────────────────────
    foreach ($rows as $i => $r) {
        $noDowntime = ($r['downtime_mins'] === 0);

        $sheet->fromArray([
            $r['company'],
            $r['services'],
            number_format($r['sla_target'], 2),
            $r['total_minutes'],
            $noDowntime ? '' : $r['downtime_mins'],
            number_format($r['uptime_pct'], 4),
            $r['incident_count'] > 0 ? $r['incident_count'] : '',
            $r['pending_count'] > 0 ? $r['pending_count'] . ' open' : '',
        ], null, 'A' . $row);

        $rowStyle = [
            'borders' => $border,
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        // Alternate row tint
        if ($i % 2 === 1) {
            $rowStyle['fill'] = $altRow;
        }
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($rowStyle);

        // Highlight rows with open/pending incidents in a light amber
        if ($r['pending_count'] > 0) {
            $sheet->getStyle('H' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'C55A11']],
            ]);
        }

        // Column A — left; B–H — centre
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
    }


    // ── Summary section ───────────────────────────────────────────────────────
    $totalCompanies = count($rows);
    $grandDowntime = array_sum(array_column($rows, 'downtime_mins'));
    $grandIncidents = array_sum(array_column($rows, 'incident_count'));
    $companiesWithOpen = count(array_filter($rows, fn($r) => $r['pending_count'] > 0));
    $grandDowntimePct = $totalMinutes > 0 ? ($grandDowntime / $totalMinutes) * 100 : 0;
    $grandUptime = max(0, 99.99 - $grandDowntimePct);

    $row += 2; // spacer

    // Summary header
    $sheet->setCellValue('A' . $row, 'SUMMARY');
    $sheet->mergeCells('A' . $row . ':H' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']],
        'fill' => ['type' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;

    // Summary rows: label (cols A–D merged) | value (cols E–H merged)
    $summaryItems = [
        ['Total companies included', $totalCompanies],
        ['Reporting period (minutes)', $totalMinutes],
        ['Total downtime across all companies (min)', $grandDowntime > 0 ? $grandDowntime : 'None'],
        ['Aggregate uptime %', number_format($grandUptime, 4)],
        ['Total incidents', $grandIncidents > 0 ? $grandIncidents : 'None'],
        ['Companies with open/pending incidents', $companiesWithOpen > 0 ? $companiesWithOpen : 'None'],
    ];

    $labelStyle = [
        'font' => ['bold' => false, 'color' => ['rgb' => '000000']],
        'fill' => ['type' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
        'borders' => $border,
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    $valueStyle = [
        'borders' => $border,
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];

    foreach ($summaryItems as [$label, $value]) {
        $sheet->setCellValue('A' . $row, $label);
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($labelStyle);

        $sheet->setCellValue('E' . $row, $value);
        $sheet->mergeCells('E' . $row . ':H' . $row);
        $sheet->getStyle('E' . $row . ':H' . $row)->applyFromArray($valueStyle);

        // Highlight pending count in orange if > 0
        if ($label === 'Companies with open/pending incidents' && $companiesWithOpen > 0) {
            $sheet->getStyle('E' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'C55A11']],
            ]);
        }

        $row++;
    }

    // ── Auto-size all columns ─────────────────────────────────────────────────
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }


    $sheet->freezePane('A5'); // freeze above first data row

    // ── Output ────────────────────────────────────────────────────────────────
    ob_end_clean();

    $safeName = $companyId
        ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $rows[0]['company'] ?? 'Company') . '_'
        : 'All_Companies_';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="SLA_Report_' . $safeName . $startDate . '_to_' . $endDate . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    (new Xlsx($spreadsheet))->save('php://output');
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    die('Error generating SLA report: ' . $e->getMessage());
}