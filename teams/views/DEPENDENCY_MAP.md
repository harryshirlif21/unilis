# Team Management UI Dependency Map

## 1. Backend Endpoints Referenced by lecturer_teams.php

### Export Endpoints (Direct links / JS)
| Endpoint | Method | Used By |
|---|---|---|
| `../../teams/api/export_all_teams_pdf.php` | GET | Global export section (direct anchor link) |
| `../../teams/api/export_all_teams_excel.php` | GET | Global export section (direct anchor link) |
| `../../teams/api/export_pdf.php?team_id=X` | GET | `generatePDF()` JS function |
| `../../teams/api/export_excel.php?team_id=X` | GET | `generateExcel()` JS function |
| `../../teams/api/peer_evaluation_report.php?team_id=X&format=json` | GET | Ellipsis menu anchor |
| `../../teams/api/peer_evaluation_report.php?team_id=X&format=csv` | GET | Ellipsis menu anchor + `exportPeerCsvBtn` |
| `../../teams/api/export_group_members_pdf.php?team_id=X` | GET | Ellipsis menu anchor |

### AJAX/POST Endpoints
| Endpoint | Method | Used By |
|---|---|---|
| `../../teams/api/delete_team.php` | POST (JSON) | `deleteTeam()` JS |
| `../../teams/api/award_mark.php` | POST (FormData) | `awardMark()`, `awardGroupMark()`, `awardIndividualMark()` |
| `../../teams/api/serve_file.php?file_id=X` | GET | `openFileViewer()` - team files |
| `../../teams/api/serve_file.php?submission_id=X` | GET | `openFileViewer()` - submissions |
| `../../teams/api/lecturer_team_insights.php?team_id=X` | GET | `loadPeerEvalInsights()` |
| `../../teams/api/get_team_supervisors.php?team_id=X` | GET | `loadTeamSupervisors()` |
| `../../teams/api/search_supervisor.php?team_id=X&email=Y` | GET | `searchSupervisor()` |
| `../../teams/api/request_supervisor.php` | POST (JSON) | `requestSupervisor()` |
| `../../teams/api/approve_supervisor.php` | POST (JSON) | `approveSupervisor()` |
| `../../teams/api/remove_supervisor.php` | POST (JSON) | `removeSupervisor()` |

### Other Views Linked
- `approve_membership_requests.php` - Membership requests link
- `workspace.php?team_id=X` - Open Team Workspace link

## 2. JavaScript Functions & Their Dependencies

| Function | Depends On (IDs/Classes) | Trigger |
|---|---|---|
| `deleteTeam()` | None (takes args) | Ellipsis menu link `onclick` |
| `toggleMenu(teamId)` | `#menu-{id}` `.ellipsis-content` `.ellipsis-btn` | `onclick` on `.ellipsis-btn` |
| `toggleStudentField(select, teamId)` | `#studentGroup{teamId}` | `onchange` on mark type select |
| `awardMark(event, teamId)` | `#markForm{teamId}` | `onsubmit` on form |
| `awardGroupMark(teamId)` | `#group_mark_{id}`, `#group_component_{id}`, `#group_max_mark_{id}`, `#group_notes_{id}` | `onclick` button |
| `awardIndividualMark(studentId, teamId)` | `#individual_mark_{studentId}`, `#individual_component_{studentId}` | `onclick` button |
| `viewSubmission(submissionId)` | - | Anchor link |
| `viewTeamFile(fileId)` | - | `onclick` on read-btn / link |
| `openFileViewer(type, id)` | `#fileViewerOverlay`, `#fileViewerTitle`, `#fileViewerContent` | Called by `viewSubmission`/`viewTeamFile` |
| `closeFileViewer()` | `#fileViewerOverlay` | Close button, overlay click, Escape key |
| `fixSubmissionFile()` / `fixTeamFile()` | - | File viewer error buttons |
| `toggleMarkBox(element, teamId, studentId)` | `#individual_mark_{studentId}`, `#group_mark_{teamId}` | `onclick` on `.mark-box` |
| `generatePDF(teamId)` | - | Ellipsis menu link |
| `generateExcel(teamId)` | - | Ellipsis menu link |
| `escapeHtml(value)` | - | Helper for insights rendering |
| `formatDateTime(value)` | - | Helper for insights rendering |
| `renderHeatmap(heatmap)` | - | Insights body |
| `loadPeerEvalInsights(teamId)` | `#peerEvalInsightsStatus`, `#peerEvalInsightsBody` | `loadPeerEvalInsightsBtn` click |
| `getSelectedPeerEvalTeamId()` | `.peer-eval-team-picker.visible .peer-eval-team-select` | Helper |
| `initPeerEvalUnitTiles()` | `.peer-eval-unit-tile`, `.peer-eval-team-picker`, `#insightsUnitSelect` | Auto-init on load |
| `openSupervisorModal(teamId, teamTitle)` | `#supervisorModal`, `#supervisorTeamId`, `#supervisorTeamTitle` | Ellipsis menu link |
| `closeSupervisorModal()` | `#supervisorModal` | Modal close button |
| `canManageTeamSupervisors()` | - | Permission check |
| `loadTeamSupervisors(teamId)` | `#existingSupervisors` | Modal open |
| `loadAvailableSupervisors(teamId)` | - | Modal open (legacy stub) |
| `searchSupervisor()` | `#supervisorTeamId`, `#supervisorEmail`, `#searchResults` | Search button |
| `selectSupervisor(personId, type, name)` | `#supervisorTeamId`, `#supervisorError` | Search result click |
| `showSupervisorError(message, details)` | `#supervisorError` | Error display |
| `clearSupervisorError()` | `#supervisorError` | Clear error |
| `requestSupervisor(teamId, personId, type)` | `#supervisorEmail`, `#searchResults` | `selectSupervisor()` confirm |
| `approveSupervisor(id, approved)` | `#supervisorTeamId` | Supervisor list button |
| `removeSupervisor(id)` | `#supervisorTeamId` | Supervisor list button |

## 3. Critical IDs Used by JS (MUST PRESERVE)

### Team-Level Inputs
- `markForm{teamId}` - Form ID
- `studentGroup{teamId}` - Student select wrapper
- `group_mark_{teamId}` - Group mark input
- `group_component_{teamId}` - Group component input
- `group_max_mark_{teamId}` - Group max mark input
- `group_notes_{teamId}` - Group notes textarea

### Member-Level Inputs
- `individual_mark_{studentId}` - Individual mark input
- `individual_component_{studentId}` - Individual component input

### Menus
- `menu-{teamId}` - Ellipsis dropdown content

### Insights
- `insightsUnitSelect` - Unit dropdown
- `peerEvalUnitTiles` - Unit tiles container
- `peerEvalTeams-{unitKey}` - Team picker for unit
- `loadPeerEvalInsightsBtn` - Load insights button
- `exportPeerCsvBtn` - Export CSV button
- `peerEvalInsightsStatus` - Status text
- `peerEvalInsightsBody` - Insights body container

### File Viewer
- `fileViewerOverlay` - Overlay element
- `fileViewerTitle` - Title element
- `fileViewerContent` - Content element

### Supervisor Modal
- `supervisorModal` - Modal root
- `supervisorTeamId` - Hidden team ID input
- `supervisorTeamTitle` - Team title span
- `existingSupervisors` - Supervisors list
- `supervisorEmail` - Email input
- `searchResults` - Results container
- `supervisorError` - Error display div

## 4. Critical CSS Classes Used (MUST PRESERVE)

- `.team-card`, `.team-header`, `.team-title-section`, `.team-meta`
- `.badge`, `.active`, `.locked`, `.archived`
- `.team-leader`, `.leader-info`, `.leader-contact-item`
- `.members-section`, `.members-grid`, `.member-card`
- `.member-header`, `.member-name`, `.member-role`
- `.role-leader`, `.role-member` (+ dynamic role classes)
- `.marks-section`, `.marks-form`, `.form-grid`, `.form-group`
- `.marks-table`, `.submit-btn`
- `.global-export`, `.global-export-buttons`, `.global-export-btn`
- `.export-all-pdf`, `.export-all-excel`
- `.ellipsis-menu`, `.ellipsis-btn`, `.ellipsis-content`, `.show`
- `.mark-box`, `.checked`
- `.read-btn`
- `.file-viewer-overlay`, `.active`, `.file-viewer`, `.file-viewer-header`
- `.file-viewer-title`, `.file-viewer-close`, `.file-viewer-content`
- `.file-viewer-loading`, `.file-viewer-error`
- `.empty`
- `.peer-eval-units`, `.peer-eval-unit-tile`, `.active`
- `.unit-code`, `.unit-name`, `.unit-team-count`
- `.peer-eval-team-picker`, `.visible`, `.peer-eval-team-select`
- `.peer-eval-actions`
- `.supervisor-modal`, `.supervisor-modal-content`, `.supervisor-modal-header`
- `.supervisor-modal-close`, `.supervisor-modal-body`
- `.supervisor-section`, `.supervisor-list`, `.supervisor-item`
- `.supervisor-info`, `.primary-badge`, `.nominated-badge`, `.status-badge`
- `.supervisor-actions`, `.btn-approve`, `.btn-reject`, `.btn-remove`
- `.supervisor-form`, `.supervisor-input`, `.supervisor-error`
- `.search-results`, `.search-result-item`, `.search-result-main`
- `.assign-button`, `.name`, `.email`, `.type`, `.team-count`
- `.btn-search`, `.btn-request`, `.supervisor-hint`
- `.approved`, `.pending`, `.rejected` (status classes)

## 5. Form Field Names (Submitted to Backend)
- `mark_type`, `student_id`, `component`, `mark`, `max_mark`, `notes`, `csrf_token`
- `action` (part of FormData in awardGroupMark/awardIndividualMark)
- `team_id`, `lecturer_id`, `supervisor_type` (supervisor requests)

## 6. Inline JS Dependencies on PHP Data
- `<?= $_SESSION['csrf_token']; ?>` - used in JS deleteTeam and award functions
- `<?= $team['team_id']; ?>` - passed to many JS functions
- `<?= $member['student_id']; ?>` - passed to awardIndividualMark, toggleMarkBox
- `<?= htmlspecialchars($team['team_title']); ?>` - shown in delete/assign modals and supervisor modal title

## 7. PHP Includes Required
- `../../config/db.php` - DB connection
- `../includes/ensure_team_marks.php` - Ensures team_marks table
- `../includes/team_access.php` - Permission/access checks

## 8. Data Tables Referenced (DO NOT CHANGE)
- `teams`
- `units`
- `team_members`
- `students`
- `lecturer_units`
- `team_supervisors`
- `team_marks`
- `lecturers`
- `team_files`
- `team_activity_log`