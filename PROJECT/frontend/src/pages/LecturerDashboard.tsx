// pages/LecturerDashboard.tsx
import { useState, useEffect } from "react";
import { api, Submission, SubmissionDetail, LecturerStats } from "../api/client";
import { useAuth } from "../App";
import { StatusBadge, PipelineSteps, ScoreRing, EmptyState, RelTime } from "../components/shared";

type LecturerView = "overview" | "submissions" | "review";
type FilterStatus = "ALL" | "COMPLETED" | "APPROVED" | "FAILED";

export function LecturerDashboard() {
  const { user, logout } = useAuth();
  const [view, setView] = useState<LecturerView>("overview");
  const [stats, setStats] = useState<LecturerStats | null>(null);
  const [submissions, setSubmissions] = useState<Submission[]>([]);
  const [filter, setFilter] = useState<FilterStatus>("ALL");
  const [selected, setSelected] = useState<SubmissionDetail | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    api.lecturer.stats().then(setStats).catch(() => {});
    loadSubmissions();
  }, []);

  const loadSubmissions = async (f?: FilterStatus) => {
    const status = (f ?? filter) === "ALL" ? undefined : (f ?? filter);
    const data = await api.lecturer.submissions(status).catch(() => []);
    setSubmissions(data);
  };

  const changeFilter = (f: FilterStatus) => {
    setFilter(f);
    loadSubmissions(f);
  };

  const openReview = async (id: string) => {
    setLoading(true);
    try {
      const detail = await api.lecturer.getSubmission(id);
      setSelected(detail);
      setView("review");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="app-root">
      <LecturerSidebar user={user!} onLogout={logout} activeView={view} setView={setView} />
      <main className="main-content">
        {view === "overview" && stats && (
          <OverviewPage stats={stats} submissions={submissions} onReview={openReview} />
        )}
        {view === "submissions" && (
          <SubmissionsPage
            submissions={submissions}
            filter={filter}
            onFilter={changeFilter}
            onReview={openReview}
            loading={loading}
          />
        )}
        {view === "review" && selected && (
          <ReviewPage
            detail={selected}
            onBack={() => { setView("submissions"); loadSubmissions(); }}
            onApproved={() => { loadSubmissions(); api.lecturer.stats().then(setStats).catch(() => {}); }}
          />
        )}
      </main>
    </div>
  );
}

// ─── Sidebar ──────────────────────────────────────────────────────────────────
function LecturerSidebar({ user, onLogout, activeView, setView }: {
  user: any; onLogout: () => void;
  activeView: LecturerView; setView: (v: LecturerView) => void;
}) {
  return (
    <aside className="sidebar">
      <div className="sidebar-logo">
        <svg width="32" height="32" viewBox="0 0 48 48" fill="none">
          <rect x="4" y="4" width="40" height="40" rx="12" fill="#0d2137" stroke="#1a6b8a" strokeWidth="1.5"/>
          <path d="M24 12 L24 36 M12 24 L36 24" stroke="#4dd4f0" strokeWidth="2.5" strokeLinecap="round"/>
          <circle cx="24" cy="24" r="6" fill="none" stroke="#4dd4f0" strokeWidth="1.5"/>
          <circle cx="24" cy="24" r="2" fill="#4dd4f0"/>
        </svg>
        <span className="sidebar-brand">MedScore AI</span>
      </div>

      <div className="sidebar-section-label">Lecturer Portal</div>
      <nav className="sidebar-nav">
        <button className={`nav-item ${activeView === "overview" ? "nav-active" : ""}`} onClick={() => setView("overview")}>
          <span className="nav-icon">⬡</span> Overview
        </button>
        <button className={`nav-item ${activeView === "submissions" ? "nav-active" : ""}`} onClick={() => setView("submissions")}>
          <span className="nav-icon">◫</span> Submissions
        </button>
      </nav>

      <div className="sidebar-footer">
        <div className="user-chip">
          <div className="user-avatar lecturer-avatar">{user.name?.[0] ?? "L"}</div>
          <div className="user-info">
            <span className="user-name">{user.name}</span>
            <span className="user-role lecturer-role">Lecturer</span>
          </div>
        </div>
        <button className="logout-btn" onClick={onLogout}>Sign Out</button>
      </div>
    </aside>
  );
}

// ─── Overview ─────────────────────────────────────────────────────────────────
function OverviewPage({ stats, submissions, onReview }: {
  stats: LecturerStats; submissions: Submission[]; onReview: (id: string) => void;
}) {
  const pending = submissions.filter(s => s.status === "COMPLETED").slice(0, 5);

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Dashboard Overview</h1>
          <p className="page-sub">Medical AI Scoring System — Lecturer View</p>
        </div>
      </div>

      <div className="stats-grid">
        <StatCard label="Total Submissions" value={stats.total_submissions} icon="📥" color="#4dd4f0" />
        <StatCard label="Pending Review" value={stats.pending_review} icon="⏳" color="#f0c44d" urgent={stats.pending_review > 0} />
        <StatCard label="Approved" value={stats.approved} icon="✓" color="#4df09a" />
        <StatCard label="Avg Score" value={`${stats.average_score}%`} icon="◎" color="#c44df0" />
        <StatCard label="Students" value={stats.total_students} icon="👤" color="#f07a4d" />
      </div>

      {pending.length > 0 && (
        <div className="section">
          <h2 className="section-title">Awaiting Your Review</h2>
          <div className="pending-list">
            {pending.map(sub => (
              <div key={sub.id} className="pending-row" onClick={() => onReview(sub.id)}>
                <div className="pending-id">#{sub.id.slice(0, 8).toUpperCase()}</div>
                <div className="pending-student">{(sub as any).student_name ?? "Student"}</div>
                <StatusBadge status={sub.status} />
                {sub.ai_score != null && <ScoreRing score={sub.ai_score} size={52} />}
                <RelTime iso={sub.created_at} />
                <button className="btn-sm">Review →</button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, icon, color, urgent }: {
  label: string; value: string | number; icon: string; color: string; urgent?: boolean;
}) {
  return (
    <div className={`stat-card ${urgent ? "stat-urgent" : ""}`} style={{ "--accent": color } as any}>
      <div className="stat-icon">{icon}</div>
      <div className="stat-value">{value}</div>
      <div className="stat-label">{label}</div>
    </div>
  );
}

// ─── Submissions List ─────────────────────────────────────────────────────────
function SubmissionsPage({ submissions, filter, onFilter, onReview, loading }: {
  submissions: Submission[]; filter: FilterStatus;
  onFilter: (f: FilterStatus) => void; onReview: (id: string) => void; loading: boolean;
}) {
  const filters: FilterStatus[] = ["ALL", "COMPLETED", "APPROVED", "FAILED"];

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">All Submissions</h1>
          <p className="page-sub">{submissions.length} submission{submissions.length !== 1 ? "s" : ""}</p>
        </div>
        <div className="filter-tabs">
          {filters.map(f => (
            <button key={f} className={`filter-tab ${filter === f ? "filter-active" : ""}`} onClick={() => onFilter(f)}>
              {f}
            </button>
          ))}
        </div>
      </div>

      {submissions.length === 0 ? (
        <EmptyState icon="📋" title="No submissions found" desc="No submissions match the current filter." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>ID</th><th>Student</th><th>Status</th>
                <th>AI Score</th><th>Pipeline</th><th>Submitted</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
              {submissions.map(sub => (
                <tr key={sub.id} className="table-row">
                  <td className="mono-cell">#{sub.id.slice(0,8).toUpperCase()}</td>
                  <td>
                    <div className="student-cell">
                      <div className="student-avatar">{((sub as any).student_name?.[0] ?? "S")}</div>
                      <div>
                        <div className="student-name">{(sub as any).student_name ?? "—"}</div>
                        <div className="student-email">{(sub as any).student_email ?? ""}</div>
                      </div>
                    </div>
                  </td>
                  <td><StatusBadge status={sub.status} /></td>
                  <td>{sub.ai_score != null ? <ScoreRing score={sub.ai_score} size={52} /> : <span className="dim">—</span>}</td>
                  <td><PipelineSteps sub={sub} /></td>
                  <td><RelTime iso={sub.created_at} /></td>
                  <td>
                    <button className="btn-sm" onClick={() => onReview(sub.id)}>
                      {sub.status === "COMPLETED" ? "Review" : "View"}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

// ─── Review / Approve ─────────────────────────────────────────────────────────
function ReviewPage({ detail, onBack, onApproved }: {
  detail: SubmissionDetail; onBack: () => void; onApproved: () => void;
}) {
  const { submission, ocr, result, explainability } = detail;
  const [overrideScore, setOverrideScore] = useState<string>(result?.ai_score?.toString() ?? "");
  const [feedback, setFeedback] = useState(result?.lecturer_feedback ?? "");
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState("");

  const alreadyApproved = !!result?.approved_at;

  const handleApprove = async () => {
    setSaving(true);
    setError("");
    try {
      const score = overrideScore !== "" ? parseFloat(overrideScore) : undefined;
      await api.lecturer.approve(submission.id, score, feedback);
      setSaved(true);
      onApproved();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Review Submission</h1>
          <p className="page-sub">
            #{submission.id.slice(0, 8).toUpperCase()} · {(submission as any).student_name ?? "Student"}
          </p>
        </div>
        <button className="btn-ghost" onClick={onBack}>← Back</button>
      </div>

      <div className="review-grid">
        {/* Left: Submission info */}
        <div className="review-left">
          <div className="detail-card">
            <h3 className="card-title">Submission Info</h3>
            <div className="info-rows">
              <InfoRow label="Status" value={<StatusBadge status={submission.status} />} />
              <InfoRow label="Submitted" value={<RelTime iso={submission.created_at} />} />
              {(submission as any).student_email && (
                <InfoRow label="Student Email" value={(submission as any).student_email} />
              )}
            </div>
          </div>

          <div className="detail-card">
            <h3 className="card-title">Pipeline Steps</h3>
            <PipelineSteps sub={submission} />
          </div>

          {ocr?.extracted_text && (
            <div className="detail-card">
              <h3 className="card-title">OCR — Extracted Labels</h3>
              <pre className="ocr-text">{ocr.extracted_text}</pre>
            </div>
          )}

          {explainability?.explanation_text && (
            <div className="detail-card">
              <h3 className="card-title">AI Explanation</h3>
              <p className="explanation-text">{explainability.explanation_text}</p>
              {explainability.gradcam_url && (
                <div className="heatmap-wrap">
                  <img src={explainability.gradcam_url} alt="GradCAM" className="heatmap-img"/>
                  <span className="heatmap-label">GradCAM</span>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Right: Scoring panel */}
        <div className="review-right">
          {result ? (
            <div className="detail-card scoring-panel">
              <h3 className="card-title">AI Score</h3>
              <div className="score-center">
                <ScoreRing score={result.ai_score} size={140} />
              </div>

              {result.scores_breakdown && Object.keys(result.scores_breakdown).length > 0 && (
                <div className="breakdown-section">
                  <h4 className="breakdown-title">Breakdown</h4>
                  {Object.entries(result.scores_breakdown).map(([k, v]) => (
                    <div key={k} className="breakdown-row">
                      <span className="breakdown-key">{k}</span>
                      <div className="breakdown-bar-wrap">
                        <div className="breakdown-bar" style={{ width: `${Number(v) ?? 0}%` }}/>
                      </div>
                      <span className="breakdown-val">{String(v)}</span>
                    </div>
                  ))}
                </div>
              )}

              {!alreadyApproved && (
                <div className="approval-section">
                  <h4 className="approval-title">Score Override (optional)</h4>
                  <input
                    type="number" min="0" max="100" step="0.5"
                    className="score-input"
                    placeholder={`AI: ${result.ai_score}`}
                    value={overrideScore}
                    onChange={e => setOverrideScore(e.target.value)}
                  />

                  <h4 className="approval-title">Lecturer Feedback</h4>
                  <textarea
                    className="feedback-input"
                    rows={5}
                    placeholder="Add your feedback for the student…"
                    value={feedback}
                    onChange={e => setFeedback(e.target.value)}
                  />

                  {error && <div className="alert-error">{error}</div>}
                  {saved && <div className="alert-success">✓ Approved and saved</div>}

                  <button className="btn-approve" onClick={handleApprove} disabled={saving}>
                    {saving ? <><span className="spinner-sm"/>Saving…</> : "Approve & Release to Student"}
                  </button>
                </div>
              )}

              {alreadyApproved && (
                <div className="approved-panel">
                  <div className="approved-banner">✓ Already Approved</div>
                  {result.lecturer_feedback && (
                    <>
                      <h4 className="approval-title">Your Feedback</h4>
                      <p className="feedback-text">{result.lecturer_feedback}</p>
                    </>
                  )}
                </div>
              )}
            </div>
          ) : (
            <div className="detail-card">
              <EmptyState icon="⏳" title="Not yet scored" desc="The AI pipeline has not completed scoring for this submission." />
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="info-row">
      <span className="info-label">{label}</span>
      <span className="info-value">{value}</span>
    </div>
  );
}
