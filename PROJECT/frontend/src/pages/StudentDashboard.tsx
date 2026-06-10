// pages/StudentDashboard.tsx
import { useState, useEffect, useRef } from "react";
import { api, Submission, SubmissionDetail } from "../api/client";
import { useAuth } from "../App";
import { StatusBadge, PipelineSteps, ScoreRing, EmptyState, RelTime } from "../components/shared";

type View = "list" | "submit" | "detail";

export function StudentDashboard() {
  const { user, logout } = useAuth();
  const [view, setView] = useState<View>("list");
  const [submissions, setSubmissions] = useState<Submission[]>([]);
  const [selected, setSelected] = useState<SubmissionDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const loadSubmissions = async () => {
    try {
      const data = await api.student.mySubmissions();
      setSubmissions(data);
    } catch (e: any) {
      setError(e.message);
    }
  };

  useEffect(() => { loadSubmissions(); }, []);

  // Poll for PROCESSING submissions
  useEffect(() => {
    const processing = submissions.some(s => s.status === "PENDING" || s.status === "PROCESSING");
    if (!processing) return;
    const t = setInterval(loadSubmissions, 4000);
    return () => clearInterval(t);
  }, [submissions]);

  const openDetail = async (id: string) => {
    setLoading(true);
    try {
      const detail = await api.student.getSubmission(id);
      setSelected(detail);
      setView("detail");
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="app-root">
      <Sidebar role="STUDENT" user={user!} onLogout={logout} activeView={view} setView={setView} />
      <main className="main-content">
        {view === "list" && (
          <SubmissionList
            submissions={submissions}
            onOpen={openDetail}
            onNew={() => setView("submit")}
            loading={loading}
          />
        )}
        {view === "submit" && (
          <SubmitForm
            onSuccess={() => { setView("list"); loadSubmissions(); }}
            onCancel={() => setView("list")}
          />
        )}
        {view === "detail" && selected && (
          <SubmissionDetailView detail={selected} onBack={() => setView("list")} />
        )}
      </main>
    </div>
  );
}

// ─── Sidebar ──────────────────────────────────────────────────────────────────
function Sidebar({ role, user, onLogout, activeView, setView }: {
  role: string; user: any; onLogout: () => void;
  activeView: View; setView: (v: View) => void;
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

      <nav className="sidebar-nav">
        <button className={`nav-item ${activeView === "list" ? "nav-active" : ""}`} onClick={() => setView("list")}>
          <NavIcon name="list" /> My Submissions
        </button>
        <button className={`nav-item ${activeView === "submit" ? "nav-active" : ""}`} onClick={() => setView("submit")}>
          <NavIcon name="upload" /> New Submission
        </button>
      </nav>

      <div className="sidebar-footer">
        <div className="user-chip">
          <div className="user-avatar">{user.name?.[0] ?? "S"}</div>
          <div className="user-info">
            <span className="user-name">{user.name}</span>
            <span className="user-role">Student</span>
          </div>
        </div>
        <button className="logout-btn" onClick={onLogout}>Sign Out</button>
      </div>
    </aside>
  );
}

function NavIcon({ name }: { name: string }) {
  if (name === "list") return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>;
  if (name === "upload") return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>;
  return null;
}

// ─── Submission List ──────────────────────────────────────────────────────────
function SubmissionList({ submissions, onOpen, onNew, loading }: {
  submissions: Submission[]; onOpen: (id: string) => void;
  onNew: () => void; loading: boolean;
}) {
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">My Submissions</h1>
          <p className="page-sub">{submissions.length} total submission{submissions.length !== 1 ? "s" : ""}</p>
        </div>
        <button className="btn-primary" onClick={onNew}>+ New Submission</button>
      </div>

      {submissions.length === 0 ? (
        <EmptyState icon="🔬" title="No submissions yet" desc="Upload a medical image or diagram to get started." />
      ) : (
        <div className="card-grid">
          {submissions.map(sub => (
            <div key={sub.id} className="sub-card" onClick={() => onOpen(sub.id)}>
              <div className="sub-card-top">
                <StatusBadge status={sub.status} />
                <RelTime iso={sub.created_at} />
              </div>
              <div className="sub-card-id">#{sub.id.slice(0, 8).toUpperCase()}</div>
              <PipelineSteps sub={sub} />
              {sub.ai_score != null && (
                <div className="sub-card-score">
                  <ScoreRing score={sub.ai_score} size={80} />
                  <div className="sub-card-score-label">AI Score</div>
                </div>
              )}
              {sub.approved_at && (
                <div className="approved-tag">✓ Approved by Lecturer</div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Submit Form ──────────────────────────────────────────────────────────────
function SubmitForm({ onSuccess, onCancel }: { onSuccess: () => void; onCancel: () => void }) {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState("");
  const [dragOver, setDragOver] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFile = (f: File) => {
    setFile(f);
    if (f.type.startsWith("image/")) {
      const url = URL.createObjectURL(f);
      setPreview(url);
    } else {
      setPreview(null);
    }
  };

  const handleSubmit = async () => {
    if (!file) return;
    setUploading(true);
    setError("");
    try {
      await api.student.submit(file);
      onSuccess();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">New Submission</h1>
          <p className="page-sub">Upload a medical image or annotated diagram for AI scoring</p>
        </div>
        <button className="btn-ghost" onClick={onCancel}>← Back</button>
      </div>

      <div className="submit-container">
        <div
          className={`dropzone ${dragOver ? "dropzone-over" : ""} ${file ? "dropzone-filled" : ""}`}
          onDragOver={e => { e.preventDefault(); setDragOver(true); }}
          onDragLeave={() => setDragOver(false)}
          onDrop={e => { e.preventDefault(); setDragOver(false); const f = e.dataTransfer.files[0]; if (f) handleFile(f); }}
          onClick={() => inputRef.current?.click()}
        >
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp,application/pdf"
            style={{ display: "none" }}
            onChange={e => { const f = e.target.files?.[0]; if (f) handleFile(f); }}
          />
          {!file ? (
            <div className="dropzone-empty">
              <div className="dropzone-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#4dd4f0" strokeWidth="1.5">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/>
                  <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
              </div>
              <p className="dropzone-title">Drop your medical image here</p>
              <p className="dropzone-hint">JPEG, PNG, WebP, or PDF · Max 20MB</p>
            </div>
          ) : (
            <div className="dropzone-preview">
              {preview ? (
                <img src={preview} alt="Preview" className="preview-img" />
              ) : (
                <div className="pdf-preview">
                  <span className="pdf-icon">📄</span>
                  <span className="pdf-name">{file.name}</span>
                </div>
              )}
              <button className="change-file-btn" onClick={e => { e.stopPropagation(); setFile(null); setPreview(null); }}>
                Change File
              </button>
            </div>
          )}
        </div>

        {file && (
          <div className="file-meta">
            <span className="file-name">{file.name}</span>
            <span className="file-size">{(file.size / 1024 / 1024).toFixed(2)} MB</span>
          </div>
        )}

        {error && <div className="alert-error">{error}</div>}

        <div className="submit-actions">
          <button className="btn-ghost" onClick={onCancel}>Cancel</button>
          <button
            className="btn-primary"
            disabled={!file || uploading}
            onClick={handleSubmit}
          >
            {uploading ? <><span className="spinner-sm"/>Uploading…</> : "Submit for Scoring"}
          </button>
        </div>

        <div className="pipeline-info">
          <h4 className="pipeline-info-title">What happens after submission</h4>
          <div className="pipeline-info-steps">
            {["Object Detection (YOLO)", "OCR Label Extraction", "Multimodal Verification (CLIP + SBERT)", "Explainability (GradCAM)", "Score Calculation"].map((s, i) => (
              <div key={s} className="pipeline-info-step">
                <span className="pipeline-info-num">{i + 1}</span>
                <span>{s}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

// ─── Submission Detail ────────────────────────────────────────────────────────
function SubmissionDetailView({ detail, onBack }: { detail: SubmissionDetail; onBack: () => void }) {
  const { submission, ocr, result, explainability } = detail;

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Submission #{submission.id.slice(0, 8).toUpperCase()}</h1>
          <p className="page-sub"><RelTime iso={submission.created_at} /></p>
        </div>
        <button className="btn-ghost" onClick={onBack}>← Back</button>
      </div>

      <div className="detail-grid">
        {/* Status card */}
        <div className="detail-card">
          <h3 className="card-title">Pipeline Status</h3>
          <div className="status-row"><StatusBadge status={submission.status} /></div>
          <PipelineSteps sub={submission} />
        </div>

        {/* Score card */}
        {result && (
          <div className="detail-card detail-card-accent">
            <h3 className="card-title">Your Score</h3>
            <div className="score-center">
              <ScoreRing score={result.ai_score} size={140} />
            </div>
            {result.approved_at && (
              <div className="approved-banner">✓ Approved by Lecturer</div>
            )}
          </div>
        )}

        {/* Breakdown */}
        {result?.scores_breakdown && Object.keys(result.scores_breakdown).length > 0 && (
          <div className="detail-card span-2">
            <h3 className="card-title">Score Breakdown</h3>
            <div className="breakdown-table">
              {Object.entries(result.scores_breakdown).map(([k, v]) => (
                <div key={k} className="breakdown-row">
                  <span className="breakdown-key">{k}</span>
                  <div className="breakdown-bar-wrap">
                    <div className="breakdown-bar" style={{ width: `${Number(v) ?? 0}%` }} />
                  </div>
                  <span className="breakdown-val">{String(v)}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Lecturer feedback */}
        {result?.lecturer_feedback && (
          <div className="detail-card span-2">
            <h3 className="card-title">Lecturer Feedback</h3>
            <p className="feedback-text">{result.lecturer_feedback}</p>
          </div>
        )}

        {/* OCR results */}
        {ocr?.extracted_text && (
          <div className="detail-card">
            <h3 className="card-title">Extracted Labels (OCR)</h3>
            <pre className="ocr-text">{ocr.extracted_text}</pre>
          </div>
        )}

        {/* Explainability */}
        {explainability?.explanation_text && (
          <div className="detail-card">
            <h3 className="card-title">AI Explanation</h3>
            <p className="explanation-text">{explainability.explanation_text}</p>
            {explainability.gradcam_url && (
              <div className="heatmap-wrap">
                <img src={explainability.gradcam_url} alt="GradCAM" className="heatmap-img"/>
                <span className="heatmap-label">GradCAM Heatmap</span>
              </div>
            )}
          </div>
        )}

        {/* No result yet */}
        {!result && (
          <div className="detail-card span-2">
            <EmptyState icon="⏳" title="Processing in progress" desc="Your submission is being analysed by the AI pipeline. This usually takes under a minute." />
          </div>
        )}
      </div>
    </div>
  );
}
