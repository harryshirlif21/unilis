<!-- Report Submissions Modal -->
<div id="reportSubmissionsModal" class="modal-overlay hidden">
    <div class="modal-backdrop" onclick="closeReportSubmissionsModal()"></div>
    <div class="modal-card" style="max-width:750px;">
        <div class="modal-card-header">
            <div>
                <h2>📋 Report Submissions</h2>
                <p id="submissions-practical-title">Loading...</p>
            </div>
            <button class="modal-close" onclick="closeReportSubmissionsModal()">&times;</button>
        </div>
        <div class="modal-body" id="submissions-modal-body" style="padding:1.5rem 1.6rem;">
            <div style="text-align:center;padding:2rem;">
                <div class="spinner"></div>
                <p style="color:#64748b;margin-top:1rem;">Loading submission data...</p>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:center;padding:1rem 1.6rem 1.4rem;">
            <button class="btn btn-secondary" onclick="closeReportSubmissionsModal()">Close</button>
        </div>
    </div>
</div>

<script>
function openReportSubmissionsModal(practicalId, practicalTitle) {
    const modal = document.getElementById('reportSubmissionsModal');
    const body = document.getElementById('submissions-modal-body');
    const title = document.getElementById('submissions-practical-title');
    
    title.textContent = 'Practical: ' + practicalTitle;
    modal.classList.remove('hidden');
    body.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="spinner"></div><p style="color:#64748b;margin-top:1rem;">Loading submission data...</p></div>';

    fetch('<?= APP_URL ?>/practicals/submission-stats/' + practicalId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                body.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                return;
            }
            
            let html = '';

            // Stats cards
            html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">';
            html += '<div style="background:#f1f5f9;padding:16px;border-radius:10px;text-align:center;">';
            html += '<div style="font-size:28px;font-weight:700;color:#1e3a5f;">' + data.total_students + '</div>';
            html += '<div style="font-size:13px;color:#64748b;margin-top:4px;">Enrolled Students</div></div>';

            html += '<div style="background:#dcfce7;padding:16px;border-radius:10px;text-align:center;">';
            html += '<div style="font-size:28px;font-weight:700;color:#166534;">' + data.submitted + '</div>';
            html += '<div style="font-size:13px;color:#64748b;margin-top:4px;">Reports Submitted</div></div>';

            const notSubmitted = data.total_students - data.submitted;
            const notSubColor = notSubmitted > 0 ? '#fef3c7' : '#f1f5f9';
            const notSubTextColor = notSubmitted > 0 ? '#92400e' : '#64748b';
            html += '<div style="background:' + notSubColor + ';padding:16px;border-radius:10px;text-align:center;">';
            html += '<div style="font-size:28px;font-weight:700;color:' + notSubTextColor + ';">' + notSubmitted + '</div>';
            html += '<div style="font-size:13px;color:#64748b;margin-top:4px;">Not Submitted</div></div>';
            html += '</div>';

            // Student list
            if (data.students && data.students.length > 0) {
                html += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                html += '<thead><tr style="background:#f8fafc;">';
                html += '<th style="padding:8px 12px;border:1px solid #e2e8f0;text-align:left;">Student</th>';
                html += '<th style="padding:8px 12px;border:1px solid #e2e8f0;text-align:left;">Reg Number</th>';
                html += '<th style="padding:8px 12px;border:1px solid #e2e8f0;text-align:center;">Report Status</th>';
                html += '<th style="padding:8px 12px;border:1px solid #e2e8f0;text-align:center;">Submitted At</th>';

                // Only show grade column for lecturers
                <?php if (Auth::role() === 'lecturer'): ?>
                html += '<th style="padding:8px 12px;border:1px solid #e2e8f0;text-align:center;">Grade</th>';
                <?php endif; ?>

                html += '</tr></thead><tbody>';

                data.students.forEach(s => {
                    const statusBadge = s.status === 'submitted'
                        ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-size:11px;">✅ Submitted</span>'
                        : '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px;">⏳ Pending</span>';

                    html += '<tr style="border-bottom:1px solid #f1f5f9;">';
                    html += '<td style="padding:8px 12px;">' + s.full_name + '</td>';
                    html += '<td style="padding:8px 12px;">' + s.reg_number + '</td>';
                    html += '<td style="padding:8px 12px;text-align:center;">' + statusBadge + '</td>';
                    html += '<td style="padding:8px 12px;text-align:center;font-size:12px;color:#64748b;">' + (s.submitted_at || '-') + '</td>';

                    <?php if (Auth::role() === 'lecturer'): ?>
                    // Grade column for lecturers
                    if (s.status === 'submitted') {
                        const grade = s.grade || '—';
                        html += '<td style="padding:8px 12px;text-align:center;"><a href="<?= APP_URL ?>/practicals/grade/' + s.report_id + '" class="btn btn-sm" style="background:#2563eb;color:white;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:11px;">' + (s.graded ? grade : 'Grade') + '</a></td>';
                    } else {
                        html += '<td style="padding:8px 12px;text-align:center;color:#94a3b8;">—</td>';
                    }
                    <?php endif; ?>

                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<div style="text-align:center;padding:2rem;color:#94a3b8;">No students enrolled for this practical.</div>';
            }

            // Admin/Technician note
            <?php if (Auth::role() !== 'lecturer'): ?>
            html += '<div style="margin-top:16px;padding:12px;background:#f0f9ff;border-radius:8px;font-size:13px;color:#1e40af;border:1px solid #bae6fd;">';
            html += '<strong>🔧 Lab Administration</strong> — This view is for tracking submissions. Grading is handled by the lecturer.';
            html += '</div>';
            <?php endif; ?>

            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger">Error loading data: ' + err.message + '</div>';
        });
}

function closeReportSubmissionsModal() {
    document.getElementById('reportSubmissionsModal').classList.add('hidden');
}
</script>

<style>
.spinner {
    width: 36px; height: 36px;
    border: 3px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    margin: 0 auto;
}
@keyframes spin { to { transform: rotate(360deg); } }
.modal-overlay.hidden { display: none !important; }
.modal-overlay {
    position: fixed; inset: 0; z-index: 1100;
    display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal-backdrop {
    position: absolute; inset: 0;
    background: rgba(0,0,0,.52); backdrop-filter: blur(2px);
}
.modal-card {
    position: relative; z-index: 1; width: min(100%, 750px);
    background: #fff; border-radius: 18px;
    box-shadow: 0 28px 72px rgba(0,0,0,.22);
    max-height: 90vh; overflow-y: auto;
}
.modal-card-header {
    padding: 1.4rem 1.6rem .8rem;
    display: flex; justify-content: space-between; gap: 1rem;
    align-items: flex-start; border-bottom: 1px solid #f0f0f0;
}
.modal-card-header h2 { margin: 0 0 .2rem; font-size: 1.2rem; font-weight: 700; color: #0f172a; }
.modal-card-header p { margin: 0; color: #64748b; font-size: .9rem; }
.modal-close { border: none; background: transparent; font-size: 1.4rem; cursor: pointer; color: #94a3b8; padding: .2rem .4rem; border-radius: 6px; }
.modal-footer { padding: .85rem 1.6rem 1.25rem; border-top: 1px solid #f0f0f0; }
.btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: all .15s; }
.btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.btn-secondary:hover { background: #e2e8f0; }
.alert-danger { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; }
</style>