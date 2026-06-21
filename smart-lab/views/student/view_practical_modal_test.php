<?php
/**
 * Minimal test modal — takes a practical directly without QR / RFID / code / biometric.
 * Include from view_practical.php by uncommenting the require line at the bottom.
 */
?>
<div id="takePracticalTestModal" class="modal-overlay hidden">
    <div class="modal-backdrop" onclick="closeTestModal()"></div>
    <div class="modal-card" style="max-width:420px;">
        <div class="modal-card-header">
            <h2>Take Practical (Test Mode)</h2>
            <button class="modal-close" onclick="closeTestModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:2rem 1.6rem;">
            <p style="margin-bottom:1.5rem;color:#475569;">
                Click below to immediately start this practical.<br>
                <small style="color:#94a3b8;">(Skips QR / RFID / code / biometric verification)</small>
            </p>
            <button id="testTakeBtn" class="btn btn-accent btn-lg" onclick="testTakePractical('<?= $practical['id'] ?>')">
                ▶ Start Practical Now
            </button>
            <div id="testTakeStatus" class="status-panel" style="margin-top:1rem;"></div>
        </div>
        <div class="modal-footer" style="justify-content:center;">
            <button class="btn btn-secondary" onclick="closeTestModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
function openTestTakeModal() {
    document.getElementById('takePracticalTestModal').classList.remove('hidden');
    document.getElementById('testTakeStatus').className = 'status-panel';
    document.getElementById('testTakeStatus').textContent = '';
}

function closeTestModal() {
    document.getElementById('takePracticalTestModal').classList.add('hidden');
}

function testTakePractical(practicalId) {
    const btn = document.getElementById('testTakeBtn');
    const status = document.getElementById('testTakeStatus');
    btn.disabled = true;
    btn.textContent = 'Starting…';
    status.className = 'status-panel';
    status.textContent = 'Creating report and redirecting…';

    // Direct navigation — this calls start-practical/{id} → startPractical()
    // Adjust the URL to match your APP_URL prefix
    const baseUrl = '<?= APP_URL ?>';
    window.location.href = baseUrl + '/start-practical/' + practicalId;
}
</script>

<style>
.btn-lg {
    padding: 1rem 2rem;
    font-size: 1.05rem;
    border-radius: 12px;
}
.btn-accent {
    background: #2563eb;
    color: #fff;
    border: none;
    cursor: pointer;
}
.btn-accent:hover:not(:disabled) { background: #1d4ed8; }
.btn-accent:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
</final_file_content>

IMPORTANT: For any future changes to this file, use the final_file_content shown above as your reference. This content reflects the current state of the file, including any auto-formatting (e.g., if you used single quotes but the formatter converted them to double quotes). Always base your SEARCH/REPLACE operations on this final version to ensure accuracy.
</write_to_file>