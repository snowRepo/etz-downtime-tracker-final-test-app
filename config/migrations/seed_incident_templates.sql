-- Incident Templates seed (v2 – 4 comprehensive templates)
-- Service: Mobile Money (service_id=1)
-- Components: MTN=14, Telecel=15, AT=16
-- Incident Types: Credit=13, Debit=14, Double Deduction=15, Callback=16
-- Admin user: created_by=9

INSERT INTO incident_templates
    (template_name, service_id, component_id, incident_type_id, impact_level, description, root_cause, is_active, usage_count, created_by)
VALUES

-- ── Template 1: MTN Credit Failure ────────────────────────────────────────────
(
    'MTN Mobile Money – Credit Failure',
    1, 14, 13, 'high',
    'Customers on the MTN network are reporting failed credit transactions. Funds are being deducted from the sending account but are not credited to the recipient. Affected customers receive a failure notification or no notification at all, while the transaction shows a pending or failed status on the platform.',
    'Intermittent connectivity failure between the eTranzact switch and the MTN MoMo credit API endpoint. Possible causes include gateway timeout, malformed response from the MTN side, or a transaction routing misconfiguration following a recent platform update.',
    1, 0, 9
),

-- ── Template 2: Telecel Debit Failure ─────────────────────────────────────────
(
    'Telecel Mobile Money – Debit Failure',
    1, 15, 14, 'high',
    'Customers on the Telecel network are experiencing failed debit transactions. Payments and withdrawals are not being processed; customers receive error responses when initiating a debit and funds remain in their wallets. Merchant payments and bill payments on Telecel are equally affected.',
    'Debit request timeout due to Telecel MoMo API instability or rejection of debit authorisation requests. Suspected upstream issue with Telecel\'s payment processing infrastructure or rate-limiting on their API gateway.',
    1, 0, 9
),

-- ── Template 3: AirtelTigo Double Deduction ───────────────────────────────────
(
    'AirtelTigo Mobile Money – Double Deduction',
    1, 16, 15, 'critical',
    'Customers on the AirtelTigo (AT) network are reporting instances of double deduction – their accounts are debited twice for a single transaction. The recipient receives only one credit while the sender is charged twice. This is causing customer complaints, failed reconciliation, and financial loss. Immediate investigation and reversal of duplicate charges is required.',
    'Duplicate transaction processing caused by a retry storm triggered when AirtelTigo\'s API returned an ambiguous timeout response instead of a definitive success or failure code. The eTranzact platform retried the transaction without detecting that the first attempt had already been processed by AT.',
    1, 0, 9
),

-- ── Template 4: All Networks – Callback Failure ───────────────────────────────
(
    'All Networks – Callback / Notification Failure',
    1, NULL, 16, 'medium',
    'Mobile Money transaction callbacks and SMS/push notifications are failing across all networks (MTN, Telecel, AirtelTigo). Transactions are completing successfully on the backend but customers are not receiving confirmation messages. Third-party merchants and integrators are not receiving webhook callbacks, causing their systems to treat completed transactions as pending or failed.',
    'Callback delivery failure traced to an unresponsive notification service or misconfigured webhook delivery queue. The issue may stem from a message broker outage, an expired SSL certificate on the callback endpoint, or a recent configuration change that broke the notification dispatch pipeline.',
    1, 0, 9
);
