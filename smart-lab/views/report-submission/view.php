<?php
$authMethod = Auth::getCurrentAuthMethod();
$approvedMethods = ['biometric', 'rfid', 'qr', 'code'];
$isApproved = in_array($authMethod, $approvedMethods, true);
$approvalText = $isApproved ? 'DATA SHEET APPROVED' : 'DATA SHEET PENDING APPROVAL';
$signatureHash = generateSignature(Auth::id(), $report['id']);
$qrText = 'Practical approved';
$labNumber = $report['lab_code'] ?? ($report['lab_name'] ?? 'N/A');
$submittedAt = !empty($report['submitted_at']) ? date('M j, Y h:i A', strtotime($report['submitted_at'])) : 'Not recorded';
?>

<div class="card">
    <div class="card-header">
        <div class="card-header-content">
            <span>📄 Lab Data Sheet</span>
            <div class="card-actions">
                <button type="button" class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Data Sheet
                </button>
                <button type="button" class="btn btn-primary" onclick="downloadDataSheetPdf()">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <a href="<?= APP_URL ?>/report-submission" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Submissions
                </a>
            </div>
        </div>
    </div>

    <div class="card-body report-sheet">
        <section class="datasheet-page">
            <div class="sheet-header">
                <img src="<?= APP_URL ?>/jkuatlogo.jpg" alt="JKUAT Logo" class="sheet-logo" />
                <div class="sheet-title-block">
                    <div class="sheet-university">Jomo Kenyatta University of Agriculture and Technology</div>
                    <div class="sheet-name">JKUAT SmartLab Laboratory Data Sheet</div>
                    <div class="sheet-subtitle">Practical Data, Readings, Calculations and Approval</div>
                </div>
            </div>

            <div class="sheet-section sheet-border">
                <h2>Practical Details</h2>
                <div class="sheet-grid">
                    <div><strong>Student Name</strong><br><?= htmlspecialchars($report['student_name'] ?? ($_SESSION['user_name'] ?? 'N/A')) ?></div>
                    <div><strong>Registration No.</strong><br><?= htmlspecialchars($report['reg_number'] ?? 'N/A') ?></div>
                    <div><strong>Lab / Practical</strong><br><?= htmlspecialchars($report['practical_title'] ?? 'N/A') ?></div>
                    <div><strong>Lab Number</strong><br><?= htmlspecialchars($labNumber) ?></div>
                    <div><strong>Laboratory</strong><br><?= htmlspecialchars($report['lab_name'] ?? 'N/A') ?></div>
                    <div><strong>Submitted</strong><br><?= htmlspecialchars($submittedAt) ?></div>
                </div>
            </div>

            <div class="sheet-section sheet-border">
                <h2>Experiment Readings & Notes</h2>
                <div class="sheet-section-text">
                    <?= nl2br(htmlspecialchars($report['submission_notes'] ?? 'No submission notes available.')) ?>
                </div>
            </div>

            <div class="sheet-footer-grid">
                <div class="sheet-approval">
                    <div class="approval-label">Authentication Method</div>
                    <div class="approval-value"><?= strtoupper(htmlspecialchars($authMethod)) ?></div>
                    <?php if ($isApproved): ?>
                        <div class="approval-approved"><?= $approvalText ?></div>
                    <?php else: ?>
                        <div class="approval-pending"><?= $approvalText ?></div>
                    <?php endif; ?>
                </div>

                <div class="sheet-qr">
                    <div id="datasheet-qr"></div>
                    <div class="qr-note">Scan to verify practical approval</div>
                </div>

                <div class="sheet-signature">
                    <div class="signature-label">Digital Signature</div>
                    <div class="signature-value"><?= htmlspecialchars($signatureHash) ?></div>
                    <div class="signature-by">Signed by <?= htmlspecialchars($report['student_name'] ?? Auth::userName() ?? 'Student') ?></div>
                </div>
            </div>
        </section>

        <?php for ($page = 2; $page <= 4; $page++): ?>
            <section class="datasheet-page blank-page">
                <div class="blank-header">Calculations / Inferences — Page <?= $page - 1 ?></div>
                <div class="blank-lines">
                    <?php for ($line = 0; $line < 20; $line++): ?>
                        <div class="blank-line"></div>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endfor; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const qrContainer = document.getElementById('datasheet-qr');
        if (!qrContainer) return;

        new QRCode(qrContainer, {
            text: <?= json_encode($qrText) ?>,
            width: 180,
            height: 180,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    });

    async function downloadDataSheetPdf() {
        if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
            alert('PDF download is unavailable. Required libraries are missing.');
            return;
        }

        const pages = Array.from(document.querySelectorAll('.datasheet-page'));
        const pdf = new window.jspdf.jsPDF('portrait', 'pt', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();

        for (let index = 0; index < pages.length; index++) {
            const page = pages[index];
            const canvas = await html2canvas(page, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff'
            });

            const imgData = canvas.toDataURL('image/png');
            const imgProps = pdf.getImageProperties(imgData);
            const ratio = Math.min(pageWidth / imgProps.width, pageHeight / imgProps.height);
            const imgWidth = imgProps.width * ratio;
            const imgHeight = imgProps.height * ratio;
            const marginX = (pageWidth - imgWidth) / 2;
            const marginY = 20;

            if (index > 0) {
                pdf.addPage();
            }

            pdf.addImage(imgData, 'PNG', marginX, marginY, imgWidth, imgHeight);
        }

        pdf.save('lab_data_sheet_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', ($report['practical_title'] ?? 'practical')) ?>.pdf');
    }
</script>

<style>
.card-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.report-sheet {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.datasheet-page {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
    position: relative;
}

.sheet-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.sheet-logo {
    width: 120px;
    max-height: 120px;
    object-fit: contain;
    border-radius: 16px;
    background: #ffffff;
    padding: 1rem;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
}

.sheet-title-block {
    flex: 1;
}

.sheet-university {
    font-size: 0.95rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.sheet-name {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.35rem;
}

.sheet-subtitle {
    font-size: 1rem;
    color: #4b5563;
}

.sheet-section {
    margin-bottom: 1.5rem;
}

.sheet-border {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
}

.sheet-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.sheet-grid > div {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 12px;
    min-height: 80px;
}

.sheet-section-text {
    white-space: pre-wrap;
    line-height: 1.65;
    color: #1f2937;
}

.sheet-footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1.5fr;
    gap: 1rem;
    align-items: start;
}

.sheet-approval, .sheet-signature {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1rem;
    background: #f8fafc;
}

.approval-label, .signature-label {
    font-size: 0.9rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: 0.5rem;
}

.approval-value, .signature-value {
    font-size: 1rem;
    color: #111827;
    word-break: break-all;
    margin-bottom: 0.75rem;
}

.approval-approved {
    font-size: 1rem;
    font-weight: 800;
    color: #047857;
}

.approval-pending {
    font-size: 1rem;
    font-weight: 800;
    color: #b91c1c;
}

.sheet-qr {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 1rem;
    background: #ffffff;
}

.qr-note {
    margin-top: 0.75rem;
    font-size: 0.9rem;
    color: #4b5563;
    text-align: center;
}

.signature-by {
    color: #475569;
    font-size: 0.92rem;
}

.blank-page {
    min-height: 920px;
    display: flex;
    flex-direction: column;
}

.blank-header {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.blank-lines {
    flex: 1;
    display: grid;
    gap: 0.75rem;
}

.blank-line {
    border-bottom: 1px dashed #d1d5db;
    height: 1.2rem;
}

@media print {
    .card, .card-body, .report-sheet {
        box-shadow: none;
        border: none;
        background: #fff;
    }

    .datasheet-page {
        box-shadow: none;
        border: none;
        page-break-after: always;
        page-break-inside: avoid;
    }
}
</style>
