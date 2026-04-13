# Team Membership Revocation Feature - Implementation Guide

## Overview
This feature allows students to request to leave a team and team leads to request member removal. Both types of requests require approval from both the lecturer and team lead before taking effect.

## Database Changes

### New Table: `team_membership_requests`
Created table to track all membership revocation and removal requests:

```sql
CREATE TABLE `team_membership_requests` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `team_id` int NOT NULL,
  `student_id` int NOT NULL,
  `requested_by` int NOT NULL,
  `request_type` enum('leave', 'remove'),
  `reason` text,
  `status` enum('pending', 'approved', 'rejected', 'cancelled'),
  `approved_by_lecturer` int,
  `approved_by_team_lead` int,
  `lecturer_approval_at` datetime,
  `team_lead_approval_at` datetime,
  `rejection_reason` text,
  `created_at` datetime,
  `updated_at` datetime
);
```

Run migration: `teams/api/migrate_membership_requests_table.php`

---

## Features

### 1. Student Membership Leave Request
**File:** `teams/views/manage_team.php`
**User Role:** Student (regular team member)

**How it works:**
- Button: "Request to Leave" (yellow button)
- Appears on a student's own member card
- Student provides optional reason for leaving
- Request sends to both lecturer and team lead for approval
- Member is removed only after BOTH approve

**API:** `teams/api/request_membership_leave.php`

---

### 2. Team Lead Member Removal
**File:** `teams/views/manage_team.php`
**User Role:** Team Lead

**How it works:**
- Button: "Removal Request" (red button) on member cards
- Team lead selects a member and provides reason
- Request is auto-approved by team lead (already authorized)
- Awaits lecturer approval only
- Old "Remove" button still available for direct removal

**API:** `teams/api/request_member_removal.php`

---

### 3. Lecturer Approval Portal
**File:** `teams/views/approve_membership_requests.php`
**User Role:** Lecturer

**How it works:**
- Access: Link in `lecturer_teams.php` - "View Membership Requests"
- Shows all pending requests for lecturer's units
- Displays approval status from lecturer and team lead
- Can approve or reject with optional reason
- Once both approve: member is automatically removed

**API:** `teams/api/approve_membership_request.php`

---

### 4. Team Lead Request Monitoring
**File:** `teams/views/manage_team.php`
**User Role:** Team Lead

**How it works:**
- Button: "View Requests" on team lead's member card
- Shows pending requests for their team only
- Can see approval status from both lecturer and team lead
- Cannot directly approve/reject (only lecturer can)

**API:** `teams/api/get_pending_membership_requests.php`

---

## API Endpoints

### POST `/teams/api/request_membership_leave.php`
Student requesting to leave a team

**Parameters:**
```json
{
  "team_id": 1,
  "reason": "Optional reason text",
  "csrf_token": "..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Leave request submitted successfully...",
  "request_id": 123
}
```

---

### POST `/teams/api/request_member_removal.php`
Team lead requesting removal of a member

**Parameters:**
```json
{
  "team_id": 1,
  "student_id": 5,
  "reason": "Optional reason",
  "csrf_token": "..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Removal request submitted...",
  "request_id": 124
}
```

---

### GET `/teams/api/get_pending_membership_requests.php`
Get pending requests for a team

**Parameters:**
- `team_id`: Team ID

**Response:**
```json
{
  "success": true,
  "team": {...},
  "requests": [
    {
      "id": 123,
      "team_id": 1,
      "student_id": 5,
      "student_name": "John Doe",
      "student_reg": "REG001",
      "request_type": "leave",
      "reason": "...",
      "status": "pending",
      "approved_by_lecturer": null,
      "approved_by_team_lead": null,
      "created_at": "2026-04-13 10:30:00"
    }
  ]
}
```

---

### POST `/teams/api/approve_membership_request.php`
Approve or reject a membership request

**Parameters:**
```json
{
  "request_id": 123,
  "action": "approve|reject",
  "rejection_reason": "Optional reason if rejecting",
  "csrf_token": "..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Request approved...",
  "status": "approved|partial|rejected"
}
```

- `approved`: Both lecturer and team lead approved, member removed
- `partial`: One approval received, awaiting other
- `rejected`: Request was rejected

---

## Workflow Diagrams

### Leave Request Workflow
```
Student clicks "Request to Leave"
    ↓
Submits reason (optional)
    ↓
Request created (status: pending)
    ↓
Lecturer reviews & approves/rejects
    ↓
If approved, Team Lead reviews & approves/rejects
    ↓
If both approve: Member removed, status: approved
    ↓
If either rejects: Status: rejected, member stays
```

### Removal Request Workflow
```
Team Lead clicks "Removal Request" on member
    ↓
Submits reason
    ↓
Request created + Team Lead auto-approves
    ↓
Lecturer reviews & approves/rejects
    ↓
If approved: Member removed, status: approved
    ↓
If rejected: Status: rejected, member stays
```

---

## UI Updates

### Student View (manage_team.php)
- **Regular Member Card:** 
  - Shows "Request to Leave" button
  
- **Own Team Lead Card:** 
  - Shows "View Requests" button to see pending requests

- **Other Member Cards (if current user is team lead):**
  - Shows "Removal Request" + "Remove" buttons

### Lecturer View (lecturer_teams.php)
- New section: "Membership Requests"
- Link to `approve_membership_requests.php`

### Lecturer Approval View (approve_membership_requests.php)
- All requests for lecturer's units
- Shows request type (leave/removal)
- Shows each approver's status
- Action buttons: Approve/Reject

---

## Status Flow

```
pending → (approved_by_lecturer + approved_by_team_lead) → approved
  ↓
  →(rejected) → rejected
```

---

## Activity Logging

All requests trigger activity log entries:
- `membership_leave_request`: Student requests to leave
- `membership_removal_request`: Team lead requests removal
- `membership_removal_approved`: Request approved and executed
- `membership_removal_rejected`: Request rejected

---

## Security Features

✓ CSRF token validation on all POST endpoints
✓ Role-based access control (student, team lead, lecturer)
✓ Team membership verification before processing
✓ Request status validation (can't process twice)
✓ Foreign key constraints on database
✓ Proper error handling and exceptions

---

## Testing Checklist

- [ ] Student can submit leave request
- [ ] Lecturer can approve/reject leave request
- [ ] Team lead can see pending requests
- [ ] Team lead can request member removal with reason
- [ ] Lecturer sees both leave and removal requests
- [ ] Request shows correct approval status
- [ ] Member is removed only after both approvals
- [ ] Member is not removed if rejected
- [ ] Activity logs are created
- [ ] Duplicate requests are prevented

---

## File Summary

### New Files Created:
1. `database_setup/create_team_membership_requests_table.sql`
2. `teams/api/migrate_membership_requests_table.php`
3. `teams/api/request_membership_leave.php`
4. `teams/api/request_member_removal.php`
5. `teams/api/get_pending_membership_requests.php`
6. `teams/api/approve_membership_request.php`
7. `teams/views/approve_membership_requests.php`

### Files Modified:
1. `teams/views/manage_team.php` - Added leave/removal request UI
2. `teams/views/lecturer_teams.php` - Added membership requests link

### Files Referenced:
1. `teams/models/ActivityLog.php` - For logging
2. `config/db.php` - Database connection

---

## Future Enhancements

- Email notifications to approvers
- Notification dashboard for pending requests
- Student portfolio tracking of team changes
- Team history/audit trail
- Team stability metrics
- Automatic escalation if request pending too long

---

End of Implementation Guide
